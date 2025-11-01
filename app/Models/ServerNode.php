<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerNode extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'version', // e.g., "22", "20", "18", "16"
        'is_default', // Whether this is the default Node.js version
        'status',
        'error_log',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'status' => TaskStatus::class,
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    protected static function booted(): void
    {
        static::created(function (self $node): void {
            \App\Events\ServerUpdated::dispatch($node->server_id);
        });

        static::updated(function (self $node): void {
            // Only broadcast if meaningful fields changed
            $broadcastFields = ['status', 'is_default', 'version'];

            if ($node->wasChanged($broadcastFields)) {
                \App\Events\ServerUpdated::dispatch($node->server_id);
            }
        });

        static::deleted(function (self $node): void {
            \App\Events\ServerUpdated::dispatch($node->server_id);
        });
    }
}
