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
        Schema::create('available_php_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version')->unique();
            $table->string('display_name');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_deprecated')->default(false);
            $table->date('eol_date')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('available_php_versions');
    }
};
