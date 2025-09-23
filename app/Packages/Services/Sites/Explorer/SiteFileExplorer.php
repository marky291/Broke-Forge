<?php

namespace App\Packages\Services\Sites\Explorer;

use App\Exceptions\ServerFileExplorerException;
use App\Models\Server;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Spatie\Ssh\Ssh;
use Symfony\Component\Process\Process;

class SiteFileExplorer
{
    protected const LIST_EXIT_INVALID_BASE = 3;
    protected const LIST_EXIT_INVALID_PATH = 4;
    protected const LIST_EXIT_NOT_DIRECTORY = 5;
    protected const LIST_EXIT_READ_FAILED = 6;

    protected const RESOLVE_EXIT_INVALID_BASE = 3;
    protected const RESOLVE_EXIT_INVALID_PATH = 4;
    protected const RESOLVE_EXIT_NOT_DIRECTORY = 5;
    protected const RESOLVE_EXIT_NOT_FILE = 6;

    public function __construct(protected Server $server)
    {
    }

    /**
     * Fetch the items within the given directory relative to the server base path.
     *
     * @return array{path: string, items: array<int, array{name: string, path: string, type: string, size: int|null, modifiedAt: string, permissions: string}>}
     */
    public function list(string $relativePath = ''): array
    {
        $relativePath = $this->normalizeRelativePath($relativePath);

        $command = $this->buildListCommand($relativePath);
        $process = $this->runCommand($command, timeout: 30);

        if (! $process->isSuccessful()) {
            throw $this->mapListFailure($process);
        }

        $output = trim($process->getOutput());

        if ($output === '') {
            return [
                'path' => $relativePath,
                'items' => [],
            ];
        }

        try {
            /** @var array{path: string, items: array<int, array<string, mixed>>} $decoded */
            $decoded = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new ServerFileExplorerException('Failed to decode file listing response.', 500);
        }

        return $decoded;
    }

    /**
     * Upload a file into the given directory relative to the server base path.
     */
    public function upload(string $relativeDirectory, UploadedFile $file): void
    {
        $relativeDirectory = $this->normalizeRelativePath($relativeDirectory);
        $absoluteDirectory = $this->resolveAbsolutePath($relativeDirectory, expect: 'dir');

        $originalName = $file->getClientOriginalName();
        $filename = $this->sanitizeFilename($originalName !== '' ? $originalName : $file->hashName());

        if ($filename === '') {
            throw new ServerFileExplorerException('Unable to determine a valid filename for the upload.', 422);
        }

        $localPath = $file->getRealPath();

        if ($localPath === false || $localPath === null) {
            throw new ServerFileExplorerException('Uploaded file could not be accessed on the server.', 500);
        }

        $remotePath = rtrim($absoluteDirectory, '/').'/'.$filename;

        $process = $this->makeSsh()
            ->setTimeout(120)
            ->upload(escapeshellarg($localPath), escapeshellarg($remotePath));

        if (! $process->isSuccessful()) {
            $message = trim($process->getErrorOutput()) ?: 'File upload failed during transmission.';

            throw new ServerFileExplorerException($message, 500);
        }
    }

    /**
     * Download a file from the server to a temporary local path.
     *
     * @return array{path: string, filename: string}
     */
    public function download(string $relativeFilePath): array
    {
        $relativeFilePath = $this->normalizeRelativePath($relativeFilePath);

        if ($relativeFilePath === '') {
            throw new ServerFileExplorerException('A file path is required for download.', 422);
        }

        $absolutePath = $this->resolveAbsolutePath($relativeFilePath, expect: 'file');
        $filename = basename($absolutePath);

        $localPath = tempnam($this->temporaryStoragePath(), 'bf-dl-');

        if ($localPath === false) {
            throw new ServerFileExplorerException('Failed to allocate a temporary file for download.', 500);
        }

        $handle = fopen($localPath, 'w+b');

        if ($handle === false) {
            throw new ServerFileExplorerException('Unable to open temporary download file for writing.', 500);
        }

        try {
            $process = $this->makeSsh()
                ->onOutput(function (string $type, string $buffer) use ($handle): void {
                    if ($type === Process::OUT) {
                        fwrite($handle, $buffer);
                    }
                })
                ->setTimeout(180)
                ->execute(sprintf('cat %s', escapeshellarg($absolutePath)));
        } finally {
            fclose($handle);
        }

        if (! isset($process) || ! $process->isSuccessful()) {
            @unlink($localPath);

            $message = isset($process)
                ? (trim($process->getErrorOutput()) ?: 'File download failed while reading from the server.')
                : 'File download failed while reading from the server.';

            throw new ServerFileExplorerException($message, 500);
        }

        return [
            'path' => $localPath,
            'filename' => $filename,
        ];
    }

    protected function makeSsh(): Ssh
    {
        $user = $this->server->ssh_app_user;

        if (! $user) {
            throw new ServerFileExplorerException('The server does not have an SSH application user configured.', 422);
        }

        $ssh = Ssh::create($user, $this->server->public_ip, $this->server->ssh_port);
        $ssh->disableStrictHostKeyChecking();

        return $ssh;
    }

    protected function runCommand(string $command, int $timeout): Process
    {
        return $this->makeSsh()
            ->setTimeout($timeout)
            ->execute($command);
    }

    protected function normalizeRelativePath(?string $path): string
    {
        $path ??= '';
        $path = trim($path);
        $path = str_replace("\0", '', $path);
        $path = trim($path, '/');

        if ($path === '') {
            return '';
        }

        $segments = [];

        foreach (explode('/', $path) as $segment) {
            $segment = trim($segment);

            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new ServerFileExplorerException('Path traversal is not allowed.', 422);
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    protected function sanitizeFilename(string $name): string
    {
        $name = trim(str_replace(["\0", '/', '\\'], '', $name));

        if ($name === '') {
            return $name;
        }

        // Collapse consecutive whitespace to a single space for neatness.
        $name = preg_replace('/\s+/', ' ', $name) ?: $name;

        // Guard against overly long filenames.
        return Str::limit($name, 200, '');
    }

    protected function basePath(): string
    {
        $user = $this->server->ssh_app_user;

        if (! $user) {
            throw new ServerFileExplorerException('The server does not have an SSH application user configured.', 422);
        }

        return '/home/'.$user;
    }

    protected function buildListCommand(string $relativePath): string
    {
        $script = <<<'PHP'
error_reporting(E_ERROR);
$base = getenv('BF_BASE') ?: '';
$base = rtrim($base, '/');
if ($base === '') {
    fwrite(STDERR, 'BASE_UNSET');
    exit(3);
}
$baseReal = realpath($base);
if ($baseReal === false) {
    fwrite(STDERR, 'BASE_UNREADABLE');
    exit(3);
}
$relative = getenv('BF_PATH');
$relative = $relative === false ? '' : trim($relative, '/');
if ($relative === '') {
    $target = $baseReal;
    $relativePath = '';
} else {
    $candidate = realpath($baseReal . '/' . $relative);
    if ($candidate === false) {
        fwrite(STDERR, 'NOT_FOUND');
        exit(5);
    }
    if (strpos($candidate, $baseReal) !== 0) {
        fwrite(STDERR, 'INVALID_PATH');
        exit(4);
    }
    $target = $candidate;
    $relativePath = trim(str_replace($baseReal, '', $target), '/');
}
if (! is_dir($target)) {
    fwrite(STDERR, 'NOT_A_DIRECTORY');
    exit(5);
}
$entries = scandir($target);
if ($entries === false) {
    fwrite(STDERR, 'DIR_READ_FAILED');
    exit(6);
}
$items = [];
foreach ($entries as $entry) {
    if ($entry === '.' || $entry === '..') {
        continue;
    }
    $fullPath = $target . '/' . $entry;
    $items[] = [
        'name' => $entry,
        'path' => ltrim(($relativePath !== '' ? $relativePath . '/' : '') . $entry, '/'),
        'type' => is_dir($fullPath) ? 'directory' : 'file',
        'size' => is_file($fullPath) ? (int) @filesize($fullPath) : null,
        'modifiedAt' => date(DATE_ATOM, @filemtime($fullPath) ?: time()),
        'permissions' => substr(sprintf('%o', @fileperms($fullPath) ?: 0), -4),
    ];
}
usort($items, static function (array $left, array $right): int {
    if ($left['type'] === $right['type']) {
        return strcasecmp($left['name'], $right['name']);
    }

    return $left['type'] === 'directory' ? -1 : 1;
});
echo json_encode([
    'path' => $relativePath,
    'items' => $items,
], JSON_UNESCAPED_SLASHES);
PHP;

        return sprintf(
            'BF_BASE=%s BF_PATH=%s php -d detect_unicode=0 -r %s',
            escapeshellarg($this->basePath()),
            escapeshellarg($relativePath),
            escapeshellarg($script),
        );
    }

    protected function resolveAbsolutePath(string $relativePath, string $expect): string
    {
        $script = <<<'PHP'
error_reporting(E_ERROR);
$base = getenv('BF_BASE') ?: '';
$base = rtrim($base, '/');
if ($base === '') {
    fwrite(STDERR, 'BASE_UNSET');
    exit(3);
}
$baseReal = realpath($base);
if ($baseReal === false) {
    fwrite(STDERR, 'BASE_UNREADABLE');
    exit(3);
}
$relative = getenv('BF_PATH');
$relative = $relative === false ? '' : trim($relative, '/');
if ($relative === '') {
    $target = $baseReal;
} else {
    $candidate = realpath($baseReal . '/' . $relative);
    if ($candidate === false) {
        fwrite(STDERR, 'NOT_FOUND');
        exit(5);
    }
    if (strpos($candidate, $baseReal) !== 0) {
        fwrite(STDERR, 'INVALID_PATH');
        exit(4);
    }
    $target = $candidate;
}
$type = getenv('BF_EXPECT') ?: 'any';
if ($type === 'dir' && ! is_dir($target)) {
    fwrite(STDERR, 'NOT_A_DIRECTORY');
    exit(5);
}
if ($type === 'file' && ! is_file($target)) {
    fwrite(STDERR, 'NOT_A_FILE');
    exit(6);
}
echo $target;
PHP;

        $command = sprintf(
            'BF_BASE=%s BF_PATH=%s BF_EXPECT=%s php -d detect_unicode=0 -r %s',
            escapeshellarg($this->basePath()),
            escapeshellarg($relativePath),
            escapeshellarg($expect),
            escapeshellarg($script),
        );

        $process = $this->runCommand($command, timeout: 15);

        if (! $process->isSuccessful()) {
            throw $this->mapResolveFailure($process, $expect === 'dir');
        }

        $absolutePath = trim($process->getOutput());

        if ($absolutePath === '') {
            throw new ServerFileExplorerException('Failed to resolve the remote path.', 500);
        }

        return $absolutePath;
    }

    protected function mapListFailure(Process $process): ServerFileExplorerException
    {
        return match ($process->getExitCode()) {
            self::LIST_EXIT_INVALID_BASE => new ServerFileExplorerException('The server file base directory is not accessible.', 500),
            self::LIST_EXIT_INVALID_PATH => new ServerFileExplorerException('The requested directory path is invalid.', 422),
            self::LIST_EXIT_NOT_DIRECTORY => new ServerFileExplorerException('The requested directory could not be found on the server.', 404),
            self::LIST_EXIT_READ_FAILED => new ServerFileExplorerException('The directory could not be read on the server.', 500),
            default => new ServerFileExplorerException(
                trim($process->getErrorOutput()) ?: 'Unable to fetch files from the server.',
                500
            ),
        };
    }

    protected function mapResolveFailure(Process $process, bool $expectDirectory): ServerFileExplorerException
    {
        return match ($process->getExitCode()) {
            self::RESOLVE_EXIT_INVALID_BASE => new ServerFileExplorerException('The server file base directory is not accessible.', 500),
            self::RESOLVE_EXIT_INVALID_PATH => new ServerFileExplorerException('The requested path is invalid.', 422),
            self::RESOLVE_EXIT_NOT_DIRECTORY => new ServerFileExplorerException(
                $expectDirectory ? 'The requested directory could not be found on the server.' : 'The path does not point to a directory.',
                404
            ),
            self::RESOLVE_EXIT_NOT_FILE => new ServerFileExplorerException('The requested file could not be found on the server.', 404),
            default => new ServerFileExplorerException(
                trim($process->getErrorOutput()) ?: 'The requested path could not be resolved on the server.',
                500
            ),
        };
    }

    protected function temporaryStoragePath(): string
    {
        $directory = storage_path('app/tmp/server-files');

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        return $directory;
    }
}
