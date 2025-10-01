<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('server_package_events', 'server_events');
    }

    public function down(): void
    {
        Schema::rename('server_events', 'server_package_events');
    }
};
