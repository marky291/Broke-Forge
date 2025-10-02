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
            // Try to drop index if it exists (for non-SQLite databases)
            try {
                $table->dropIndex(['provision_services_status']);
            } catch (\Exception $e) {
                // Index might not exist or we're using SQLite - continue
            }

            // Drop columns if they exist
            $columnsToRemove = [];
            if (Schema::hasColumn('servers', 'provision_setup_status')) {
                $columnsToRemove[] = 'provision_setup_status';
            }
            if (Schema::hasColumn('servers', 'provision_services_status')) {
                $columnsToRemove[] = 'provision_services_status';
            }
            if (Schema::hasColumn('servers', 'provision_run_id')) {
                $columnsToRemove[] = 'provision_run_id';
            }

            if (! empty($columnsToRemove)) {
                $table->dropColumn($columnsToRemove);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->string('provision_setup_status')->default('pending')->after('status');
            $table->string('provision_services_status')->default('pending')->after('provision_setup_status');
            $table->uuid('provision_run_id')->nullable()->after('provision_services_status');
            $table->index('provision_services_status');
        });
    }
};
