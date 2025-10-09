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
            $table->string('commit_message')->nullable()->after('commit_sha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_deployments', function (Blueprint $table) {
            $table->dropColumn('commit_message');
        });
    }
};
