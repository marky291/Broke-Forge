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
            $table->unsignedInteger('progress_step')->nullable()->after('status');
            $table->unsignedInteger('progress_total')->nullable()->after('progress_step');
            $table->string('progress_label')->nullable()->after('progress_total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_services', function (Blueprint $table) {
            $table->dropColumn(['progress_step', 'progress_total', 'progress_label']);
        });
    }
};
