<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->string('sudo_password', 191)->nullable()->after('provision_status');
            $table->string('db_password', 191)->nullable()->after('sudo_password');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['sudo_password', 'db_password']);
        });
    }
};
