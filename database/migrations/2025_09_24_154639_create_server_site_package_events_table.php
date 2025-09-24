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
        Schema::create('server_site_package_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('server_sites')->cascadeOnDelete();
            $table->string('service_type');
            $table->string('provision_type'); // 'install' or 'uninstall'
            $table->string('milestone');
            $table->integer('current_step')->default(0);
            $table->integer('total_steps')->default(0);
            $table->json('details')->nullable();
            $table->string('status')->default('pending'); // 'pending', 'success', 'failed'
            $table->text('error_log')->nullable();
            $table->timestamps();

            $table->index(['server_id', 'site_id']);
            $table->index(['site_id', 'service_type', 'provision_type'], 'site_service_provision_idx');
            $table->index(['server_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_site_package_events');
    }
};