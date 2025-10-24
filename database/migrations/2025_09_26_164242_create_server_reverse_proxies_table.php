<?php

use App\Enums\TaskStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_reverse_proxies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->onDelete('cascade');
            $table->string('type'); // nginx, apache, caddy
            $table->string('version')->nullable(); // e.g., "1.24.0"
            $table->string('worker_processes')->default('auto'); // auto or number
            $table->unsignedInteger('worker_connections')->default(1024);
            $table->string('status')->default(TaskStatus::Installing->value);
            $table->timestamps();

            $table->unique('server_id'); // Only one reverse proxy per server
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_reverse_proxies');
    }
};
