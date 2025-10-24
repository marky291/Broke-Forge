<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerSupervisorTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'name',
        'command',
        'working_directory',
        'processes',
        'user',
        'auto_restart',
        'autorestart_unexpected',
        'status',
        'error_log',
        'stdout_logfile',
        'stderr_logfile',
        'installed_at',
        'uninstalled_at',
    ];

    protected $casts = [
        'status' => TaskStatus::class,
        'processes' => 'integer',
        'auto_restart' => 'boolean',
        'autorestart_unexpected' => 'boolean',
        'installed_at' => 'datetime',
        'uninstalled_at' => 'datetime',
    ];

    /**
     * Get the server that owns this task
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Check if task is active
     */
    public function isActive(): bool
    {
        return $this->status === TaskStatus::Active;
    }

    /**
     * Check if task is inactive
     */
    public function isInactive(): bool
    {
        return $this->status === TaskStatus::Paused;
    }

    /**
     * Check if task has failed
     */
    public function isFailed(): bool
    {
        return $this->status === TaskStatus::Failed;
    }

    /**
     * Boot the model and dispatch broadcast events.
     */
    protected static function booted(): void
    {
        // Broadcast when task is created
        static::created(function (self $task): void {
            \App\Events\ServerUpdated::dispatch($task->server_id);
        });

        // Broadcast when task is updated
        static::updated(function (self $task): void {
            \App\Events\ServerUpdated::dispatch($task->server_id);
        });

        // Broadcast when task is deleted
        static::deleted(function (self $task): void {
            \App\Events\ServerUpdated::dispatch($task->server_id);
        });
    }
}
