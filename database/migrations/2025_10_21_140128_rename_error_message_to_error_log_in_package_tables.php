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
        Schema::table('server_databases', function (Blueprint $table) {
            $table->renameColumn('error_message', 'error_log');
        });

        Schema::table('server_firewall_rules', function (Blueprint $table) {
            $table->renameColumn('error_message', 'error_log');
        });

        Schema::table('server_phps', function (Blueprint $table) {
            $table->renameColumn('error_message', 'error_log');
        });

        Schema::table('server_scheduled_tasks', function (Blueprint $table) {
            $table->renameColumn('error_message', 'error_log');
        });

        Schema::table('server_supervisor_tasks', function (Blueprint $table) {
            $table->renameColumn('error_message', 'error_log');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_databases', function (Blueprint $table) {
            $table->renameColumn('error_log', 'error_message');
        });

        Schema::table('server_firewall_rules', function (Blueprint $table) {
            $table->renameColumn('error_log', 'error_message');
        });

        Schema::table('server_phps', function (Blueprint $table) {
            $table->renameColumn('error_log', 'error_message');
        });

        Schema::table('server_scheduled_tasks', function (Blueprint $table) {
            $table->renameColumn('error_log', 'error_message');
        });

        Schema::table('server_supervisor_tasks', function (Blueprint $table) {
            $table->renameColumn('error_log', 'error_message');
        });
    }
};
