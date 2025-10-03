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
            $table->boolean('auto_deploy_enabled')->default(false)->after('last_deployed_at');
            $table->string('webhook_id')->nullable()->after('auto_deploy_enabled');
            $table->string('webhook_secret')->nullable()->after('webhook_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_sites', function (Blueprint $table) {
            $table->dropColumn(['auto_deploy_enabled', 'webhook_id', 'webhook_secret']);
        });
    }
};
