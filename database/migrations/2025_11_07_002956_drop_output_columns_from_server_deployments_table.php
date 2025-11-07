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
            $table->dropColumn(['output', 'error_output']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_deployments', function (Blueprint $table) {
            $table->text('output')->nullable()->after('deployment_script');
            $table->text('error_output')->nullable()->after('output');
        });
    }
};
