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
        Schema::create('server_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->onDelete('cascade');
            $table->string('credential_type'); // 'root', 'user', 'worker'
            $table->text('private_key'); // Encrypted
            $table->text('public_key');
            $table->timestamps();

            // Ensure one credential per type per server
            $table->unique(['server_id', 'credential_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_credentials');
    }
};
