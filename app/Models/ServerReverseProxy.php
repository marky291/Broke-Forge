<?php

namespace App\Models;

use App\Enums\ReverseProxyType;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerReverseProxy extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'type',
        'version',
        'worker_processes',
        'worker_connections',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => ReverseProxyType::class,
            'status' => TaskStatus::class,
            'worker_connections' => 'integer',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
