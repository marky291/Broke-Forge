<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingEvent extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'stripe_event_id',
        'metadata',
        'description',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get the user that owns the event.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Create event from Stripe webhook.
     */
    public static function createFromStripeEvent($user, $event, ?string $description = null): self
    {
        return self::create([
            'user_id' => $user->id,
            'type' => $event->type,
            'stripe_event_id' => $event->id,
            'metadata' => $event->data->toArray(),
            'description' => $description,
        ]);
    }
}
