<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethod extends Model
{
    protected $fillable = [
        'user_id',
        'stripe_payment_method_id',
        'type',
        'brand',
        'last_four',
        'exp_month',
        'exp_year',
        'is_default',
    ];

    protected $casts = [
        'exp_month' => 'integer',
        'exp_year' => 'integer',
        'is_default' => 'boolean',
    ];

    /**
     * Get the user that owns the payment method.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get display name for payment method.
     */
    public function getDisplayNameAttribute(): string
    {
        return ucfirst($this->brand).' •••• '.$this->last_four;
    }

    /**
     * Check if card is expired.
     */
    public function isExpired(): bool
    {
        if (! $this->exp_month || ! $this->exp_year) {
            return false;
        }

        $expiry = \Carbon\Carbon::createFromDate($this->exp_year, $this->exp_month, 1)->endOfMonth();

        return $expiry->isPast();
    }
}
