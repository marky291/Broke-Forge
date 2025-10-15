<?php

namespace App\Models;

use App\Enums\DatabaseStatus;
use App\Enums\DatabaseType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerDatabase extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'name',
        'type',
        'version',
        'port',
        'status',
        'root_password',
        'error_message',
    ];

    protected $hidden = [
        'root_password',
    ];

    protected function casts(): array
    {
        return [
            'type' => DatabaseType::class,
            'status' => DatabaseStatus::class,
            'port' => 'integer',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Boot the model and dispatch broadcast events.
     */
    protected static function booted(): void
    {
        // Broadcast when database is created
        static::created(function (self $database): void {
            \App\Events\ServerUpdated::dispatch($database->server_id);
        });

        // Broadcast when database is updated (status changes, version updates, etc.)
        static::updated(function (self $database): void {
            \App\Events\ServerUpdated::dispatch($database->server_id);
        });

        // Broadcast when database is deleted
        static::deleted(function (self $database): void {
            \App\Events\ServerUpdated::dispatch($database->server_id);
        });
    }
}
