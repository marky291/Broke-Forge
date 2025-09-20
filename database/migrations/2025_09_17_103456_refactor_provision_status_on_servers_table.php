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
            $table->renameColumn('provision_status', 'provision_setup_status');
            $table->string('provision_services_status')->default('pending')->after('provision_setup_status');
            $table->index('provision_services_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropIndex(['provision_services_status']);
            $table->dropColumn('provision_services_status');
            $table->renameColumn('provision_setup_status', 'provision_status');
        });
    }
};
