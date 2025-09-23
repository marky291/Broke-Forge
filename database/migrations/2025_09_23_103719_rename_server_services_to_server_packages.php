<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('server_services', 'server_packages');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('server_packages', 'server_services');
    }
};
