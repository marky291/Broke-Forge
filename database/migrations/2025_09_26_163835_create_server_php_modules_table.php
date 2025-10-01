<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_php_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_php_id')->constrained()->onDelete('cascade');
            $table->string('name'); // e.g., "gd", "mbstring", "curl", "xml"
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index('is_enabled');
            $table->unique(['server_php_id', 'name']); // Each module can only exist once per PHP installation
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_php_modules');
    }
};
