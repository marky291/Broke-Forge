<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServerFirewall extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(ServerFirewallRule::class);
    }

    protected static function booted(): void
    {
        static::created(function (self $firewall): void {
            \App\Events\ServerUpdated::dispatch($firewall->server_id);
        });

        static::updated(function (self $firewall): void {
            \App\Events\ServerUpdated::dispatch($firewall->server_id);
        });
    }
}
