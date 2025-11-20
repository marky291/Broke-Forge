<?php

namespace App\Console\Commands;

use App\Models\ServerSite;
use App\Packages\Credential\Contracts\SshFactory;
use App\Packages\Services\Sites\Framework\WordPress\WordPressConfigGenerator;
use Illuminate\Console\Command;

class FixWordPressConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wordpress:fix-config {site-id : The ID of the WordPress site to fix}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix wp-config.php for existing WordPress installations to use correct MySQL user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $siteId = $this->argument('site-id');
        $site = ServerSite::find($siteId);

        if (! $site) {
            $this->error("Site with ID {$siteId} not found.");

            return 1;
        }

        if ($site->siteFramework->slug !== 'wordpress') {
            $this->error('This command only works with WordPress sites.');

            return 1;
        }

        $this->info("Fixing wp-config.php for site: {$site->domain}");

        // Generate correct wp-config.php
        $generator = new WordPressConfigGenerator;
        $wpConfigContent = $generator->generate($site);
        $wpConfigContent = str_replace('$', '\$', $wpConfigContent);

        // Get deployment path
        $deploymentPath = $this->getDeploymentPath($site);

        if (! $deploymentPath) {
            $this->error('Could not determine WordPress installation path.');

            return 1;
        }

        $this->info("Deployment path: {$deploymentPath}");

        // Upload new wp-config.php
        $command = "cat > {$deploymentPath}/wp-config.php << 'WP_CONFIG_EOF'\n{$wpConfigContent}\nWP_CONFIG_EOF";

        try {
            $ssh = app()->make('ssh', [
                'host' => $site->server->public_ip,
                'username' => 'root',
                'password' => $site->server->ssh_root_password,
                'port' => $site->server->ssh_port,
            ]);

            $result = $ssh->exec([$command]);

            $this->info('✅ wp-config.php has been updated successfully!');
            $this->newLine();
            $this->info('You can now visit http://'.$site->server->public_ip.'/wp-admin/install.php to complete WordPress setup.');

            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to update wp-config.php: '.$e->getMessage());

            return 1;
        }
    }

    /**
     * Get the deployment path for a WordPress site.
     */
    protected function getDeploymentPath(ServerSite $site): ?string
    {
        try {
            $ssh = app()->make('ssh', [
                'host' => $site->server->public_ip,
                'username' => 'root',
                'password' => $site->server->ssh_root_password,
                'port' => $site->server->ssh_port,
            ]);

            $result = $ssh->exec(['readlink -f /home/brokeforge/'.$site->domain]);

            return trim($result[0] ?? '');
        } catch (\Exception $e) {
            return null;
        }
    }
}
