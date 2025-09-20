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
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->onDelete('cascade');
            $table->string('domain');
            $table->string('document_root');
            $table->string('php_version')->default('8.3');
            $table->boolean('ssl_enabled')->default(false);
            $table->string('ssl_cert_path')->nullable();
            $table->string('ssl_key_path')->nullable();
            $table->string('nginx_config_path');
            $table->string('status')->default('provisioning');
            $table->json('configuration')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamp('deprovisioned_at')->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'domain']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
