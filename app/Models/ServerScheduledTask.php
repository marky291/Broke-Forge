<?php

namespace App\Models;

use App\Enums\ScheduleFrequency;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServerScheduledTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'name',
        'command',
        'frequency',
        'cron_expression',
        'status',
        'last_run_at',
        'next_run_at',
        'send_notifications',
        'timeout',
    ];

    protected $casts = [
        'frequency' => ScheduleFrequency::class,
        'status' => TaskStatus::class,
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
        'send_notifications' => 'boolean',
        'timeout' => 'integer',
    ];

    /**
     * Get the server that owns this task
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Get all runs for this task
     */
    public function runs(): HasMany
    {
        return $this->hasMany(ServerScheduledTaskRun::class);
    }

    /**
     * Get the latest run
     */
    public function latestRun(): ?ServerScheduledTaskRun
    {
        return $this->runs()->latest('started_at')->first();
    }

    /**
     * Get the cron expression for this task
     */
    public function getCronExpression(): string
    {
        if ($this->frequency === ScheduleFrequency::Custom) {
            return $this->cron_expression ?? '* * * * *';
        }

        return $this->frequency->cronExpression() ?? '* * * * *';
    }

    /**
     * Check if task is active
     */
    public function isActive(): bool
    {
        return $this->status === TaskStatus::Active;
    }

    /**
     * Check if task is paused
     */
    public function isPaused(): bool
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
