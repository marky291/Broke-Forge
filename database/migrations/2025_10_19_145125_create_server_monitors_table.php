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
        Schema::create('server_monitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // User-friendly name like "High CPU Alert"
            $table->enum('metric_type', ['cpu', 'memory', 'storage']);
            $table->enum('operator', ['>', '<', '>=', '<=', '==']);
            $table->decimal('threshold', 5, 2); // e.g., 90.00 for 90%
            $table->unsignedInteger('duration_minutes'); // How long condition must persist
            $table->json('notification_emails'); // Array of email addresses
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('cooldown_minutes')->default(60); // Don't re-alert for 60 mins
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamp('last_recovered_at')->nullable();
            $table->enum('status', ['normal', 'triggered'])->default('normal');
            $table->timestamps();

            $table->index(['server_id', 'enabled']);
            $table->index('last_triggered_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_monitors');
    }
};
