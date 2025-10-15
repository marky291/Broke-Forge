<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerSiteCommandHistory extends Model
{
    protected $table = 'server_site_command_history';

    protected $fillable = [
        'server_id',
        'server_site_id',
        'command',
        'output',
        'error_output',
        'exit_code',
        'duration_ms',
        'success',
    ];

    protected $casts = [
        'success' => 'boolean',
        'exit_code' => 'integer',
        'duration_ms' => 'integer',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(ServerSite::class, 'server_site_id');
    }

    /**
     * Boot the model and dispatch broadcast events.
     */
    protected static function booted(): void
    {
        // Broadcast when new command is executed (so history updates in real-time)
        static::created(function (self $commandHistory): void {
            \App\Events\ServerSiteUpdated::dispatch($commandHistory->server_site_id);
        });
    }
}
