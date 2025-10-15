<?php

namespace App\Models;

use App\Enums\PhpStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServerPhp extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'version', // e.g., "8.3", "7.4", "8.2.15"
        'is_cli_default', // Whether this is the default PHP version for CLI
        'is_site_default', // Whether this is the default PHP version for sites
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_cli_default' => 'boolean',
            'is_site_default' => 'boolean',
            'status' => PhpStatus::class,
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(ServerPhpModule::class);
    }

    protected static function booted(): void
    {
        static::created(function (self $php): void {
            \App\Events\ServerUpdated::dispatch($php->server_id);
        });

        static::updated(function (self $php): void {
            // Only broadcast if meaningful fields changed
            $broadcastFields = ['status', 'is_cli_default', 'is_site_default', 'version'];

            if ($php->wasChanged($broadcastFields)) {
                \App\Events\ServerUpdated::dispatch($php->server_id);
            }
        });

        static::deleted(function (self $php): void {
            \App\Events\ServerUpdated::dispatch($php->server_id);
        });
    }
}
