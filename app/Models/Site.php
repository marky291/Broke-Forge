<?php

namespace App\Models;

use App\Enums\GitStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class Site extends Model
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
        'git_status',
        'configuration',
        'provisioned_at',
        'git_installed_at',
        'last_deployment_sha',
        'last_deployed_at',
        'deprovisioned_at',
    ];

    protected function casts(): array
    {
        return [
            'ssl_enabled' => 'boolean',
            'configuration' => 'array',
            'git_status' => GitStatus::class,
            'provisioned_at' => 'datetime',
            'git_installed_at' => 'datetime',
            'last_deployed_at' => 'datetime',
            'deprovisioned_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
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

    protected static function booted(): void
    {
        static::created(function (self $site): void {
            Activity::create([
                'type' => 'site.created',
                'description' => sprintf('Site %s created on server %s', $site->domain, $site->server->vanity_name ?? $site->server->public_ip),
                'causer_id' => Auth::id(),
                'subject_type' => self::class,
                'subject_id' => $site->id,
                'properties' => [
                    'domain' => $site->domain,
                    'server_id' => $site->server_id,
                    'php_version' => $site->php_version,
                    'ssl_enabled' => $site->ssl_enabled,
                ],
            ]);
        });

        static::updated(function (self $site): void {
            if ($site->wasChanged('status')) {
                Activity::create([
                    'type' => 'site.status_changed',
                    'description' => sprintf('Site %s status changed to %s', $site->domain, $site->status),
                    'causer_id' => Auth::id(),
                    'subject_type' => self::class,
                    'subject_id' => $site->id,
                    'properties' => [
                        'domain' => $site->domain,
                        'old_status' => $site->getOriginal('status'),
                        'new_status' => $site->status,
                    ],
                ]);
            }
        });

        static::deleted(function (self $site): void {
            Activity::create([
                'type' => 'site.deleted',
                'description' => sprintf('Site %s deleted', $site->domain),
                'causer_id' => Auth::id(),
                'subject_type' => self::class,
                'subject_id' => $site->id,
                'properties' => [
                    'domain' => $site->domain,
                    'server_id' => $site->server_id,
                ],
            ]);
        });
    }
}
