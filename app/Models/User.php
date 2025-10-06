<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use Billable, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Retrieve all servers provisioned by the user.
     */
    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }

    /**
     * Get all source providers connected by the user.
     */
    public function sourceProviders(): HasMany
    {
        return $this->hasMany(SourceProvider::class);
    }

    /**
     * Get the user's GitHub provider if connected.
     */
    public function githubProvider(): ?SourceProvider
    {
        return $this->sourceProviders()
            ->where('provider', 'github')
            ->first();
    }

    /**
     * Check if user has GitHub connected.
     */
    public function hasGitHubConnected(): bool
    {
        return $this->githubProvider() !== null;
    }

    /**
     * Get servers that count toward subscription limits.
     */
    public function activeServers(): HasMany
    {
        return $this->servers()->where('counted_in_subscription', true);
    }

    /**
     * Get user's payment methods.
     */
    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    /**
     * Get user's billing events.
     */
    public function billingEvents(): HasMany
    {
        return $this->hasMany(BillingEvent::class);
    }

    /**
     * Check if user can create more servers based on subscription.
     */
    public function canCreateServer(): bool
    {
        $limit = $this->getServerLimit();
        $current = $this->activeServers()->count();

        return $current < $limit;
    }

    /**
     * Get server limit based on subscription.
     */
    public function getServerLimit(): int
    {
        // Free users
        if (! $this->subscribed('default')) {
            return config('subscription.plans.free.server_limit', 1);
        }

        // Get subscription plan
        $planSlug = $this->getCurrentPlanSlug();

        return config("subscription.plans.{$planSlug}.server_limit", 1);
    }

    /**
     * Get current subscription plan slug.
     */
    public function getCurrentPlanSlug(): ?string
    {
        if (! $this->subscribed('default')) {
            return 'free';
        }

        $subscription = $this->subscription('default');
        $priceId = $subscription->stripe_price;

        // Match price ID to plan
        foreach (config('subscription.plans') as $slug => $plan) {
            if ($slug === 'free') {
                continue;
            }

            if (
                ($plan['monthly_price_id'] ?? null) === $priceId ||
                ($plan['yearly_price_id'] ?? null) === $priceId
            ) {
                return $slug;
            }
        }

        return null;
    }

    /**
     * Get remaining server slots.
     */
    public function getRemainingServerSlots(): int
    {
        return max(0, $this->getServerLimit() - $this->activeServers()->count());
    }

    /**
     * Check if user is on trial.
     */
    public function isOnTrial(): bool
    {
        return $this->onTrial('default');
    }

    /**
     * Check if user has active subscription.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscribed('default');
    }

    /**
     * Get subscription status label.
     */
    public function getSubscriptionStatus(): string
    {
        if ($this->isOnTrial()) {
            return 'trial';
        }

        if ($this->hasActiveSubscription()) {
            return 'active';
        }

        if ($this->subscription('default')?->cancelled()) {
            return 'cancelled';
        }

        if ($this->subscription('default')?->pastDue()) {
            return 'past_due';
        }

        return 'free';
    }
}
