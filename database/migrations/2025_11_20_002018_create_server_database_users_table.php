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
        Schema::create('server_database_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_database_id')->constrained('server_databases')->cascadeOnDelete();
            $table->string('username'); // Database username
            $table->text('password'); // Encrypted database password
            $table->string('host')->default('%'); // Host access (%, localhost, specific IP)
            $table->string('privileges')->default('all'); // all, read_only, read_write
            $table->string('status')->default('pending'); // pending, creating, active, failed, updating
            $table->text('error_log')->nullable();
            $table->timestamps();

            // Unique constraint: username+host must be unique per database service
            $table->unique(['server_database_id', 'username', 'host']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_database_users');
    }
};
