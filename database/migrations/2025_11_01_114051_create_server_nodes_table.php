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
        Schema::create('server_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->onDelete('cascade');
            $table->string('version'); // e.g., "22", "20", "18", "16"
            $table->boolean('is_default')->default(false);
            $table->string('status')->default(\App\Enums\TaskStatus::Installing->value);
            $table->text('error_log')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['server_id', 'is_default']);
            $table->unique(['server_id', 'version']); // Each version can only be installed once per server
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_nodes');
    }
};
