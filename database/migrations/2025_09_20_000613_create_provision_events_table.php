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
        Schema::create('provision_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->onDelete('cascade');
            $table->string('service_type'); // e.g., 'mysql', 'nginx', 'php'
            $table->enum('provision_type', ['install', 'uninstall']); // provision or deprovision
            $table->string('milestone'); // current milestone name
            $table->integer('current_step'); // current step number
            $table->integer('total_steps'); // total number of steps
            $table->text('details')->nullable(); // additional JSON data if needed
            $table->timestamps();

            // Index for faster queries when fetching events for a specific server/service
            $table->index(['server_id', 'service_type']);
            $table->index(['server_id', 'provision_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provision_events');
    }
};
