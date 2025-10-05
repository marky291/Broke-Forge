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
        Schema::table('servers', function (Blueprint $table) {
            $table->boolean('counted_in_subscription')->default(true)->after('scheduler_token');
            $table->timestamp('subscription_counted_at')->nullable()->after('counted_in_subscription');

            $table->index('counted_in_subscription');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropIndex(['counted_in_subscription']);
            $table->dropColumn(['counted_in_subscription', 'subscription_counted_at']);
        });
    }
};
