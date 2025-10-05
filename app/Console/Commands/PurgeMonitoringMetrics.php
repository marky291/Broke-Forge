<?php

namespace App\Console\Commands;

use App\Models\ServerMetric;
use Illuminate\Console\Command;

class PurgeMonitoringMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitoring:purge {--days= : Number of days to retain metrics}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete old server metrics to prevent database bloat';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('monitoring.retention_days'));

        $this->info("Purging metrics older than {$days} days...");

        $deletedCount = ServerMetric::where('collected_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Deleted {$deletedCount} old metric records.");

        return self::SUCCESS;
    }
}
