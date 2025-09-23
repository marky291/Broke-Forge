<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'service_name',
        'service_type',
        'configuration',
        'status',
        'installed_at',
        'uninstalled_at',
    ];

    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'installed_at' => 'datetime',
            'uninstalled_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
