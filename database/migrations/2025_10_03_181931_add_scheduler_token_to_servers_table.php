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
        Schema::table('servers', function (Blueprint $table) {
            // Encrypted strings can be 200+ characters, so using 500 to be safe
            $table->string('scheduler_token', 500)->nullable()->after('monitoring_token');
            $table->index('scheduler_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropIndex(['scheduler_token']);
            $table->dropColumn('scheduler_token');
        });
    }
};
