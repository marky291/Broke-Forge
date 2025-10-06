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
        Schema::create('server_supervisor_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('command');
            $table->string('working_directory');
            $table->integer('processes')->default(1);
            $table->string('user')->default('brokeforge');
            $table->boolean('auto_restart')->default(true);
            $table->boolean('autorestart_unexpected')->default(true);
            $table->string('status')->default('active');
            $table->string('stdout_logfile')->nullable();
            $table->string('stderr_logfile')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('uninstalled_at')->nullable();
            $table->timestamps();

            $table->index(['server_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_supervisor_tasks');
    }
};
