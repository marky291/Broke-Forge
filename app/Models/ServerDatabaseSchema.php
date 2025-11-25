<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ServerDatabaseSchema extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_database_id',
        'name',
        'character_set',
        'collation',
        'status',
        'error_log',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
        ];
    }

    /**
     * Get the database service this schema belongs to.
     */
    public function database(): BelongsTo
    {
        return $this->belongsTo(ServerDatabase::class, 'server_database_id');
    }

    /**
     * Get the users that have access to this schema.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(ServerDatabaseUser::class, 'server_database_user_schema');
    }

    /**
     * Boot the model and dispatch broadcast events.
     */
    protected static function booted(): void
    {
        // Broadcast when schema is created
        static::created(function (self $schema): void {
            \App\Events\ServerUpdated::dispatch($schema->database->server_id);
        });

        // Broadcast when schema is updated (status changes, etc.)
        static::updated(function (self $schema): void {
            \App\Events\ServerUpdated::dispatch($schema->database->server_id);
        });

        // Store server_id before deletion so we can broadcast after deletion
        static::deleting(function (self $schema): void {
            $schema->loadMissing('database');
        });

        // Broadcast when schema is deleted
        static::deleted(function (self $schema): void {
            \App\Events\ServerUpdated::dispatch($schema->database->server_id);
        });
    }
}
