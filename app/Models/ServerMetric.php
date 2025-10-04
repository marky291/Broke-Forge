<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'cpu_usage',
        'memory_total_mb',
        'memory_used_mb',
        'memory_usage_percentage',
        'storage_total_gb',
        'storage_used_gb',
        'storage_usage_percentage',
        'collected_at',
    ];

    protected $casts = [
        'cpu_usage' => 'decimal:2',
        'memory_usage_percentage' => 'decimal:2',
        'storage_usage_percentage' => 'decimal:2',
        'collected_at' => 'datetime',
    ];

    /**
     * Get the server that owns the metric
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
