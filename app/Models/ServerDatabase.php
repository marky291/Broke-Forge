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
}
