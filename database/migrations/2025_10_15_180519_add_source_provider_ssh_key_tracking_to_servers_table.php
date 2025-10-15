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
        Schema::table('servers', function (Blueprint $table) {
            $table->boolean('source_provider_ssh_key_added')->default(false)->after('supervisor_uninstalled_at');
            $table->string('source_provider_ssh_key_id')->nullable()->after('source_provider_ssh_key_added');
            $table->string('source_provider_ssh_key_title')->nullable()->after('source_provider_ssh_key_id');
            $table->index('source_provider_ssh_key_added');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropIndex(['source_provider_ssh_key_added']);
            $table->dropColumn(['source_provider_ssh_key_added', 'source_provider_ssh_key_id', 'source_provider_ssh_key_title']);
        });
    }
};
