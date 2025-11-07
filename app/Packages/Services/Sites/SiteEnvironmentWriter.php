<?php

namespace App\Packages\Services\Sites;

use App\Models\ServerSite;
use Spatie\Ssh\Ssh;

/**
 * Service for writing environment files to remote servers.
 *
 * Writes .env or framework-specific configuration files via SSH.
 * Never stores file content in database - always writes directly to remote.
 */
class SiteEnvironmentWriter
{
    public function __construct(
        protected ServerSite $site
    ) {}

    /**
     * Write environment file content to remote server.
     *
     * @param  string  $content  The environment file content to write
     *
     * @throws \RuntimeException if SSH connection or write fails
     */
    public function execute(string $content): void
    {
        $envPath = $this->getEnvFilePath();

        if (! $envPath) {
            throw new \RuntimeException('Framework does not support environment file editing');
        }

        // Use base64 encoding to safely transport content with special characters
        $encodedContent = base64_encode($content);

        $command = sprintf(
            'echo %s | base64 -d > %s',
            escapeshellarg($encodedContent),
            escapeshellarg($envPath)
        );

        $process = $this->makeSsh()
            ->setTimeout(30)
            ->execute($command);

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Failed to write environment file: '.$process->getErrorOutput());
        }
    }

    /**
     * Get the full path to the environment file.
     */
    protected function getEnvFilePath(): ?string
    {
        $framework = $this->site->siteFramework;

        if (! $framework || ! $framework->supportsEnv()) {
            return null;
        }

        $siteRoot = $this->site->getSiteRoot();
        $envFile = $framework->getEnvFilePath();

        return $siteRoot.'/'.$envFile;
    }

    /**
     * Create SSH connection to server.
     */
    protected function makeSsh(): Ssh
    {
        return $this->site->server->ssh('brokeforge');
    }
}
