<?php

namespace App\Models;

use App\Packages\Enums\GitStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;

/**
 * Represents a site hosted on a server.
 *
 * This model manages websites deployed on servers, including their
 * configuration, SSL settings, Git integration, and provisioning status.
 */
class ServerSite extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'domain',
        'document_root',
        'php_version',
        'ssl_enabled',
        'ssl_cert_path',
        'ssl_key_path',
        'nginx_config_path',
        'status',
        'health',
        'git_status',
        'configuration',
        'provisioned_at',
        'git_installed_at',
        'last_deployment_sha',
        'last_deployed_at',
        'auto_deploy_enabled',
        'webhook_id',
        'webhook_secret',
        'deprovisioned_at',
    ];

    protected $appends = [
        'provisioned_at_human',
        'last_deployed_at_human',
    ];

    protected function casts(): array
    {
        return [
            'ssl_enabled' => 'boolean',
            'auto_deploy_enabled' => 'boolean',
            'webhook_secret' => 'encrypted',
            'configuration' => 'array',
            'git_status' => GitStatus::class,
            'provisioned_at' => 'datetime',
            'git_installed_at' => 'datetime',
            'last_deployed_at' => 'datetime',
            'deprovisioned_at' => 'datetime',
        ];
    }

    /**
     * Get human-readable provisioned at timestamp.
     */
    public function getProvisionedAtHumanAttribute(): ?string
    {
        return $this->provisioned_at?->diffForHumans();
    }

    /**
     * Get human-readable last deployed at timestamp.
     */
    public function getLastDeployedAtHumanAttribute(): ?string
    {
        return $this->last_deployed_at?->diffForHumans();
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Get all deployments for this site.
     */
    public function deployments(): HasMany
    {
        return $this->hasMany(ServerDeployment::class, 'server_site_id');
    }

    /**
     * Get the latest deployment for this site.
     */
    public function latestDeployment(): HasOne
    {
        return $this->hasOne(ServerDeployment::class, 'server_site_id')->latestOfMany();
    }

    /**
     * Get all command history for this site.
     */
    public function commandHistory(): HasMany
    {
        return $this->hasMany(ServerSiteCommandHistory::class, 'server_site_id');
    }

    /**
     * Get all events for this site.
     */
    public function events(): HasMany
    {
        return $this->hasMany(ServerEvent::class, 'server_site_id');
    }

    /**
     * Check if Git repository can be installed.
     */
    public function canInstallGitRepository(): bool
    {
        return ! $this->git_status || $this->git_status->canRetry();
    }

    /**
     * Check if Git repository is currently being processed.
     */
    public function isGitProcessing(): bool
    {
        return $this->git_status?->isProcessing() ?? false;
    }

    /**
     * Get Git repository configuration.
     */
    public function getGitConfiguration(): array
    {
        $config = $this->configuration['git_repository'] ?? [];

        return [
            'provider' => $config['provider'] ?? null,
            'repository' => $config['repository'] ?? null,
            'branch' => $config['branch'] ?? null,
            'deploy_key' => $config['deploy_key'] ?? $config['deployKey'] ?? null,
        ];
    }

    /**
     * Get deployment script from configuration.
     */
    public function getDeploymentScript(): string
    {
        return $this->configuration['deployment']['script'] ?? 'git fetch && git pull';
    }

    /**
     * Update deployment script in configuration.
     */
    public function updateDeploymentScript(string $script): void
    {
        $config = $this->configuration ?? [];
        $config['deployment']['script'] = $script;
        $this->update(['configuration' => $config]);
    }

    /**
     * Check if site has Git repository installed.
     */
    public function hasGitRepository(): bool
    {
        return $this->git_status === GitStatus::Installed;
    }

    protected static function booted(): void
    {
        static::created(function (self $site): void {
            activity()
                ->causedBy(Auth::user())
                ->performedOn($site)
                ->event('site.created')
                ->withProperties([
                    'domain' => $site->domain,
                    'server_id' => $site->server_id,
                    'php_version' => $site->php_version,
                    'ssl_enabled' => $site->ssl_enabled,
                ])
                ->log(sprintf('Site %s created on server %s', $site->domain, $site->server->vanity_name ?? $site->server->public_ip));
        });

        static::updated(function (self $site): void {
            if ($site->wasChanged('status')) {
                activity()
                    ->causedBy(Auth::user())
                    ->performedOn($site)
                    ->event('site.status_changed')
                    ->withProperties([
                        'domain' => $site->domain,
                        'old_status' => $site->getOriginal('status'),
                        'new_status' => $site->status,
                    ])
                    ->log(sprintf('Site %s status changed to %s', $site->domain, $site->status));
            }
        });

        static::deleted(function (self $site): void {
            activity()
                ->causedBy(Auth::user())
                ->performedOn($site)
                ->event('site.deleted')
                ->withProperties([
                    'domain' => $site->domain,
                    'server_id' => $site->server_id,
                ])
                ->log(sprintf('Site %s deleted', $site->domain));
        });
    }
}
