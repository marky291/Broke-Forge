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
        Schema::table('server_database_users', function (Blueprint $table) {
            $table->string('update_status')->nullable()->after('status');
            $table->text('update_error_log')->nullable()->after('error_log');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_database_users', function (Blueprint $table) {
            $table->dropColumn(['update_status', 'update_error_log']);
        });
    }
};
