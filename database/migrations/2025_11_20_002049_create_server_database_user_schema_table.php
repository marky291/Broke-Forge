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
        Schema::create('server_database_user_schema', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_database_user_id')->constrained('server_database_users')->cascadeOnDelete();
            $table->foreignId('server_database_schema_id')->constrained('server_database_schemas')->cascadeOnDelete();
            $table->timestamps();

            // Unique constraint: prevent duplicate user-schema assignments
            $table->unique(['server_database_user_id', 'server_database_schema_id'], 'user_schema_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_database_user_schema');
    }
};
