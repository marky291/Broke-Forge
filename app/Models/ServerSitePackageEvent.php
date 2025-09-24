<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $provision_type
 * @property int $current_step
 * @property int $total_steps
 * @property string $progressPercentage
 * @property string $status
 * @property string|null $error_log
 */
class ServerSitePackageEvent extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'server_id',
        'site_id',
        'service_type',
        'provision_type',
        'milestone',
        'current_step',
        'total_steps',
        'details',
        'status',
        'error_log',
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
     * Get the site that owns the provision event.
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(ServerSite::class, 'site_id');
    }

    /**
     * Calculate the progress percentage of the server site package event.
     */
    public function getProgressPercentageAttribute(): string
    {
        if ($this->total_steps == 0) {
            return "0";
        }

        return str(($this->current_step / $this->total_steps) * 100);
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

    /**
     * Check if the event is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the event was successful.
     */
    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if the event failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}