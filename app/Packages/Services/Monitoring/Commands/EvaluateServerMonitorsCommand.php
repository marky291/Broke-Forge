<?php

namespace App\Packages\Services\Monitoring\Commands;

use App\Models\ServerMetric;
use App\Models\ServerMonitor;
use App\Packages\Services\Monitoring\Events\MonitorRecoveredEvent;
use App\Packages\Services\Monitoring\Events\MonitorTriggeredEvent;
use Illuminate\Console\Command;

class EvaluateServerMonitorsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitors:evaluate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Evaluate server monitors and trigger/recover alerts based on thresholds';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $monitors = ServerMonitor::query()
            ->with('server')
            ->where('enabled', true)
            ->get();

        $this->info("Evaluating {$monitors->count()} enabled monitor(s)...");

        $triggered = 0;
        $recovered = 0;

        foreach ($monitors as $monitor) {
            if ($this->shouldEvaluate($monitor)) {
                $currentValue = $this->getCurrentMetricValue($monitor);

                if ($currentValue === null) {
                    continue;
                }

                $conditionMet = $this->isConditionMet($monitor, $currentValue);

                if ($conditionMet && $monitor->status === 'normal') {
                    $this->triggerMonitor($monitor, $currentValue);
                    $triggered++;
                } elseif (! $conditionMet && $monitor->status === 'triggered') {
                    $this->recoverMonitor($monitor, $currentValue);
                    $recovered++;
                }
            }
        }

        $this->info("Evaluation complete. Triggered: {$triggered}, Recovered: {$recovered}");

        return Command::SUCCESS;
    }

    protected function shouldEvaluate(ServerMonitor $monitor): bool
    {
        if ($monitor->status === 'normal') {
            return true;
        }

        if ($monitor->last_triggered_at === null) {
            return true;
        }

        $cooldownEnds = $monitor->last_triggered_at->addMinutes($monitor->cooldown_minutes);

        return now()->greaterThanOrEqualTo($cooldownEnds);
    }

    protected function getCurrentMetricValue(ServerMonitor $monitor): ?float
    {
        $since = now()->subMinutes($monitor->duration_minutes);

        $metrics = ServerMetric::query()
            ->where('server_id', $monitor->server_id)
            ->where('collected_at', '>=', $since)
            ->orderBy('collected_at', 'desc')
            ->get();

        if ($metrics->isEmpty()) {
            return null;
        }

        $column = match ($monitor->metric_type) {
            'cpu' => 'cpu_usage',
            'memory' => 'memory_usage_percentage',
            'storage' => 'storage_usage_percentage',
        };

        return $metrics->first()->{$column};
    }

    protected function isConditionMet(ServerMonitor $monitor, float $currentValue): bool
    {
        $since = now()->subMinutes($monitor->duration_minutes);

        $metrics = ServerMetric::query()
            ->where('server_id', $monitor->server_id)
            ->where('collected_at', '>=', $since)
            ->orderBy('collected_at', 'desc')
            ->get();

        if ($metrics->isEmpty()) {
            return false;
        }

        $column = match ($monitor->metric_type) {
            'cpu' => 'cpu_usage',
            'memory' => 'memory_usage_percentage',
            'storage' => 'storage_usage_percentage',
        };

        return $metrics->every(function ($metric) use ($monitor, $column) {
            $value = (float) $metric->{$column};

            return match ($monitor->operator) {
                '>' => $value > $monitor->threshold,
                '<' => $value < $monitor->threshold,
                '>=' => $value >= $monitor->threshold,
                '<=' => $value <= $monitor->threshold,
                '==' => abs($value - $monitor->threshold) < 0.01,
                default => false,
            };
        });
    }

    protected function triggerMonitor(ServerMonitor $monitor, float $currentValue): void
    {
        $monitor->update([
            'status' => 'triggered',
            'last_triggered_at' => now(),
        ]);

        MonitorTriggeredEvent::dispatch($monitor, $monitor->server, $currentValue);

        $this->warn("Monitor triggered: {$monitor->name} (Server: {$monitor->server->vanity_name})");
    }

    protected function recoverMonitor(ServerMonitor $monitor, float $currentValue): void
    {
        $monitor->update([
            'status' => 'normal',
            'last_recovered_at' => now(),
        ]);

        MonitorRecoveredEvent::dispatch($monitor, $monitor->server, $currentValue);

        $this->info("Monitor recovered: {$monitor->name} (Server: {$monitor->server->vanity_name})");
    }
}
