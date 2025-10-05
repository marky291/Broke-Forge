<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;
use Laravel\Cashier\Cashier;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing plans
        SubscriptionPlan::truncate();

        $stripe = Cashier::stripe();

        // Pro Monthly Plan
        if ($priceId = config('subscription.plans.pro.monthly_price_id')) {
            try {
                $price = $stripe->prices->retrieve($priceId);
                $product = $stripe->products->retrieve($price->product);

                SubscriptionPlan::create([
                    'stripe_product_id' => $product->id,
                    'stripe_price_id' => $price->id,
                    'name' => 'Pro',
                    'slug' => 'pro',
                    'amount' => $price->unit_amount,
                    'currency' => $price->currency,
                    'interval' => $price->recurring->interval,
                    'interval_count' => $price->recurring->interval_count,
                    'server_limit' => config('subscription.plans.pro.server_limit'),
                    'features' => config('subscription.plans.pro.features'),
                    'is_active' => true,
                    'sort_order' => 1,
                ]);

                $this->command->info('✓ Created Pro Monthly plan');
            } catch (\Exception $e) {
                $this->command->error('✗ Failed to create Pro Monthly plan: '.$e->getMessage());
            }
        } else {
            $this->command->warn('⚠ Skipping Pro Monthly - STRIPE_PRICE_PRO_MONTHLY not set in .env');
        }

        // Pro Yearly Plan
        if ($priceId = config('subscription.plans.pro.yearly_price_id')) {
            try {
                $price = $stripe->prices->retrieve($priceId);
                $product = $stripe->products->retrieve($price->product);

                SubscriptionPlan::create([
                    'stripe_product_id' => $product->id,
                    'stripe_price_id' => $price->id,
                    'name' => 'Pro',
                    'slug' => 'pro',
                    'amount' => $price->unit_amount,
                    'currency' => $price->currency,
                    'interval' => $price->recurring->interval,
                    'interval_count' => $price->recurring->interval_count,
                    'server_limit' => config('subscription.plans.pro.server_limit'),
                    'features' => config('subscription.plans.pro.features'),
                    'is_active' => true,
                    'sort_order' => 2,
                ]);

                $this->command->info('✓ Created Pro Yearly plan');
            } catch (\Exception $e) {
                $this->command->error('✗ Failed to create Pro Yearly plan: '.$e->getMessage());
            }
        } else {
            $this->command->warn('⚠ Skipping Pro Yearly - STRIPE_PRICE_PRO_YEARLY not set in .env');
        }

        // Enterprise Monthly Plan
        if ($priceId = config('subscription.plans.enterprise.monthly_price_id')) {
            try {
                $price = $stripe->prices->retrieve($priceId);
                $product = $stripe->products->retrieve($price->product);

                SubscriptionPlan::create([
                    'stripe_product_id' => $product->id,
                    'stripe_price_id' => $price->id,
                    'name' => 'Enterprise',
                    'slug' => 'enterprise',
                    'amount' => $price->unit_amount,
                    'currency' => $price->currency,
                    'interval' => $price->recurring->interval,
                    'interval_count' => $price->recurring->interval_count,
                    'server_limit' => config('subscription.plans.enterprise.server_limit'),
                    'features' => config('subscription.plans.enterprise.features'),
                    'is_active' => true,
                    'sort_order' => 3,
                ]);

                $this->command->info('✓ Created Enterprise Monthly plan');
            } catch (\Exception $e) {
                $this->command->error('✗ Failed to create Enterprise Monthly plan: '.$e->getMessage());
            }
        } else {
            $this->command->warn('⚠ Skipping Enterprise Monthly - STRIPE_PRICE_ENTERPRISE_MONTHLY not set in .env');
        }

        // Enterprise Yearly Plan
        if ($priceId = config('subscription.plans.enterprise.yearly_price_id')) {
            try {
                $price = $stripe->prices->retrieve($priceId);
                $product = $stripe->products->retrieve($price->product);

                SubscriptionPlan::create([
                    'stripe_product_id' => $product->id,
                    'stripe_price_id' => $price->id,
                    'name' => 'Enterprise',
                    'slug' => 'enterprise',
                    'amount' => $price->unit_amount,
                    'currency' => $price->currency,
                    'interval' => $price->recurring->interval,
                    'interval_count' => $price->recurring->interval_count,
                    'server_limit' => config('subscription.plans.enterprise.server_limit'),
                    'features' => config('subscription.plans.enterprise.features'),
                    'is_active' => true,
                    'sort_order' => 4,
                ]);

                $this->command->info('✓ Created Enterprise Yearly plan');
            } catch (\Exception $e) {
                $this->command->error('✗ Failed to create Enterprise Yearly plan: '.$e->getMessage());
            }
        } else {
            $this->command->warn('⚠ Skipping Enterprise Yearly - STRIPE_PRICE_ENTERPRISE_YEARLY not set in .env');
        }

        $count = SubscriptionPlan::count();
        $this->command->info("\n✓ Seeded {$count} subscription plans successfully!");
    }
}
