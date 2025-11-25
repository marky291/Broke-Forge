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
        Schema::create('server_database_schemas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_database_id')->constrained('server_databases')->cascadeOnDelete();
            $table->string('name'); // Schema name (e.g., 'app_production', 'wordpress_db')
            $table->string('character_set')->default('utf8mb4');
            $table->string('collation')->default('utf8mb4_unicode_ci');
            $table->string('status')->default('pending'); // pending, creating, active, failed
            $table->text('error_log')->nullable();
            $table->timestamps();

            // Unique constraint: schema name must be unique per database service
            $table->unique(['server_database_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_database_schemas');
    }
};
