<?php

namespace App\Packages\Services\Sites;

use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Base\Milestones;
use App\Packages\Base\PackageInstaller;
use App\Packages\Base\SitePackage;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Site Deploy Key Generator
 *
 * Generates unique SSH deploy keys for individual sites to enable
 * per-site repository access without using server-wide keys.
 */
class SiteDeployKeyGenerator extends PackageInstaller implements SitePackage
{
    private string $publicKey = '';

    /**
     * Generate deploy key for a specific site
     */
    public function execute(ServerSite $site): string
    {
        $keyPath = "/home/brokeforge/.ssh/site_{$site->id}_rsa";
        $keyTitle = "BrokeForge Site - {$site->domain}";

        Log::info("Generating deploy key for site #{$site->id}", [
            'site_id' => $site->id,
            'domain' => $site->domain,
            'key_path' => $keyPath,
        ]);

        // Generate SSH key and store public key for return
        $this->install($this->commands($site, $keyPath, $keyTitle));

        // Update site with deploy key information
        $site->update([
            'has_dedicated_deploy_key' => true,
            'dedicated_deploy_key_title' => $keyTitle,
        ]);

        Log::info("Deploy key generated successfully for site #{$site->id}", [
            'site_id' => $site->id,
            'domain' => $site->domain,
            'public_key_length' => strlen($this->publicKey),
        ]);

        return $this->publicKey;
    }

    /**
     * Generate SSH commands for key generation
     */
    protected function commands(ServerSite $site, string $keyPath, string $keyTitle): array
    {
        return [
            $this->track(SiteDeployKeyGeneratorMilestones::GENERATE_KEY),

            // Generate ed25519 key with no passphrase
            sprintf(
                'ssh-keygen -t ed25519 -f %s -N "" -C %s',
                escapeshellarg($keyPath),
                escapeshellarg($keyTitle)
            ),

            $this->track(SiteDeployKeyGeneratorMilestones::SET_PERMISSIONS),

            // Set proper permissions on keys
            sprintf('chmod 600 %s', escapeshellarg($keyPath)),
            sprintf('chmod 644 %s.pub', escapeshellarg($keyPath)),

            $this->track(SiteDeployKeyGeneratorMilestones::READ_PUBLIC_KEY),

            // Read public key and store for return
            function () use ($keyPath) {
                $remoteCommand = sprintf('cat %s.pub', escapeshellarg($keyPath));

                $process = $this->ssh($this->sshCredential()->user(), $this->server->public_ip, $this->server->ssh_port)
                    ->disableStrictHostKeyChecking()
                    ->setTimeout(30)
                    ->execute($remoteCommand);

                if (! $process->isSuccessful()) {
                    throw new RuntimeException("Failed to read public key: {$process->getErrorOutput()}");
                }

                $this->publicKey = trim($process->getOutput());

                if (empty($this->publicKey)) {
                    throw new RuntimeException('Generated public key is empty');
                }
            },

            $this->track(SiteDeployKeyGeneratorMilestones::COMPLETE),
        ];
    }

    public function packageName(): PackageName
    {
        return PackageName::Git;
    }

    public function packageType(): PackageType
    {
        return PackageType::Git;
    }

    public function milestones(): Milestones
    {
        return new SiteDeployKeyGeneratorMilestones;
    }
}
