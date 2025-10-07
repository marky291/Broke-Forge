<?php

namespace App\Console\Commands;

use App\Models\ServerSite;
use App\Packages\Enums\GitStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupStuckGitInstallations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'git:cleanup-stuck';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark Git installations stuck for more than 15 minutes as failed';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $timeoutMinutes = 15;
        $cutoffTime = now()->subMinutes($timeoutMinutes);

        // Find sites with git_status = 'installing' that were updated more than 15 minutes ago
        $stuckSites = ServerSite::where('git_status', GitStatus::Installing)
            ->where('updated_at', '<', $cutoffTime)
            ->get();

        if ($stuckSites->isEmpty()) {
            $this->info('No stuck Git installations found.');

            return self::SUCCESS;
        }

        $this->info("Found {$stuckSites->count()} stuck Git installation(s).");

        foreach ($stuckSites as $site) {
            $site->update(['git_status' => GitStatus::Failed]);

            $this->warn("Marked site #{$site->id} ({$site->domain}) as failed (stuck for more than {$timeoutMinutes} minutes).");

            Log::warning('Automatically marked stuck Git installation as failed', [
                'site_id' => $site->id,
                'domain' => $site->domain,
                'stuck_duration_minutes' => now()->diffInMinutes($site->updated_at),
            ]);
        }

        $this->info("Cleanup complete. {$stuckSites->count()} installation(s) marked as failed.");

        return self::SUCCESS;
    }
}
