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
        Schema::create('server_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();

            // CPU metrics
            $table->decimal('cpu_usage', 5, 2); // 0.00 - 100.00

            // Memory metrics
            $table->bigInteger('memory_total_mb');
            $table->bigInteger('memory_used_mb');
            $table->decimal('memory_usage_percentage', 5, 2);

            // Storage metrics
            $table->bigInteger('storage_total_gb');
            $table->bigInteger('storage_used_gb');
            $table->decimal('storage_usage_percentage', 5, 2);

            $table->timestamp('collected_at');
            $table->timestamps();

            $table->index('server_id');
            $table->index('collected_at');
            $table->index(['server_id', 'collected_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_metrics');
    }
};
