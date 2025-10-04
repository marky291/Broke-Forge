<?php

namespace App\Console\Commands\Scheduler;

use App\Models\ServerScheduledTaskRun;
use Illuminate\Console\Command;

class CleanupOldTaskRuns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scheduler:cleanup
                            {--days= : Number of days to retain task runs (default from config)}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete old scheduled task run records based on retention policy';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $retentionDays = $this->option('days') ?? config('scheduler.retention_days');
        $dryRun = $this->option('dry-run');

        $cutoffDate = now()->subDays($retentionDays);

        $this->info("Cleaning up task runs older than {$retentionDays} days (before {$cutoffDate->toDateTimeString()})");

        // Get count of records to delete
        $count = ServerScheduledTaskRun::where('started_at', '<', $cutoffDate)->count();

        if ($count === 0) {
            $this->info('No task runs to clean up.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("DRY RUN: Would delete {$count} task run record(s)");

            return self::SUCCESS;
        }

        // Delete old task runs
        $deleted = ServerScheduledTaskRun::where('started_at', '<', $cutoffDate)->delete();

        $this->info("Successfully deleted {$deleted} task run record(s)");

        return self::SUCCESS;
    }
}
