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
            $table->foreignId('database_id')
                ->nullable()
                ->after('available_framework_id')
                ->constrained('server_databases')
                ->onDelete('set null');

            $table->foreignId('node_id')
                ->nullable()
                ->after('database_id')
                ->constrained('server_nodes')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_sites', function (Blueprint $table) {
            $table->dropForeign(['database_id']);
            $table->dropColumn('database_id');
            $table->dropForeign(['node_id']);
            $table->dropColumn('node_id');
        });
    }
};
