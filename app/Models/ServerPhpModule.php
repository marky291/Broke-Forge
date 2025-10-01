<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerPhpModule extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_php_id',
        'name', // e.g., "gd", "mbstring", "curl", "xml"
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    public function php(): BelongsTo
    {
        return $this->belongsTo(ServerPhp::class, 'server_php_id');
    }
}
