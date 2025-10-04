<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add scheduler columns to servers table
        Schema::table('servers', function (Blueprint $table) {
            $table->string('scheduler_status')->nullable()->after('scheduler_token');
            $table->timestamp('scheduler_installed_at')->nullable()->after('scheduler_status');
            $table->timestamp('scheduler_uninstalled_at')->nullable()->after('scheduler_installed_at');

            $table->string('monitoring_status')->nullable()->after('monitoring_token');
            $table->integer('monitoring_collection_interval')->nullable()->after('monitoring_status');
            $table->timestamp('monitoring_installed_at')->nullable()->after('monitoring_collection_interval');
            $table->timestamp('monitoring_uninstalled_at')->nullable()->after('monitoring_installed_at');

            // Add indexes for status columns
            $table->index('scheduler_status');
            $table->index('monitoring_status');
        });

        // Migrate data from server_schedulers
        if (Schema::hasTable('server_schedulers')) {
            DB::table('server_schedulers')->orderBy('id')->chunk(100, function ($schedulers) {
                foreach ($schedulers as $scheduler) {
                    DB::table('servers')
                        ->where('id', $scheduler->server_id)
                        ->update([
                            'scheduler_status' => $scheduler->status,
                            'scheduler_installed_at' => $scheduler->installed_at,
                            'scheduler_uninstalled_at' => $scheduler->uninstalled_at,
                        ]);
                }
            });
        }

        // Migrate data from server_monitorings
        if (Schema::hasTable('server_monitorings')) {
            DB::table('server_monitorings')->orderBy('id')->chunk(100, function ($monitorings) {
                foreach ($monitorings as $monitoring) {
                    DB::table('servers')
                        ->where('id', $monitoring->server_id)
                        ->update([
                            'monitoring_status' => $monitoring->status,
                            'monitoring_collection_interval' => $monitoring->collection_interval,
                            'monitoring_installed_at' => $monitoring->installed_at,
                            'monitoring_uninstalled_at' => $monitoring->uninstalled_at,
                        ]);
                }
            });
        }

        // Drop old tables
        Schema::dropIfExists('server_schedulers');
        Schema::dropIfExists('server_monitorings');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate server_schedulers table
        Schema::create('server_schedulers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('installing');
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('uninstalled_at')->nullable();
            $table->timestamps();
        });

        // Recreate server_monitorings table
        Schema::create('server_monitorings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('installing');
            $table->integer('collection_interval')->default(300);
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('uninstalled_at')->nullable();
            $table->timestamps();

            $table->index('server_id');
            $table->index('status');
        });

        // Migrate data back from servers table to scheduler
        DB::table('servers')
            ->whereNotNull('scheduler_status')
            ->orderBy('id')
            ->chunk(100, function ($servers) {
                foreach ($servers as $server) {
                    DB::table('server_schedulers')->insert([
                        'server_id' => $server->id,
                        'status' => $server->scheduler_status,
                        'installed_at' => $server->scheduler_installed_at,
                        'uninstalled_at' => $server->scheduler_uninstalled_at,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

        // Migrate data back from servers table to monitoring
        DB::table('servers')
            ->whereNotNull('monitoring_status')
            ->orderBy('id')
            ->chunk(100, function ($servers) {
                foreach ($servers as $server) {
                    DB::table('server_monitorings')->insert([
                        'server_id' => $server->id,
                        'status' => $server->monitoring_status,
                        'collection_interval' => $server->monitoring_collection_interval ?? 300,
                        'installed_at' => $server->monitoring_installed_at,
                        'uninstalled_at' => $server->monitoring_uninstalled_at,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

        // Drop columns from servers table
        Schema::table('servers', function (Blueprint $table) {
            $table->dropIndex(['scheduler_status']);
            $table->dropIndex(['monitoring_status']);

            $table->dropColumn([
                'scheduler_status',
                'scheduler_installed_at',
                'scheduler_uninstalled_at',
                'monitoring_status',
                'monitoring_collection_interval',
                'monitoring_installed_at',
                'monitoring_uninstalled_at',
            ]);
        });
    }
};
