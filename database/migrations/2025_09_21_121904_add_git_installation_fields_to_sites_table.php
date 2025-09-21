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
        Schema::table('sites', function (Blueprint $table) {
            // Track git installation status
            $table->string('git_status')->nullable()->after('status')->index();
            $table->timestamp('git_installed_at')->nullable()->after('provisioned_at');
            $table->string('last_deployment_sha')->nullable();
            $table->timestamp('last_deployed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn([
                'git_status',
                'git_installed_at',
                'last_deployment_sha',
                'last_deployed_at',
            ]);
        });
    }
};
