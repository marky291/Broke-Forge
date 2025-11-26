<?php

namespace App\Packages\Services\Sites;

use App\Models\ServerSite;
use Spatie\Ssh\Ssh;

/**
 * Service for reading environment files from remote servers.
 *
 * Reads .env or framework-specific configuration files via SSH.
 * Never stores file content in database - always reads on-demand.
 */
class SiteEnvironmentReader
{
    public function __construct(
        protected ServerSite $site
    ) {}

    /**
     * Read environment file content from remote server.
     *
     * @return string The environment file content, or empty string if file doesn't exist
     *
     * @throws \RuntimeException if SSH connection or read fails
     */
    public function execute(): string
    {
        $envPath = $this->getEnvFilePath();

        if (! $envPath) {
            throw new \RuntimeException('Framework does not support environment file editing');
        }

        // Use cat with 2>/dev/null to suppress error if file doesn't exist
        // Return empty string if file doesn't exist
        $command = sprintf('cat %s 2>/dev/null || echo ""', escapeshellarg($envPath));

        $process = $this->makeSsh()
            ->setTimeout(30)
            ->execute($command);

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Failed to read environment file: '.$process->getErrorOutput());
        }

        return $process->getOutput();
    }

    /**
     * Get the full path to the environment file.
     *
     * The .env file lives in the shared directory for symlink-based deployments.
     * Path: /home/brokeforge/deployments/{domain}/shared/.env
     */
    protected function getEnvFilePath(): ?string
    {
        $framework = $this->site->siteFramework;

        if (! $framework || ! $framework->supportsEnv()) {
            return null;
        }

        $siteRoot = $this->site->getSiteRoot();
        $envFile = $framework->getEnvFilePath();

        return $siteRoot.'/shared/'.$envFile;
    }

    /**
     * Create SSH connection to server.
     */
    protected function makeSsh(): Ssh
    {
        return $this->site->server->ssh('brokeforge');
    }
}
