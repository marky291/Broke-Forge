<?php

namespace App\Models;

use App\Enums\TaskStatus;
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

    /**
     * The relationships that should always be loaded.
     */
    protected $with = ['siteFramework'];

    protected $fillable = [
        'server_id',
        'available_framework_id',
        'database_id',
        'node_id',
        'domain',
        'document_root',
        'php_version',
        'ssl_enabled',
        'ssl_cert_path',
        'ssl_key_path',
        'nginx_config_path',
        'status',
        'is_default',
        'default_site_status',
        'health',
        'git_status',
        'configuration',
        'installed_at',
        'git_installed_at',
        'last_deployment_sha',
        'last_deployed_at',
        'active_deployment_id',
        'auto_deploy_enabled',
        'webhook_id',
        'webhook_secret',
        'uninstalled_at',
        'error_log',
        'has_dedicated_deploy_key',
        'dedicated_deploy_key_id',
        'dedicated_deploy_key_title',
    ];

    protected $appends = [
        'installed_at_human',
        'last_deployed_at_human',
    ];

    protected function casts(): array
    {
        return [
            'ssl_enabled' => 'boolean',
            'is_default' => 'boolean',
            'auto_deploy_enabled' => 'boolean',
            'has_dedicated_deploy_key' => 'boolean',
            'webhook_secret' => 'encrypted',
            'configuration' => 'array',
            'git_status' => TaskStatus::class,
            'default_site_status' => TaskStatus::class,
            'installed_at' => 'datetime',
            'git_installed_at' => 'datetime',
            'last_deployed_at' => 'datetime',
            'uninstalled_at' => 'datetime',
        ];
    }

    /**
     * Get human-readable installed at timestamp.
     */
    public function getInstalledAtHumanAttribute(): ?string
    {
        return $this->installed_at?->diffForHumans();
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
     * Get the framework for this site.
     */
    public function siteFramework(): BelongsTo
    {
        return $this->belongsTo(AvailableFramework::class, 'available_framework_id');
    }

    /**
     * Get the database for this site.
     */
    public function database(): BelongsTo
    {
        return $this->belongsTo(ServerDatabase::class, 'database_id');
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
     * Get the currently active deployment.
     */
    public function activeDeployment(): BelongsTo
    {
        return $this->belongsTo(ServerDeployment::class, 'active_deployment_id');
    }

    /**
     * Get all command history for this site.
     */
    public function commandHistory(): HasMany
    {
        return $this->hasMany(ServerSiteCommandHistory::class, 'server_site_id');
    }

    /**
     * Check if Git repository can be installed.
     */
    public function canInstallGitRepository(): bool
    {
        return ! $this->git_status || $this->git_status === TaskStatus::Failed;
    }

    /**
     * Check if Git repository is currently being processed.
     */
    public function isGitProcessing(): bool
    {
        return $this->git_status && in_array($this->git_status, [TaskStatus::Installing, TaskStatus::Updating], true);
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
        return $this->configuration['deployment']['script'] ?? "git pull\ncomposer install --no-dev --no-interaction --prefer-dist --optimize-autoloader";
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
        return $this->git_status === TaskStatus::Success;
    }

    /**
     * Check if the site name is a real domain (contains a dot).
     *
     * This is used to determine if features like SSL certificates
     * should be available, as they require actual domain names.
     */
    public function isDomain(): bool
    {
        return str_contains($this->domain, '.');
    }

    /**
     * Get the site root directory (where deployments are stored).
     */
    public function getSiteRoot(): string
    {
        return "/home/brokeforge/deployments/{$this->domain}";
    }

    /**
     * Get the site symlink path (what nginx points to).
     */
    public function getSiteSymlink(): string
    {
        return "/home/brokeforge/{$this->domain}";
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

            // Only broadcast if meaningful fields changed
            $broadcastFields = [
                'domain',
                'status',
                'is_default',
                'default_site_status',
                'health',
                'git_status',
                'ssl_enabled',
                'auto_deploy_enabled',
                'last_deployed_at',
            ];

            if ($site->wasChanged($broadcastFields)) {
                \App\Events\ServerSiteUpdated::dispatch($site->id);
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
