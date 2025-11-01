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
            $table->string('composer_version')->nullable()->after('updated_at');
            $table->string('composer_status')->nullable()->after('composer_version');
            $table->text('composer_error_log')->nullable()->after('composer_status');
            $table->timestamp('composer_updated_at')->nullable()->after('composer_error_log');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['composer_version', 'composer_status', 'composer_error_log', 'composer_updated_at']);
        });
    }
};
