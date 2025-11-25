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
            $table->boolean('is_root')->default(false)->after('server_database_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_database_users', function (Blueprint $table) {
            $table->dropColumn('is_root');
        });
    }
};
