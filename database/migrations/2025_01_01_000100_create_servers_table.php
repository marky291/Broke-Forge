<?php

use App\Provision\Enums\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('vanity_name');
            $table->string('public_ip'); // IP address
            $table->string('private_ip')->nullable();
            $table->string('connection')->default(Connection::PENDING);
            $table->string('user')->nullable(); // optional SSH username, kept for compatibility
            $table->timestamps();

            $table->unique(['public_ip']);
            $table->index('public_ip');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
