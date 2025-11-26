<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents an available PHP version that can be installed on servers.
 *
 * This model defines available PHP versions (7.4, 8.0, 8.1, etc.)
 * with their deprecation status and end-of-life dates.
 */
class AvailablePhpVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'version',
        'display_name',
        'is_default',
        'is_deprecated',
        'eol_date',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_deprecated' => 'boolean',
            'eol_date' => 'date',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Check if this version is the default PHP version.
     */
    public function isDefault(): bool
    {
        return $this->is_default;
    }

    /**
     * Check if this version is deprecated.
     */
    public function isDeprecated(): bool
    {
        return $this->is_deprecated;
    }

    /**
     * Check if this version has reached end-of-life.
     */
    public function isEndOfLife(): bool
    {
        if ($this->eol_date === null) {
            return false;
        }

        return $this->eol_date->isPast();
    }

    /**
     * Scope to filter only active (non-deprecated) versions.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_deprecated', false);
    }

    /**
     * Scope to order by sort_order.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }
}
