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
        Schema::create('server_scheduled_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // User-friendly name
            $table->text('command'); // Command to execute
            $table->string('frequency'); // ScheduleFrequency enum
            $table->string('cron_expression')->nullable(); // For custom frequency
            $table->string('status')->default('active'); // TaskStatus enum
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->boolean('send_notifications')->default(false);
            $table->integer('timeout')->default(300); // Max execution time in seconds
            $table->timestamps();

            $table->index(['server_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_scheduled_tasks');
    }
};
