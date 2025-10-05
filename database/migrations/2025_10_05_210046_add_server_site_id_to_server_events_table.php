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
        Schema::table('server_events', function (Blueprint $table) {
            $table->foreignId('server_site_id')->nullable()->after('server_id')->constrained('server_sites')->cascadeOnDelete();
            $table->index(['server_site_id', 'service_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_events', function (Blueprint $table) {
            $table->dropForeign(['server_site_id']);
            $table->dropIndex(['server_site_id', 'service_type']);
            $table->dropColumn('server_site_id');
        });
    }
};
