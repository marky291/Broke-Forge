<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // Check if constraints exist before dropping
            try {
                $table->dropUnique('subscription_plans_stripe_product_id_unique');
            } catch (\Exception $e) {
                // Constraint already dropped
            }

            try {
                $table->dropUnique('subscription_plans_slug_unique');
            } catch (\Exception $e) {
                // Constraint already dropped
            }

            // Add index if it doesn't exist
            $indexes = \DB::select("SHOW INDEX FROM subscription_plans WHERE Key_name = 'subscription_plans_stripe_product_id_index'");
            if (empty($indexes)) {
                $table->index('stripe_product_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropIndex(['stripe_product_id']);
            $table->unique('stripe_product_id');
            $table->unique('slug');
        });
    }
};
