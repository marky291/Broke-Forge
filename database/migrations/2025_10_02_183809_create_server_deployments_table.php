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
        Schema::create('server_deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->onDelete('cascade');
            $table->foreignId('server_site_id')->constrained('server_sites')->onDelete('cascade');
            $table->enum('status', ['pending', 'updating', 'success', 'failed'])->default('pending');
            $table->text('deployment_script'); // Commands executed
            $table->text('output')->nullable(); // Command stdout
            $table->text('error_output')->nullable(); // Command stderr
            $table->integer('exit_code')->nullable();
            $table->string('commit_sha')->nullable(); // Git commit SHA after deployment
            $table->string('branch')->nullable(); // Branch deployed
            $table->integer('duration_ms')->nullable(); // Execution time in milliseconds
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['server_site_id', 'status']);
            $table->index(['server_site_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_deployments');
    }
};
