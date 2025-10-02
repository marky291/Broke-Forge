<?php

namespace App\Packages\Services\Credential;

/**
 * Temporary SSH Key File
 *
 * Value object for managing temporary SSH private key files.
 * Automatically cleans up the file when destroyed, preventing orphaned temp files.
 */
class TempKeyFile
{
    private string $path;

    private bool $deleted = false;

    /**
     * Create a temporary key file with the given content.
     *
     * @param  string  $content  The private key content
     * @param  int  $serverId  The server ID for unique naming
     * @param  string  $credentialType  The credential type for unique naming
     *
     * @throws \RuntimeException If file cannot be created
     */
    public function __construct(string $content, int $serverId, string $credentialType)
    {
        $tempDir = sys_get_temp_dir();
        $this->path = sprintf(
            '%s/ssh_key_%d_%s_%s',
            $tempDir,
            $serverId,
            $credentialType,
            uniqid()
        );

        if (file_put_contents($this->path, $content) === false) {
            throw new \RuntimeException("Failed to write private key to temporary file: {$this->path}");
        }

        chmod($this->path, 0600);
    }

    /**
     * Get the path to the temporary key file.
     *
     * @return string The absolute file path
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Manually delete the temporary file.
     * Called automatically on object destruction.
     */
    public function delete(): void
    {
        if (! $this->deleted && file_exists($this->path)) {
            @unlink($this->path);
            $this->deleted = true;
        }
    }

    /**
     * Automatically clean up the temporary file when object is destroyed.
     */
    public function __destruct()
    {
        $this->delete();
    }
}
