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
        Schema::table('server_credentials', function (Blueprint $table) {
            $table->renameColumn('credential_type', 'user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_credentials', function (Blueprint $table) {
            $table->renameColumn('user', 'credential_type');
        });
    }
};
