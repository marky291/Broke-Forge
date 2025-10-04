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
        Schema::table('server_schedulers', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('server_scheduled_tasks', function (Blueprint $table) {
            $table->index('status');
            $table->index('last_run_at');
        });

        Schema::table('server_scheduled_task_runs', function (Blueprint $table) {
            $table->index('exit_code', 'task_runs_exit_code_idx');
            $table->index(['server_id', 'started_at'], 'task_runs_server_started_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_schedulers', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('server_scheduled_tasks', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['last_run_at']);
        });

        Schema::table('server_scheduled_task_runs', function (Blueprint $table) {
            $table->dropIndex('task_runs_exit_code_idx');
            $table->dropIndex('task_runs_server_started_idx');
        });
    }
};
