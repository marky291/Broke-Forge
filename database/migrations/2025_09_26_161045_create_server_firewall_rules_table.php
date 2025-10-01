<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_firewall_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_firewall_id')->constrained()->onDelete('cascade');
            $table->string('name'); // Required - descriptive name for the rule
            $table->string('port'); // Can be single port "80" or range "3000:3005"
            $table->string('from_ip_address')->nullable(); // null means "any"
            $table->enum('rule_type', ['allow', 'deny'])->default('allow');
            $table->enum('status', ['pending', 'installing', 'active', 'failed', 'removing'])->default('pending');
            $table->timestamps();

            $table->index('status');
            $table->index(['server_firewall_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_firewall_rules');
    }
};
