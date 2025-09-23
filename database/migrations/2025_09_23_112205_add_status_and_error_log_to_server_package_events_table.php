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
        Schema::table('server_package_events', function (Blueprint $table) {
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending')->after('details');
            $table->text('error_log')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_package_events', function (Blueprint $table) {
            $table->dropColumn(['status', 'error_log']);
        });
    }
};
