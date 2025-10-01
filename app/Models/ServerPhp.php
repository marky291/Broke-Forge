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
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_cli_default' => 'boolean',
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
}
