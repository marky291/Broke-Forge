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
        Schema::table('server_sites', function (Blueprint $table) {
            $table->foreignId('active_deployment_id')
                ->nullable()
                ->after('last_deployed_at')
                ->constrained('server_deployments')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_sites', function (Blueprint $table) {
            $table->dropConstrainedForeignId('active_deployment_id');
        });
    }
};
