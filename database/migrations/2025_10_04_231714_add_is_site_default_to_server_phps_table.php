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
        Schema::table('server_phps', function (Blueprint $table) {
            $table->boolean('is_site_default')->default(false)->after('is_cli_default');
            $table->index(['server_id', 'is_site_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_phps', function (Blueprint $table) {
            $table->dropIndex(['server_id', 'is_site_default']);
            $table->dropColumn('is_site_default');
        });
    }
};
