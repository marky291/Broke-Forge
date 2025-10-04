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
        Schema::create('server_scheduled_task_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_scheduled_task_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->integer('exit_code')->nullable();
            $table->text('output')->nullable();
            $table->text('error_output')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['server_scheduled_task_id', 'started_at'], 'task_runs_task_started_idx');
            $table->index('started_at', 'task_runs_started_idx'); // For cleanup queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_scheduled_task_runs');
    }
};
