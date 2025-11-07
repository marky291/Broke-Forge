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
        Schema::table('server_deployments', function (Blueprint $table) {
            $table->string('deployment_path')->nullable()->after('deployment_script');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_deployments', function (Blueprint $table) {
            $table->dropColumn('deployment_path');
        });
    }
};
