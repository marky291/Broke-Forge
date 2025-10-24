<?php

namespace App\Packages\Services\Firewall;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerFirewallRule;
use App\Packages\Taskable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Firewall Rule Uninstallation Job
 *
 * Handles queued firewall rule removal on remote servers.
 */
class FirewallRuleUninstallerJob extends Taskable
{
    public function __construct(
        public Server $server,
        public ServerFirewallRule $serverFirewallRule
    ) {}

    protected function getModel(): Model
    {
        return $this->serverFirewallRule;
    }

    protected function loadModel(): Model
    {
        $rule = $this->serverFirewallRule->fresh();

        if (! $rule) {
            Log::warning('No firewall rule found for removal', ['rule_id' => $this->serverFirewallRule->id]);
            throw new \RuntimeException('Firewall rule not found');
        }

        return $rule;
    }

    protected function getInProgressStatus(): mixed
    {
        return TaskStatus::Removing;
    }

    protected function getSuccessStatus(): mixed
    {
        return TaskStatus::Active; // Not used since we delete
    }

    protected function getFailedStatus(): mixed
    {
        return TaskStatus::Failed;
    }

    protected function shouldDeleteOnSuccess(): bool
    {
        return true;
    }

    protected function executeOperation(Model $model): void
    {
        $uninstaller = new FirewallRuleUninstaller($this->server);

        $ruleConfig = [
            'port' => $model->port,
            'from_ip_address' => $model->from_ip_address,
            'rule_type' => $model->rule_type,
            'name' => $model->name,
        ];

        $uninstaller->execute($ruleConfig);
    }

    protected function getLogContext(Model $model): array
    {
        return [
            'rule_id' => $this->serverFirewallRule->id,
            'server_id' => $this->server->id,
        ];
    }

    protected function getFailedLogContext(\Throwable $exception): array
    {
        return [
            'rule_id' => $this->serverFirewallRule->id,
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ];
    }
}
