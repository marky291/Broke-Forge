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
            $table->string('supervisor_status')->nullable()->after('scheduler_uninstalled_at');
            $table->timestamp('supervisor_installed_at')->nullable()->after('supervisor_status');
            $table->timestamp('supervisor_uninstalled_at')->nullable()->after('supervisor_installed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['supervisor_status', 'supervisor_installed_at', 'supervisor_uninstalled_at']);
        });
    }
};
