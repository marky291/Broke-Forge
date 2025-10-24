<?php

namespace App\Packages\Services\Firewall;

use App\Enums\FirewallRuleStatus;
use App\Models\Server;
use App\Models\ServerFirewallRule;
use App\Packages\Taskable;
use Illuminate\Database\Eloquent\Model;

/**
 * Firewall Rule Installation Job
 *
 * Handles queued firewall rule configuration on remote servers with real-time status updates.
 * Each job instance handles ONE firewall rule only.
 * For multiple rules, dispatch multiple job instances.
 */
class FirewallRuleInstallerJob extends Taskable
{
    /**
     * @param  Server  $server  The server to configure
     * @param  ServerFirewallRule  $serverFirewallRule  The firewall rule to install
     */
    public function __construct(
        public Server $server,
        public ServerFirewallRule $serverFirewallRule
    ) {}

    protected function getModel(): Model
    {
        return $this->serverFirewallRule;
    }

    protected function getInProgressStatus(): mixed
    {
        return FirewallRuleStatus::Installing;
    }

    protected function getSuccessStatus(): mixed
    {
        return FirewallRuleStatus::Active;
    }

    protected function getFailedStatus(): mixed
    {
        return FirewallRuleStatus::Failed;
    }

    protected function executeOperation(Model $model): void
    {
        $installer = new FirewallRuleInstaller($this->server);

        $singleRule = [
            'port' => $model->port,
            'protocol' => 'tcp', // Default to TCP for MVP
            'action' => $model->rule_type ?? 'allow',
            'source' => $model->from_ip_address ?? null,
            'comment' => $model->name,
        ];

        $installer->execute([$singleRule]);
    }

    protected function getLogContext(Model $model): array
    {
        return [
            'rule_id' => $model->id,
            'server_id' => $this->server->id,
            'rule_name' => $model->name,
            'port' => $model->port,
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
