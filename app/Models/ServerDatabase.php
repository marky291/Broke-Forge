<?php

namespace App\Models;

use App\Enums\DatabaseEngine;
use App\Enums\StorageType;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServerDatabase extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'name',
        'engine',
        'storage_type',
        'version',
        'port',
        'status',
        'root_password',
        'error_log',
    ];

    protected $hidden = [
        'root_password',
    ];

    protected function casts(): array
    {
        return [
            'engine' => DatabaseEngine::class,
            'storage_type' => StorageType::class,
            'status' => TaskStatus::class,
            'port' => 'integer',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(ServerSite::class, 'database_id');
    }

    public function schemas(): HasMany
    {
        return $this->hasMany(ServerDatabaseSchema::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(ServerDatabaseUser::class);
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
