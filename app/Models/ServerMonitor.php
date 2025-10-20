<?php

namespace App\Models;

use App\Events\ServerUpdated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerMonitor extends Model
{
    /** @use HasFactory<\Database\Factories\ServerMonitorFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'server_id',
        'name',
        'metric_type',
        'operator',
        'threshold',
        'duration_minutes',
        'notification_emails',
        'enabled',
        'cooldown_minutes',
        'last_triggered_at',
        'last_recovered_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'notification_emails' => 'array',
            'threshold' => 'decimal:2',
            'enabled' => 'boolean',
            'last_triggered_at' => 'datetime',
            'last_recovered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    protected static function booted(): void
    {
        static::created(function (self $monitor): void {
            ServerUpdated::dispatch($monitor->server_id);
        });

        static::updated(function (self $monitor): void {
            ServerUpdated::dispatch($monitor->server_id);
        });

        static::deleted(function (self $monitor): void {
            ServerUpdated::dispatch($monitor->server_id);
        });
    }
}
