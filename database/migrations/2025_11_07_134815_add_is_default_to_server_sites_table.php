<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('server_sites', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('status');
            $table->index('is_default');
        });

        // Set existing default sites (domain='default') to is_default=true
        DB::table('server_sites')
            ->where('domain', 'default')
            ->update(['is_default' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_sites', function (Blueprint $table) {
            $table->dropIndex(['is_default']);
            $table->dropColumn('is_default');
        });
    }
};
