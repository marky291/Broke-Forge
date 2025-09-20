<?php

namespace App\Provision\Sites;

use App\Models\Server;
use App\Models\Site;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Spatie\Ssh\Ssh;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class SiteCommandProvision
{
    public function __construct(
        protected Server $server,
        protected Site $site,
    ) {}

    /**
     * @return array{command: string, output: string, errorOutput: string, exitCode: int|null, ranAt: string, durationMs: int, success: bool}
     */
    public function run(string $command): array
    {
        $command = trim($command);

        if ($command === '') {
            throw new RuntimeException('Cannot execute an empty command.');
        }

        $sshUser = $this->server->ssh_app_user;

        if (! $sshUser) {
            throw new RuntimeException('The server does not have an application SSH user configured.');
        }

        $workingDirectory = $this->resolveWorkingDirectory();

        $remoteCommand = sprintf('cd %s && %s', escapeshellarg($workingDirectory), $command);

        $start = (int) (microtime(true) * 1000);

        try {
            $process = $this->executeOverSsh($sshUser, $remoteCommand);
        } catch (ProcessTimedOutException $exception) {
            $duration = (int) (microtime(true) * 1000) - $start;

            return [
                'command' => $command,
                'output' => '',
                'errorOutput' => 'Command timed out after 120 seconds.',
                'exitCode' => $exception->getProcess()?->getExitCode(),
                'ranAt' => now()->toIso8601String(),
                'durationMs' => $duration,
                'success' => false,
            ];
        }

        $duration = (int) (microtime(true) * 1000) - $start;
        $success = $process->isSuccessful();
        $output = rtrim($process->getOutput());
        $error = rtrim($process->getErrorOutput());

        if (! $success) {
            Log::warning('Site command exited with a non-zero code.', [
                'server_id' => $this->server->id,
                'site_id' => $this->site->id,
                'command' => $command,
                'exit_code' => $process->getExitCode(),
                'stderr' => $error,
            ]);
        }

        return [
            'command' => $command,
            'output' => $output,
            'errorOutput' => $error,
            'exitCode' => $process->getExitCode(),
            'ranAt' => now()->toIso8601String(),
            'durationMs' => $duration,
            'success' => $success,
        ];
    }

    protected function resolveWorkingDirectory(): string
    {
        if ($this->site->document_root) {
            return $this->site->document_root;
        }

        if ($this->site->domain) {
            return "/home/brokeforge/{$this->site->domain}";
        }

        return "/home/brokeforge/site-{$this->site->id}";
    }

    protected function executeOverSsh(string $sshUser, string $remoteCommand): Process
    {
        return Ssh::create($sshUser, $this->server->public_ip, $this->server->ssh_port)
            ->disableStrictHostKeyChecking()
            ->setTimeout(120)
            ->execute($remoteCommand);
    }
}
