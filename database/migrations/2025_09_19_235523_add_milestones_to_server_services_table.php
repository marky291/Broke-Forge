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
            $table->json('milestones')->nullable()->after('configuration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_services', function (Blueprint $table) {
            $table->dropColumn('milestones');
        });
    }
};
