<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProvisionEvent extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'server_id',
        'service_type',
        'provision_type',
        'milestone',
        'current_step',
        'total_steps',
        'details',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'details' => 'array',
            'current_step' => 'integer',
            'total_steps' => 'integer',
        ];
    }

    /**
     * Get the server that owns the provision event.
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Calculate the progress percentage of the provision event.
     */
    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_steps === 0) {
            return 0;
        }

        return round(($this->current_step / $this->total_steps) * 100, 2);
    }

    /**
     * Check if this is an installation event.
     */
    public function isInstall(): bool
    {
        return $this->provision_type === 'install';
    }

    /**
     * Check if this is an uninstallation event.
     */
    public function isUninstall(): bool
    {
        return $this->provision_type === 'uninstall';
    }
}
