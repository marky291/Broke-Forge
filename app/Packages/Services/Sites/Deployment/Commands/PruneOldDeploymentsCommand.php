<?php

namespace App\Packages\Services\Sites\Deployment\Commands;

use App\Models\ServerSite;
use Illuminate\Console\Command;

class PruneOldDeploymentsCommand extends Command
{
    protected $signature = 'deployments:prune
                          {--site-id= : Prune deployments for specific site}
                          {--keep=14 : Number of deployments to keep per site}
                          {--dry-run : Show what would be deleted without deleting}';

    protected $description = 'Prune old deployment directories, keeping last N deployments per site';

    public function handle(): int
    {
        $keep = (int) $this->option('keep');
        $dryRun = $this->option('dry-run');
        $siteId = $this->option('site-id');

        $this->info("Pruning old deployments (keeping last {$keep} per site)");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No files will be deleted');
        }

        $query = ServerSite::whereNotNull('active_deployment_id');

        if ($siteId) {
            $query->where('id', $siteId);
        }

        $sites = $query->get();

        if ($sites->isEmpty()) {
            $this->info('No sites with active deployments found.');

            return Command::SUCCESS;
        }

        $totalDeleted = 0;

        foreach ($sites as $site) {
            $deleted = $this->pruneSiteDeployments($site, $keep, $dryRun);
            $totalDeleted += $deleted;
        }

        if ($dryRun) {
            $this->info("DRY RUN: Would delete {$totalDeleted} deployment director(ies)");
        } else {
            $this->info("Successfully deleted {$totalDeleted} deployment director(ies)");
        }

        return Command::SUCCESS;
    }

    protected function pruneSiteDeployments(ServerSite $site, int $keep, bool $dryRun): int
    {
        // Get all successful deployments with paths, ordered by created_at DESC
        $deployments = $site->deployments()
            ->where('status', 'success')
            ->whereNotNull('deployment_path')
            ->orderByDesc('created_at')
            ->get();

        if ($deployments->count() <= $keep) {
            $this->line("  {$site->domain}: No deployments to prune ({$deployments->count()} total)");

            return 0;
        }

        // Skip the first N deployments (keep them), get the rest to delete
        $deploymentsToDelete = $deployments->skip($keep);

        $this->line("  {$site->domain}: Pruning {$deploymentsToDelete->count()} deployment(s)");

        $deleted = 0;

        foreach ($deploymentsToDelete as $deployment) {
            if ($dryRun) {
                $this->line("    [DRY RUN] Would delete: {$deployment->deployment_path}");
                $deleted++;

                continue;
            }

            // Delete deployment directory from remote server
            $remoteCommand = sprintf(
                'rm -rf %s',
                escapeshellarg($deployment->deployment_path)
            );

            try {
                $process = $site->server->ssh('brokeforge')->execute($remoteCommand);

                if ($process->isSuccessful()) {
                    $this->line("    Deleted: {$deployment->deployment_path}");

                    // Update deployment record to mark path as deleted
                    $deployment->update(['deployment_path' => null]);
                    $deleted++;
                } else {
                    $this->error("    Failed to delete: {$deployment->deployment_path}");
                }
            } catch (\Exception $e) {
                $this->error("    Error deleting {$deployment->deployment_path}: {$e->getMessage()}");
            }
        }

        return $deleted;
    }
}
