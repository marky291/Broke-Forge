# Laravel Cashier Implementation Guide for BrokeForge

## Overview

This document provides a comprehensive, production-grade implementation plan for integrating Laravel Cashier (Stripe) into BrokeForge. The integration will enable subscription-based billing for server management services.

**Integration Scope:**

- Laravel Cashier (Stripe) for subscription management
- Multi-tier subscription plans (Free, Pro, Enterprise)
- Server limits based on subscription tier
- Usage-based billing for overages
- Payment method management
- Invoicing and receipt generation
- Webhook handling for payment events
- Frontend UI components for billing management

**Target Stripe API Version:** Latest (automatically handled by Cashier)

---

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Installation & Configuration](#installation--configuration)
3. [Database Schema Design](#database-schema-design)
4. [Model Changes](#model-changes)
5. [Subscription Plans Architecture](#subscription-plans-architecture)
6. [Backend Implementation](#backend-implementation)
7. [Frontend Implementation](#frontend-implementation)
8. [Webhook Configuration](#webhook-configuration)
9. [Testing Strategy](#testing-strategy)
10. [Security Considerations](#security-considerations)
11. [Deployment Checklist](#deployment-checklist)
12. [Task Breakdown](#task-breakdown)

---

## Prerequisites

### Required Stripe Accounts

- [ ] Stripe Test Account (for development)
- [ ] Stripe Production Account (for production)
- [ ] Verified business information in Stripe
- [ ] Tax settings configured in Stripe Dashboard

### Required API Keys

- [ ] Stripe Publishable Key (Test)
- [ ] Stripe Secret Key (Test)
- [ ] Stripe Webhook Secret (Test)
- [ ] Stripe Publishable Key (Production)
- [ ] Stripe Secret Key (Production)
- [ ] Stripe Webhook Secret (Production)

### Environment Requirements

- Laravel 12.x ✓ (already installed)
- PHP 8.2+ ✓ (already using)
- Stripe PHP SDK (installed via Cashier)
- Queue worker running ✓ (already configured)

---

## Installation & Configuration

### 1. Install Laravel Cashier

```bash
composer require laravel/cashier
```

### 2. Publish Cashier Configuration & Migrations

```bash
php artisan vendor:publish --tag="cashier-config"
php artisan vendor:publish --tag="cashier-migrations"
```

### 3. Environment Configuration

Add to `.env`:

```bash
# Stripe Keys
STRIPE_KEY=pk_test_xxxxxxxxxxxxx
STRIPE_SECRET=sk_test_xxxxxxxxxxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxxxxxxxxxx

# Cashier Configuration
CASHIER_CURRENCY=eur
CASHIER_CURRENCY_LOCALE=en_IE
CASHIER_LOGGER=stack

# Subscription Plans (Price IDs from Stripe Dashboard)
STRIPE_PRICE_FREE=price_xxxxxxxxxxxxx
STRIPE_PRICE_PRO_MONTHLY=price_xxxxxxxxxxxxx
STRIPE_PRICE_PRO_YEARLY=price_xxxxxxxxxxxxx
STRIPE_PRICE_ENTERPRISE_MONTHLY=price_xxxxxxxxxxxxx
STRIPE_PRICE_ENTERPRISE_YEARLY=price_xxxxxxxxxxxxx

# Usage-based pricing (for server overages)
STRIPE_PRICE_SERVER_OVERAGE=price_xxxxxxxxxxxxx
```

### 4. Update `config/cashier.php`

```php
return [
    'key' => env('STRIPE_KEY'),
    'secret' => env('STRIPE_SECRET'),
    'currency' => env('CASHIER_CURRENCY', 'usd'),
    'currency_locale' => env('CASHIER_CURRENCY_LOCALE', 'en'),
    'webhook' => [
        'secret' => env('STRIPE_WEBHOOK_SECRET'),
        'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
    ],
    'logger' => env('CASHIER_LOGGER'),
];
```

### 5. Create Custom Configuration File

Create `config/subscription.php`:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Subscription Plans
    |--------------------------------------------------------------------------
    */
    'plans' => [
        'free' => [
            'name' => 'Free',
            'price_id' => env('STRIPE_PRICE_FREE'),
            'server_limit' => 1,
            'features' => [
                '1 server',
                'Basic monitoring',
                'Community support',
                'Email notifications',
            ],
        ],
        'pro' => [
            'name' => 'Pro',
            'monthly_price_id' => env('STRIPE_PRICE_PRO_MONTHLY'),
            'yearly_price_id' => env('STRIPE_PRICE_PRO_YEARLY'),
            'server_limit' => 10,
            'features' => [
                'Up to 10 servers',
                'Advanced monitoring',
                'Priority support',
                'Scheduled tasks',
                'Auto-deployments',
                'Custom domains',
            ],
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'monthly_price_id' => env('STRIPE_PRICE_ENTERPRISE_MONTHLY'),
            'yearly_price_id' => env('STRIPE_PRICE_ENTERPRISE_YEARLY'),
            'server_limit' => 100,
            'features' => [
                'Up to 100 servers',
                'Enterprise monitoring',
                'Dedicated support',
                'Custom integrations',
                'SLA guarantees',
                'White-label options',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Usage-Based Pricing
    |--------------------------------------------------------------------------
    */
    'overage' => [
        'enabled' => true,
        'price_id' => env('STRIPE_PRICE_SERVER_OVERAGE'),
        'price_per_server' => 5.00, // $5 per additional server per month
    ],

    /*
    |--------------------------------------------------------------------------
    | Trial Configuration
    |--------------------------------------------------------------------------
    */
    'trial_days' => 14,

    /*
    |--------------------------------------------------------------------------
    | Grace Period
    |--------------------------------------------------------------------------
    | Days to allow access after subscription ends
    */
    'grace_period_days' => 3,
];
```

---

## Database Schema Design

### 1. Run Cashier Migrations

Cashier provides these migrations out of the box:

- `customer_columns` - Adds Stripe customer fields to users table
- `subscriptions` - Stores subscription data
- `subscription_items` - Stores subscription line items

```bash
php artisan migrate
```

### 2. Custom Migrations

**Create subscription plans table** (for caching Stripe plan data):

```bash
php artisan make:migration create_subscription_plans_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_product_id')->unique();
            $table->string('stripe_price_id')->unique();
            $table->string('name');
            $table->string('slug')->unique(); // free, pro, enterprise
            $table->integer('amount'); // In cents
            $table->string('currency', 3)->default('usd');
            $table->enum('interval', ['month', 'year']);
            $table->integer('interval_count')->default(1);
            $table->integer('server_limit');
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('slug');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
```

**Add subscription fields to servers table:**

```bash
php artisan make:migration add_subscription_tracking_to_servers_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->boolean('counted_in_subscription')->default(true)->after('monitoring_interval');
            $table->timestamp('subscription_counted_at')->nullable()->after('counted_in_subscription');

            $table->index('counted_in_subscription');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['counted_in_subscription', 'subscription_counted_at']);
        });
    }
};
```

**Create payment methods table** (for tracking user payment methods):

```bash
php artisan make:migration create_payment_methods_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_payment_method_id')->unique();
            $table->string('type'); // card, bank_account
            $table->string('brand')->nullable(); // visa, mastercard, etc.
            $table->string('last_four', 4)->nullable();
            $table->integer('exp_month')->nullable();
            $table->integer('exp_year')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('user_id');
            $table->index('is_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
```

**Create billing events table** (for audit trail):

```bash
php artisan make:migration create_billing_events_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // subscription_created, payment_succeeded, etc.
            $table->string('stripe_event_id')->unique()->nullable();
            $table->json('metadata')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_events');
    }
};
```

---

## Model Changes

### 1. Update User Model

File: `app/Models/User.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, Billable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

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
     * Get servers that count toward subscription limits.
     */
    public function activeServers(): HasMany
    {
        return $this->servers()->where('counted_in_subscription', true);
    }

    /**
     * Get all source providers connected by the user.
     */
    public function sourceProviders(): HasMany
    {
        return $this->hasMany(SourceProvider::class);
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
        if (!$this->subscribed('default')) {
            return config('subscription.plans.free.server_limit', 1);
        }

        // Get subscription plan
        $subscription = $this->subscription('default');
        $planSlug = $this->getCurrentPlanSlug();

        return config("subscription.plans.{$planSlug}.server_limit", 1);
    }

    /**
     * Get current subscription plan slug.
     */
    public function getCurrentPlanSlug(): ?string
    {
        if (!$this->subscribed('default')) {
            return 'free';
        }

        $subscription = $this->subscription('default');
        $priceId = $subscription->stripe_price;

        // Match price ID to plan
        foreach (config('subscription.plans') as $slug => $plan) {
            if ($slug === 'free') continue;

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
```

### 2. Create SubscriptionPlan Model

File: `app/Models/SubscriptionPlan.php`

```php
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
        'features' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get formatted price.
     */
    public function getFormattedPriceAttribute(): string
    {
        return '$' . number_format($this->amount / 100, 2);
    }

    /**
     * Get price per interval.
     */
    public function getPricePerIntervalAttribute(): string
    {
        return $this->formatted_price . '/' . $this->interval;
    }

    /**
     * Scope active plans.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
```

### 3. Create PaymentMethod Model

File: `app/Models/PaymentMethod.php`

```php
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
        return ucfirst($this->brand) . ' •••• ' . $this->last_four;
    }

    /**
     * Check if card is expired.
     */
    public function isExpired(): bool
    {
        if (!$this->exp_month || !$this->exp_year) {
            return false;
        }

        $expiry = \Carbon\Carbon::createFromDate($this->exp_year, $this->exp_month, 1)->endOfMonth();
        return $expiry->isPast();
    }
}
```

### 4. Create BillingEvent Model

File: `app/Models/BillingEvent.php`

```php
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
    public static function createFromStripeEvent($user, $event, string $description = null): self
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
```

---

## Subscription Plans Architecture

### 1. Create Subscription Service

File: `app/Services/Subscription/SubscriptionService.php`

```php
<?php

namespace App\Services\Subscription;

use App\Models\User;
use App\Models\BillingEvent;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    /**
     * Create subscription for user.
     */
    public function createSubscription(User $user, string $priceId, ?string $paymentMethodId = null): void
    {
        DB::transaction(function () use ($user, $priceId, $paymentMethodId) {
            $subscription = $user->newSubscription('default', $priceId);

            // Add trial if configured
            if (config('subscription.trial_days')) {
                $subscription->trialDays(config('subscription.trial_days'));
            }

            // Create subscription
            if ($paymentMethodId) {
                $subscription->create($paymentMethodId);
            } else {
                $subscription->create();
            }

            // Log event
            BillingEvent::create([
                'user_id' => $user->id,
                'type' => 'subscription_created',
                'description' => 'Subscription created for plan: ' . $priceId,
            ]);
        });
    }

    /**
     * Change subscription plan.
     */
    public function changePlan(User $user, string $newPriceId): void
    {
        DB::transaction(function () use ($user, $newPriceId) {
            $subscription = $user->subscription('default');
            $oldPriceId = $subscription->stripe_price;

            // Swap plans (prorate by default)
            $subscription->swap($newPriceId);

            // Log event
            BillingEvent::create([
                'user_id' => $user->id,
                'type' => 'subscription_updated',
                'description' => "Plan changed from {$oldPriceId} to {$newPriceId}",
            ]);
        });
    }

    /**
     * Cancel subscription.
     */
    public function cancelSubscription(User $user, bool $immediate = false): void
    {
        DB::transaction(function () use ($user, $immediate) {
            $subscription = $user->subscription('default');

            if ($immediate) {
                $subscription->cancelNow();
            } else {
                $subscription->cancel();
            }

            // Log event
            BillingEvent::create([
                'user_id' => $user->id,
                'type' => 'subscription_cancelled',
                'description' => $immediate ? 'Subscription cancelled immediately' : 'Subscription cancelled at period end',
            ]);
        });
    }

    /**
     * Resume cancelled subscription.
     */
    public function resumeSubscription(User $user): void
    {
        DB::transaction(function () use ($user) {
            $subscription = $user->subscription('default');
            $subscription->resume();

            // Log event
            BillingEvent::create([
                'user_id' => $user->id,
                'type' => 'subscription_resumed',
                'description' => 'Subscription resumed',
            ]);
        });
    }

    /**
     * Check if user can create server.
     */
    public function canCreateServer(User $user): array
    {
        $limit = $user->getServerLimit();
        $current = $user->activeServers()->count();
        $canCreate = $current < $limit;

        return [
            'can_create' => $canCreate,
            'current' => $current,
            'limit' => $limit,
            'remaining' => max(0, $limit - $current),
        ];
    }

    /**
     * Record server usage for billing.
     */
    public function recordServerUsage(User $user): void
    {
        if (!config('subscription.overage.enabled')) {
            return;
        }

        $limit = $user->getServerLimit();
        $current = $user->activeServers()->count();
        $overage = max(0, $current - $limit);

        if ($overage > 0 && $user->subscribed('default')) {
            $user->subscription('default')->recordUsage($overage);
        }
    }
}
```

### 2. Create Middleware for Subscription Checks

File: `app/Http/Middleware/CheckSubscription.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckSubscription
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Check if subscription is past due
        if ($user->subscription('default')?->pastDue()) {
            return redirect()->route('billing.index')
                ->with('error', 'Your subscription payment is past due. Please update your payment method.');
        }

        return $next($request);
    }
}
```

File: `app/Http/Middleware/CheckServerLimit.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\Subscription\SubscriptionService;

class CheckServerLimit
{
    public function __construct(
        private SubscriptionService $subscriptionService
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        $check = $this->subscriptionService->canCreateServer($user);

        if (!$check['can_create']) {
            return back()->with('error',
                "You've reached your server limit ({$check['limit']}). Please upgrade your subscription to add more servers."
            );
        }

        return $next($request);
    }
}
```

---

## Backend Implementation

### 1. Create Billing Controllers

File: `app/Http/Controllers/BillingController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BillingController extends Controller
{
    /**
     * Display billing dashboard.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        return Inertia::render('billing/index', [
            'subscription' => $user->subscription('default'),
            'paymentMethods' => $user->paymentMethods,
            'invoices' => $user->invoicesIncludingPending(),
            'upcomingInvoice' => $user->upcomingInvoice(),
            'serverUsage' => [
                'current' => $user->activeServers()->count(),
                'limit' => $user->getServerLimit(),
                'remaining' => $user->getRemainingServerSlots(),
            ],
            'plans' => SubscriptionPlan::active()->get(),
        ]);
    }

    /**
     * Download invoice.
     */
    public function downloadInvoice(Request $request, string $invoiceId)
    {
        return $request->user()->downloadInvoice($invoiceId, [
            'vendor' => 'BrokeForge',
            'product' => 'Server Management Subscription',
        ]);
    }
}
```

File: `app/Http/Controllers/SubscriptionController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubscriptionController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptionService
    ) {}

    /**
     * Create new subscription.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'price_id' => 'required|string',
            'payment_method_id' => 'required|string',
        ]);

        try {
            $this->subscriptionService->createSubscription(
                $request->user(),
                $validated['price_id'],
                $validated['payment_method_id']
            );

            return redirect()->route('billing.index')
                ->with('success', 'Subscription created successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to create subscription: ' . $e->getMessage());
        }
    }

    /**
     * Update subscription plan.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'price_id' => 'required|string',
        ]);

        try {
            $this->subscriptionService->changePlan(
                $request->user(),
                $validated['price_id']
            );

            return redirect()->route('billing.index')
                ->with('success', 'Subscription updated successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update subscription: ' . $e->getMessage());
        }
    }

    /**
     * Cancel subscription.
     */
    public function destroy(Request $request)
    {
        $immediate = $request->boolean('immediate', false);

        try {
            $this->subscriptionService->cancelSubscription(
                $request->user(),
                $immediate
            );

            $message = $immediate
                ? 'Subscription cancelled immediately.'
                : 'Subscription will be cancelled at the end of the current billing period.';

            return redirect()->route('billing.index')
                ->with('success', $message);
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to cancel subscription: ' . $e->getMessage());
        }
    }

    /**
     * Resume cancelled subscription.
     */
    public function resume(Request $request)
    {
        try {
            $this->subscriptionService->resumeSubscription($request->user());

            return redirect()->route('billing.index')
                ->with('success', 'Subscription resumed successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to resume subscription: ' . $e->getMessage());
        }
    }
}
```

File: `app/Http/Controllers/PaymentMethodController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    /**
     * Store new payment method.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'payment_method_id' => 'required|string',
        ]);

        try {
            $user = $request->user();

            // Add payment method to Stripe
            $user->addPaymentMethod($validated['payment_method_id']);

            // Get payment method details from Stripe
            $stripePaymentMethod = $user->findPaymentMethod($validated['payment_method_id']);

            // Store in database
            PaymentMethod::create([
                'user_id' => $user->id,
                'stripe_payment_method_id' => $validated['payment_method_id'],
                'type' => $stripePaymentMethod->type,
                'brand' => $stripePaymentMethod->card->brand ?? null,
                'last_four' => $stripePaymentMethod->card->last4 ?? null,
                'exp_month' => $stripePaymentMethod->card->exp_month ?? null,
                'exp_year' => $stripePaymentMethod->card->exp_year ?? null,
                'is_default' => false,
            ]);

            return redirect()->route('billing.index')
                ->with('success', 'Payment method added successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to add payment method: ' . $e->getMessage());
        }
    }

    /**
     * Set default payment method.
     */
    public function setDefault(Request $request, PaymentMethod $paymentMethod)
    {
        $this->authorize('update', $paymentMethod);

        try {
            $user = $request->user();

            // Update default in Stripe
            $user->updateDefaultPaymentMethod($paymentMethod->stripe_payment_method_id);

            // Update database
            $user->paymentMethods()->update(['is_default' => false]);
            $paymentMethod->update(['is_default' => true]);

            return redirect()->route('billing.index')
                ->with('success', 'Default payment method updated!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update default payment method: ' . $e->getMessage());
        }
    }

    /**
     * Remove payment method.
     */
    public function destroy(PaymentMethod $paymentMethod)
    {
        $this->authorize('delete', $paymentMethod);

        try {
            // Remove from Stripe
            $paymentMethod->user->removePaymentMethod($paymentMethod->stripe_payment_method_id);

            // Remove from database
            $paymentMethod->delete();

            return redirect()->route('billing.index')
                ->with('success', 'Payment method removed successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to remove payment method: ' . $e->getMessage());
        }
    }
}
```

### 2. Update ServerController

File: `app/Http/Controllers/ServerController.php`

Add server limit check in `store` method:

```php
public function store(Request $request)
{
    // Check server limit
    if (!$request->user()->canCreateServer()) {
        return back()->with('error',
            'You have reached your server limit. Please upgrade your subscription to add more servers.'
        );
    }

    // Existing server creation logic...
}
```

### 3. Create Webhook Handler

File: `app/Http/Controllers/StripeWebhookController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\BillingEvent;
use App\Models\User;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;

class StripeWebhookController extends CashierController
{
    /**
     * Handle invoice payment succeeded.
     */
    public function handleInvoicePaymentSucceeded(array $payload)
    {
        $user = $this->getUserByStripeId($payload['data']['object']['customer']);

        if ($user) {
            BillingEvent::create([
                'user_id' => $user->id,
                'type' => 'invoice.payment_succeeded',
                'stripe_event_id' => $payload['id'],
                'description' => 'Invoice payment succeeded',
                'metadata' => $payload,
            ]);
        }

        return $this->successMethod();
    }

    /**
     * Handle invoice payment failed.
     */
    public function handleInvoicePaymentFailed(array $payload)
    {
        $user = $this->getUserByStripeId($payload['data']['object']['customer']);

        if ($user) {
            BillingEvent::create([
                'user_id' => $user->id,
                'type' => 'invoice.payment_failed',
                'stripe_event_id' => $payload['id'],
                'description' => 'Invoice payment failed',
                'metadata' => $payload,
            ]);

            // Send notification to user
            // $user->notify(new PaymentFailedNotification());
        }

        return $this->successMethod();
    }

    /**
     * Handle customer subscription updated.
     */
    public function handleCustomerSubscriptionUpdated(array $payload)
    {
        $user = $this->getUserByStripeId($payload['data']['object']['customer']);

        if ($user) {
            BillingEvent::create([
                'user_id' => $user->id,
                'type' => 'customer.subscription.updated',
                'stripe_event_id' => $payload['id'],
                'description' => 'Subscription updated',
                'metadata' => $payload,
            ]);
        }

        return parent::handleCustomerSubscriptionUpdated($payload);
    }

    /**
     * Handle customer subscription deleted.
     */
    public function handleCustomerSubscriptionDeleted(array $payload)
    {
        $user = $this->getUserByStripeId($payload['data']['object']['customer']);

        if ($user) {
            BillingEvent::create([
                'user_id' => $user->id,
                'type' => 'customer.subscription.deleted',
                'stripe_event_id' => $payload['id'],
                'description' => 'Subscription deleted',
                'metadata' => $payload,
            ]);
        }

        return parent::handleCustomerSubscriptionDeleted($payload);
    }

    /**
     * Get user by Stripe customer ID.
     */
    protected function getUserByStripeId(string $stripeId): ?User
    {
        return User::where('stripe_id', $stripeId)->first();
    }
}
```

### 4. Create Routes

File: `routes/billing.php`

```php
<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Billing Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->prefix('billing')->name('billing.')->group(function () {
    // Billing dashboard
    Route::get('/', [BillingController::class, 'index'])->name('index');
    Route::get('/invoices/{invoiceId}', [BillingController::class, 'downloadInvoice'])->name('invoices.download');

    // Subscription management
    Route::post('/subscriptions', [SubscriptionController::class, 'store'])->name('subscriptions.store');
    Route::put('/subscriptions', [SubscriptionController::class, 'update'])->name('subscriptions.update');
    Route::delete('/subscriptions', [SubscriptionController::class, 'destroy'])->name('subscriptions.destroy');
    Route::post('/subscriptions/resume', [SubscriptionController::class, 'resume'])->name('subscriptions.resume');

    // Payment methods
    Route::post('/payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
    Route::post('/payment-methods/{paymentMethod}/default', [PaymentMethodController::class, 'setDefault'])->name('payment-methods.default');
    Route::delete('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');
});
```

Add to `routes/web.php`:

```php
// Include billing routes
require __DIR__.'/billing.php';
```

---

## Frontend Implementation

### 1. Create Billing Pages

**File: `resources/js/pages/billing/index.tsx`**

```tsx
import { Head } from '@inertiajs/react';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { PageHeader } from '@/components/ui/page-header';
import SubscriptionCard from '@/components/billing/subscription-card';
import PaymentMethodsList from '@/components/billing/payment-methods-list';
import InvoicesList from '@/components/billing/invoices-list';
import PlanComparison from '@/components/billing/plan-comparison';

interface BillingIndexProps {
    subscription: any;
    paymentMethods: any[];
    invoices: any[];
    upcomingInvoice: any;
    serverUsage: {
        current: number;
        limit: number;
        remaining: number;
    };
    plans: any[];
}

export default function BillingIndex({ subscription, paymentMethods, invoices, upcomingInvoice, serverUsage, plans }: BillingIndexProps) {
    return (
        <>
            <Head title="Billing & Subscription" />

            <div className="space-y-6">
                <PageHeader title="Billing & Subscription" description="Manage your subscription, payment methods, and billing history" />

                {/* Server Usage */}
                <Card className="p-6">
                    <h3 className="mb-4 text-lg font-semibold">Server Usage</h3>
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-2xl font-bold">
                                {serverUsage.current} / {serverUsage.limit}
                            </p>
                            <p className="text-muted-foreground text-sm">Active servers</p>
                        </div>
                        <Badge variant={serverUsage.remaining > 0 ? 'default' : 'destructive'}>{serverUsage.remaining} remaining</Badge>
                    </div>
                </Card>

                {/* Current Subscription */}
                <SubscriptionCard subscription={subscription} />

                {/* Payment Methods */}
                <PaymentMethodsList paymentMethods={paymentMethods} />

                {/* Plan Comparison */}
                <PlanComparison plans={plans} currentSubscription={subscription} />

                {/* Invoices */}
                <InvoicesList invoices={invoices} />
            </div>
        </>
    );
}
```

### 2. Create Billing Components

**File: `resources/js/components/billing/subscription-card.tsx`**

```tsx
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { router } from '@inertiajs/react';

interface SubscriptionCardProps {
    subscription: any;
}

export default function SubscriptionCard({ subscription }: SubscriptionCardProps) {
    const handleCancel = () => {
        if (confirm('Are you sure you want to cancel your subscription?')) {
            router.delete(route('billing.subscriptions.destroy'));
        }
    };

    const handleResume = () => {
        router.post(route('billing.subscriptions.resume'));
    };

    if (!subscription) {
        return (
            <Card className="p-6">
                <h3 className="mb-4 text-lg font-semibold">Current Plan</h3>
                <div className="flex items-center justify-between">
                    <div>
                        <p className="text-xl font-bold">Free Plan</p>
                        <p className="text-muted-foreground text-sm">Limited to 1 server</p>
                    </div>
                    <Button onClick={() => router.visit('#plans')}>Upgrade</Button>
                </div>
            </Card>
        );
    }

    return (
        <Card className="p-6">
            <h3 className="mb-4 text-lg font-semibold">Current Subscription</h3>

            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <div>
                        <p className="text-xl font-bold">{subscription.name}</p>
                        <p className="text-muted-foreground text-sm">
                            ${(subscription.price / 100).toFixed(2)} / {subscription.interval}
                        </p>
                    </div>
                    <Badge variant={subscription.active ? 'default' : 'destructive'}>{subscription.active ? 'Active' : 'Inactive'}</Badge>
                </div>

                {subscription.trial_ends_at && (
                    <div>
                        <Badge variant="outline">Trial ends {new Date(subscription.trial_ends_at).toLocaleDateString()}</Badge>
                    </div>
                )}

                {subscription.ends_at && (
                    <div>
                        <Badge variant="warning">Cancels on {new Date(subscription.ends_at).toLocaleDateString()}</Badge>
                    </div>
                )}

                <div className="flex gap-2">
                    {subscription.ends_at ? (
                        <Button onClick={handleResume}>Resume Subscription</Button>
                    ) : (
                        <Button variant="destructive" onClick={handleCancel}>
                            Cancel Subscription
                        </Button>
                    )}
                    <Button variant="outline" onClick={() => router.visit('#plans')}>
                        Change Plan
                    </Button>
                </div>
            </div>
        </Card>
    );
}
```

**File: `resources/js/components/billing/plan-comparison.tsx`**

```tsx
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Check } from 'lucide-react';
import { router } from '@inertiajs/react';

interface Plan {
    id: number;
    name: string;
    slug: string;
    amount: number;
    interval: string;
    features: string[];
    stripe_price_id: string;
}

interface PlanComparisonProps {
    plans: Plan[];
    currentSubscription: any;
}

export default function PlanComparison({ plans, currentSubscription }: PlanComparisonProps) {
    const handleSubscribe = (priceId: string) => {
        // This would open a Stripe checkout or payment modal
        router.post(route('billing.subscriptions.store'), {
            price_id: priceId,
        });
    };

    const handleChangePlan = (priceId: string) => {
        router.put(route('billing.subscriptions.update'), {
            price_id: priceId,
        });
    };

    const isCurrentPlan = (priceId: string) => {
        return currentSubscription?.stripe_price === priceId;
    };

    return (
        <div id="plans">
            <h3 className="mb-6 text-2xl font-bold">Choose Your Plan</h3>

            <div className="grid gap-6 md:grid-cols-3">
                {plans.map((plan) => (
                    <Card key={plan.id} className="space-y-4 p-6">
                        <div>
                            <h4 className="text-xl font-bold">{plan.name}</h4>
                            <p className="mt-2 text-3xl font-bold">
                                ${(plan.amount / 100).toFixed(0)}
                                <span className="text-muted-foreground text-sm font-normal">/{plan.interval}</span>
                            </p>
                        </div>

                        <ul className="space-y-2">
                            {plan.features.map((feature, index) => (
                                <li key={index} className="flex items-start gap-2">
                                    <Check className="mt-0.5 h-5 w-5 flex-shrink-0 text-green-500" />
                                    <span className="text-sm">{feature}</span>
                                </li>
                            ))}
                        </ul>

                        {isCurrentPlan(plan.stripe_price_id) ? (
                            <Badge className="w-full justify-center">Current Plan</Badge>
                        ) : (
                            <Button
                                className="w-full"
                                onClick={() => (currentSubscription ? handleChangePlan(plan.stripe_price_id) : handleSubscribe(plan.stripe_price_id))}
                            >
                                {currentSubscription ? 'Switch to this plan' : 'Subscribe'}
                            </Button>
                        )}
                    </Card>
                ))}
            </div>
        </div>
    );
}
```

### 3. Add Stripe Elements Integration

**Install Stripe.js:**

```bash
npm install @stripe/stripe-js @stripe/react-stripe-js
```

**File: `resources/js/components/billing/payment-method-form.tsx`**

```tsx
import { loadStripe } from '@stripe/stripe-js';
import { Elements, CardElement, useStripe, useElements } from '@stripe/react-stripe-js';
import { Button } from '@/components/ui/button';
import { useState } from 'react';
import { router } from '@inertiajs/react';

const stripePromise = loadStripe(import.meta.env.VITE_STRIPE_KEY);

function PaymentMethodFormContent() {
    const stripe = useStripe();
    const elements = useElements();
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!stripe || !elements) {
            return;
        }

        setProcessing(true);
        setError(null);

        const cardElement = elements.getElement(CardElement);

        if (!cardElement) {
            setProcessing(false);
            return;
        }

        const { error, paymentMethod } = await stripe.createPaymentMethod({
            type: 'card',
            card: cardElement,
        });

        if (error) {
            setError(error.message || 'An error occurred');
            setProcessing(false);
            return;
        }

        // Submit to backend
        router.post(
            route('billing.payment-methods.store'),
            {
                payment_method_id: paymentMethod.id,
            },
            {
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            <div className="rounded-md border p-3">
                <CardElement
                    options={{
                        style: {
                            base: {
                                fontSize: '16px',
                                color: '#424770',
                                '::placeholder': {
                                    color: '#aab7c4',
                                },
                            },
                            invalid: {
                                color: '#9e2146',
                            },
                        },
                    }}
                />
            </div>

            {error && <p className="text-destructive text-sm">{error}</p>}

            <Button type="submit" disabled={!stripe || processing}>
                {processing ? 'Adding...' : 'Add Payment Method'}
            </Button>
        </form>
    );
}

export default function PaymentMethodForm() {
    return (
        <Elements stripe={stripePromise}>
            <PaymentMethodFormContent />
        </Elements>
    );
}
```

### 4. Update Navigation

Add billing link to navigation:

```tsx
// In your main navigation component
<NavigationMenuItem>
    <NavigationMenuLink href={route('billing.index')}>Billing</NavigationMenuLink>
</NavigationMenuItem>
```

---

## Webhook Configuration

### 1. Register Webhook Route

Add to `routes/web.php`:

```php
Route::post(
    'stripe/webhook',
    [App\Http\Controllers\StripeWebhookController::class, 'handleWebhook']
)->name('cashier.webhook');
```

### 2. Disable CSRF for Webhooks

File: `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'stripe/webhook',
    ]);
})
```

### 3. Configure Stripe Webhook

**In Stripe Dashboard:**

1. Go to Developers → Webhooks
2. Click "Add endpoint"
3. URL: `https://yourdomain.com/stripe/webhook`
4. Events to send:
    - `customer.subscription.created`
    - `customer.subscription.updated`
    - `customer.subscription.deleted`
    - `customer.updated`
    - `customer.deleted`
    - `invoice.payment_succeeded`
    - `invoice.payment_failed`
    - `payment_method.attached`
    - `payment_method.detached`

5. Copy webhook signing secret to `.env` as `STRIPE_WEBHOOK_SECRET`

---

## Testing Strategy

### 1. Unit Tests

**File: `tests/Unit/SubscriptionServiceTest.php`**

```php
<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Subscription;
use Mockery;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SubscriptionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SubscriptionService();
    }

    public function test_can_check_server_creation_limit(): void
    {
        $user = User::factory()->create();

        $result = $this->service->canCreateServer($user);

        $this->assertArrayHasKey('can_create', $result);
        $this->assertArrayHasKey('current', $result);
        $this->assertArrayHasKey('limit', $result);
        $this->assertArrayHasKey('remaining', $result);
    }

    public function test_free_user_has_correct_limit(): void
    {
        $user = User::factory()->create();

        $result = $this->service->canCreateServer($user);

        $this->assertEquals(1, $result['limit']);
    }

    // Add more tests...
}
```

### 2. Feature Tests

**File: `tests/Feature/BillingTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_page_requires_authentication(): void
    {
        $response = $this->get(route('billing.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_billing_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('billing.index'));

        $response->assertOk();
    }

    // Add more tests...
}
```

### 3. Stripe Test Cards

Use these cards for testing:

- **Success:** `4242 4242 4242 4242`
- **Decline:** `4000 0000 0000 0002`
- **Insufficient funds:** `4000 0000 0000 9995`
- **3D Secure required:** `4000 0025 0000 3155`

---

## Security Considerations

### 1. Environment Security

- [ ] Never commit `.env` file
- [ ] Use different Stripe keys for test/production
- [ ] Rotate webhook secrets periodically
- [ ] Use HTTPS in production

### 2. Payment Security

- [ ] Never store full card numbers
- [ ] Use Stripe.js for PCI compliance
- [ ] Implement rate limiting on payment endpoints
- [ ] Log all payment events
- [ ] Verify webhook signatures

### 3. Authorization

- [ ] Ensure users can only access their own billing data
- [ ] Implement policy for PaymentMethod model
- [ ] Verify subscription ownership before modifications

**File: `app/Policies/PaymentMethodPolicy.php`**

```php
<?php

namespace App\Policies;

use App\Models\PaymentMethod;
use App\Models\User;

class PaymentMethodPolicy
{
    public function update(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->id === $paymentMethod->user_id;
    }

    public function delete(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->id === $paymentMethod->user_id;
    }
}
```

### 4. Input Validation

- [ ] Validate all Stripe IDs format
- [ ] Sanitize webhook payloads
- [ ] Verify plan existence before subscription

---

## Deployment Checklist

### Pre-Deployment

- [ ] Create Stripe products and prices in production
- [ ] Update `.env` with production Stripe keys
- [ ] Configure production webhook endpoint
- [ ] Test webhook endpoint with Stripe CLI
- [ ] Seed subscription plans table
- [ ] Run all migrations
- [ ] Test subscription flow in staging

### Post-Deployment

- [ ] Monitor webhook deliveries in Stripe Dashboard
- [ ] Verify billing events are being logged
- [ ] Test full subscription lifecycle
- [ ] Monitor failed payments
- [ ] Set up alerts for payment failures
- [ ] Document cancellation/refund procedures

### Stripe Dashboard Configuration

- [ ] Set business information
- [ ] Configure tax settings
- [ ] Set up email receipts
- [ ] Configure customer portal settings
- [ ] Set retry logic for failed payments
- [ ] Enable fraud detection

---

## Task Breakdown

### Phase 1: Setup & Configuration (Day 1-2)

- [ ] Install Laravel Cashier
- [ ] Publish config and migrations
- [ ] Configure environment variables
- [ ] Create Stripe products and prices (test mode)
- [ ] Create `config/subscription.php`
- [ ] Run Cashier migrations

### Phase 2: Database Schema (Day 2-3)

- [ ] Create subscription_plans migration
- [ ] Create payment_methods migration
- [ ] Create billing_events migration
- [ ] Update servers table migration
- [ ] Run all migrations
- [ ] Create SubscriptionPlan model
- [ ] Create PaymentMethod model
- [ ] Create BillingEvent model

### Phase 3: Backend - Models & Services (Day 3-5)

- [ ] Add Billable trait to User model
- [ ] Add subscription helper methods to User
- [ ] Implement server limit logic
- [ ] Create SubscriptionService
- [ ] Create CheckSubscription middleware
- [ ] Create CheckServerLimit middleware
- [ ] Create PaymentMethodPolicy
- [ ] Update ServerController with limit checks

### Phase 4: Backend - Controllers (Day 5-7)

- [ ] Create BillingController
- [ ] Create SubscriptionController
- [ ] Create PaymentMethodController
- [ ] Create StripeWebhookController
- [ ] Create billing routes file
- [ ] Register webhook route
- [ ] Update CSRF exceptions

### Phase 5: Frontend - Pages (Day 7-10)

- [ ] Install Stripe.js dependencies
- [ ] Create billing/index.tsx
- [ ] Create SubscriptionCard component
- [ ] Create PlanComparison component
- [ ] Create PaymentMethodsList component
- [ ] Create PaymentMethodForm component
- [ ] Create InvoicesList component
- [ ] Add billing link to navigation
- [ ] Configure Stripe public key

### Phase 6: Webhook Integration (Day 10-11)

- [ ] Implement webhook handlers
- [ ] Test webhook locally with Stripe CLI
- [ ] Configure production webhook endpoint
- [ ] Verify webhook signatures
- [ ] Test all webhook events
- [ ] Set up webhook monitoring

### Phase 7: Testing (Day 11-13)

- [ ] Write SubscriptionService unit tests
- [ ] Write User model tests
- [ ] Write BillingController feature tests
- [ ] Write SubscriptionController feature tests
- [ ] Test with Stripe test cards
- [ ] Test webhook handling
- [ ] Test server limit enforcement
- [ ] End-to-end subscription flow test

### Phase 8: UI/UX Polish (Day 13-14)

- [ ] Add loading states
- [ ] Add error handling
- [ ] Add success notifications
- [ ] Improve mobile responsiveness
- [ ] Add subscription status indicators
- [ ] Add trial countdown
- [ ] Add upgrade prompts

### Phase 9: Production Preparation (Day 14-15)

- [ ] Create production Stripe products
- [ ] Update production environment variables
- [ ] Seed production subscription plans
- [ ] Configure production webhooks
- [ ] Test in staging environment
- [ ] Document deployment process
- [ ] Create rollback plan

### Phase 10: Deployment & Monitoring (Day 15+)

- [ ] Deploy to production
- [ ] Verify webhook connectivity
- [ ] Monitor initial subscriptions
- [ ] Monitor payment events
- [ ] Set up billing alerts
- [ ] Create admin dashboard for billing metrics
- [ ] Document customer support procedures

---

## Estimated Timeline

**Total: 15-20 business days**

- Backend: 7-9 days
- Frontend: 5-6 days
- Testing: 2-3 days
- Deployment: 1-2 days

---

## Additional Resources

### Documentation

- [Laravel Cashier Docs](https://laravel.com/docs/12.x/billing)
- [Stripe API Docs](https://stripe.com/docs/api)
- [Stripe Testing Docs](https://stripe.com/docs/testing)

### Tools

- [Stripe CLI](https://stripe.com/docs/stripe-cli) - Local webhook testing
- [Stripe Dashboard](https://dashboard.stripe.com) - Manage products/webhooks

### Support Channels

- Laravel Cashier GitHub Issues
- Stripe Support
- BrokeForge internal documentation

---

## Notes

- All monetary amounts in Stripe are in cents
- Always use Stripe test mode during development
- Implement grace period for expired subscriptions
- Consider implementing customer portal using Cashier's built-in support
- Plan for handling edge cases (failed payments, disputes, refunds)
- Document cancellation and refund policies
- Consider implementing usage-based billing for server overages
- Plan for internationalization if expanding globally

---

**Last Updated:** 2025-01-05
**Version:** 1.0
**Author:** BrokeForge Development Team
