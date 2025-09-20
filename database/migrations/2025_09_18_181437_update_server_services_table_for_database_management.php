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
        Schema::table('server_services', function (Blueprint $table) {
            $table->string('service_type')->default('unknown')->after('service_name');
            $table->timestamp('uninstalled_at')->nullable()->after('installed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_services', function (Blueprint $table) {
            $table->dropColumn(['service_type', 'uninstalled_at']);
        });
    }
};
