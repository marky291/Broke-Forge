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
            $table->foreignId('available_framework_id')
                ->after('server_id')
                ->constrained()
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_sites', function (Blueprint $table) {
            $table->dropForeign(['available_framework_id']);
            $table->dropColumn('available_framework_id');
        });
    }
};
