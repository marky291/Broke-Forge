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
            $table->boolean('has_dedicated_deploy_key')->default(false)->after('deprovisioned_at');
            $table->string('dedicated_deploy_key_id')->nullable()->after('has_dedicated_deploy_key');
            $table->string('dedicated_deploy_key_title')->nullable()->after('dedicated_deploy_key_id');
            $table->index('has_dedicated_deploy_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_sites', function (Blueprint $table) {
            $table->dropIndex(['has_dedicated_deploy_key']);
            $table->dropColumn(['has_dedicated_deploy_key', 'dedicated_deploy_key_id', 'dedicated_deploy_key_title']);
        });
    }
};
