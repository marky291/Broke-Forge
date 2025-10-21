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
        Schema::table('server_firewall_rules', function (Blueprint $table) {
            $table->text('error_message')->nullable()->after('status');
        });

        Schema::table('server_phps', function (Blueprint $table) {
            $table->text('error_message')->nullable()->after('status');
        });

        Schema::table('server_scheduled_tasks', function (Blueprint $table) {
            $table->text('error_message')->nullable()->after('status');
        });

        Schema::table('server_supervisor_tasks', function (Blueprint $table) {
            $table->text('error_message')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_firewall_rules', function (Blueprint $table) {
            $table->dropColumn('error_message');
        });

        Schema::table('server_phps', function (Blueprint $table) {
            $table->dropColumn('error_message');
        });

        Schema::table('server_scheduled_tasks', function (Blueprint $table) {
            $table->dropColumn('error_message');
        });

        Schema::table('server_supervisor_tasks', function (Blueprint $table) {
            $table->dropColumn('error_message');
        });
    }
};
