<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a deployment execution for a server site.
 *
 * Tracks deployment history, output, and status for Git-enabled sites.
 */
class ServerDeployment extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'server_site_id',
        'status',
        'deployment_script',
        'triggered_by',
        'output',
        'error_output',
        'exit_code',
        'commit_sha',
        'branch',
        'duration_ms',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'exit_code' => 'integer',
            'duration_ms' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Boot the model and dispatch broadcast events.
     */
    protected static function booted(): void
    {
        // Broadcast when new deployment is created (so deployments update in real-time)
        static::created(function (self $deployment): void {
            \App\Events\ServerSiteUpdated::dispatch($deployment->server_site_id);
        });

        // Broadcast when deployment status or output changes (for real-time progress)
        static::updated(function (self $deployment): void {
            \App\Events\ServerSiteUpdated::dispatch($deployment->server_site_id);
        });
    }

    /**
     * Get the server that owns the deployment.
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Get the site that this deployment belongs to.
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(ServerSite::class, 'server_site_id');
    }

    /**
     * Check if the deployment is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the deployment is currently running.
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Check if the deployment was successful.
     */
    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if the deployment failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Get deployment duration in seconds.
     */
    public function getDurationSeconds(): ?float
    {
        if ($this->duration_ms === null) {
            return null;
        }

        return round($this->duration_ms / 1000, 2);
    }
}
