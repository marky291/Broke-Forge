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
        Schema::create('server_site_command_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
            $table->foreignId('server_site_id')->constrained('server_sites')->cascadeOnDelete();
            $table->text('command');
            $table->longText('output')->nullable();
            $table->longText('error_output')->nullable();
            $table->integer('exit_code')->nullable();
            $table->integer('duration_ms')->default(0);
            $table->boolean('success')->default(false);
            $table->timestamps();

            $table->index(['server_site_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_site_command_history');
    }
};
