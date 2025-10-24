<?php

use App\Enums\TaskStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_phps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->onDelete('cascade');
            $table->string('version'); // e.g., "8.3", "7.4", "8.2.15"
            $table->boolean('is_cli_default')->default(false);
            $table->string('status')->default(TaskStatus::Installing->value);
            $table->timestamps();

            $table->index('status');
            $table->index(['server_id', 'is_cli_default']);
            $table->unique(['server_id', 'version']); // Each version can only be installed once per server
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_phps');
    }
};
