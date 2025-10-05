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
            $table->string('os_name')->nullable()->after('ssh_port'); // e.g., "Ubuntu"
            $table->string('os_version')->nullable()->after('os_name'); // e.g., "24.04"
            $table->string('os_codename')->nullable()->after('os_version'); // e.g., "noble"
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['os_name', 'os_version', 'os_codename']);
        });
    }
};
