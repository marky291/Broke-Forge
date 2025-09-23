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
        Schema::rename('provision_events', 'server_package_events');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('server_package_events', 'provision_events');
    }
};
