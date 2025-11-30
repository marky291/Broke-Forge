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
        Schema::table('server_databases', function (Blueprint $table) {
            $table->renameColumn('type', 'engine');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_databases', function (Blueprint $table) {
            $table->renameColumn('engine', 'type');
        });
    }
};
