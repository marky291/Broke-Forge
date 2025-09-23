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
        Schema::table('server_packages', function (Blueprint $table) {
            $table->dropColumn(['progress_step', 'progress_total', 'progress_label']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_packages', function (Blueprint $table) {
            $table->integer('progress_step')->nullable();
            $table->integer('progress_total')->nullable();
            $table->string('progress_label')->nullable();
        });
    }
};
