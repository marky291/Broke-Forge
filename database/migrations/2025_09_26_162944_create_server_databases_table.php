<?php

use App\Enums\DatabaseStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_databases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->onDelete('cascade');
            $table->string('name'); // Database instance name
            $table->string('type'); // mysql, mariadb, postgresql, mongodb, redis
            $table->string('version'); // e.g., "8.0.35", "10.11.5", "15.4"
            $table->unsignedInteger('port');
            $table->string('status')->default(DatabaseStatus::Installing->value);
            $table->text('root_password')->nullable(); // Encrypted
            $table->timestamps();

            $table->index('status');
            $table->index(['server_id', 'type']);
            $table->unique(['server_id', 'port']); // Each port can only be used once per server
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_databases');
    }
};
