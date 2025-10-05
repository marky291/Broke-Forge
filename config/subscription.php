<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Subscription Plans
    |--------------------------------------------------------------------------
    |
    | Define your subscription plans here. Each plan should have a name,
    | price ID from Stripe, server limit, and features list.
    |
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
    |
    | Configure usage-based pricing for server overages beyond plan limits.
    |
    */
    'overage' => [
        'enabled' => env('STRIPE_OVERAGE_ENABLED', false),
        'price_id' => env('STRIPE_PRICE_SERVER_OVERAGE'),
        'price_per_server' => 5.00, // $5 per additional server per month
    ],

    /*
    |--------------------------------------------------------------------------
    | Trial Configuration
    |--------------------------------------------------------------------------
    |
    | Number of days for trial period on new subscriptions.
    |
    */
    'trial_days' => env('SUBSCRIPTION_TRIAL_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Grace Period
    |--------------------------------------------------------------------------
    |
    | Days to allow access after subscription ends before restricting features.
    |
    */
    'grace_period_days' => env('SUBSCRIPTION_GRACE_PERIOD_DAYS', 3),
];
