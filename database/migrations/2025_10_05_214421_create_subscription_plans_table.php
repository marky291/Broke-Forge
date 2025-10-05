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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
