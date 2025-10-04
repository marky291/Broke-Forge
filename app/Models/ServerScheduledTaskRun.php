<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerScheduledTaskRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'server_scheduled_task_id',
        'started_at',
        'completed_at',
        'exit_code',
        'output',
        'error_output',
        'duration_ms',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'exit_code' => 'integer',
        'duration_ms' => 'integer',
    ];

    /**
     * Attributes to append to the model's array form
     */
    protected $appends = [
        'was_successful',
    ];

    /**
     * Get the server for this run
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Get the task for this run
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(ServerScheduledTask::class, 'server_scheduled_task_id');
    }

    /**
     * Get the was_successful attribute
     */
    protected function wasSuccessful(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => isset($attributes['exit_code']) && $attributes['exit_code'] === 0,
        );
    }

    /**
     * Check if the run failed
     */
    public function failed(): bool
    {
        return $this->exit_code !== 0;
    }
}
