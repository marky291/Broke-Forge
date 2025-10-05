<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'stripe_product_id',
        'stripe_price_id',
        'name',
        'slug',
        'amount',
        'currency',
        'interval',
        'interval_count',
        'server_limit',
        'features',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'amount' => 'integer',
        'server_limit' => 'integer',
        'interval_count' => 'integer',
        'sort_order' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'formatted_price',
        'price_per_interval',
    ];

    /**
     * Get formatted price.
     */
    public function getFormattedPriceAttribute(): string
    {
        return '€'.number_format($this->amount / 100, 2);
    }

    /**
     * Get price per interval.
     */
    public function getPricePerIntervalAttribute(): string
    {
        return $this->formatted_price.'/'.$this->interval;
    }

    /**
     * Scope active plans.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
