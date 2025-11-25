<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ServerDatabaseUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_database_id',
        'is_root',
        'username',
        'password',
        'host',
        'privileges',
        'status',
        'error_log',
        'update_status',
        'update_error_log',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'is_root' => 'boolean',
            'status' => TaskStatus::class,
            'update_status' => TaskStatus::class,
            'password' => 'encrypted',
        ];
    }

    /**
     * Get the database service this user belongs to.
     */
    public function database(): BelongsTo
    {
        return $this->belongsTo(ServerDatabase::class, 'server_database_id');
    }

    /**
     * Get the schemas this user has access to.
     */
    public function schemas(): BelongsToMany
    {
        return $this->belongsToMany(ServerDatabaseSchema::class, 'server_database_user_schema');
    }

    /**
     * Boot the model and dispatch broadcast events.
     */
    protected static function booted(): void
    {
        // Broadcast when user is created
        static::created(function (self $user): void {
            \App\Events\ServerUpdated::dispatch($user->database->server_id);
        });

        // Broadcast when user is updated (status changes, password updates, etc.)
        static::updated(function (self $user): void {
            \App\Events\ServerUpdated::dispatch($user->database->server_id);
        });

        // Store server_id before deletion so we can broadcast after deletion
        static::deleting(function (self $user): void {
            $user->loadMissing('database');
        });

        // Broadcast when user is deleted
        static::deleted(function (self $user): void {
            \App\Events\ServerUpdated::dispatch($user->database->server_id);
        });
    }
}
