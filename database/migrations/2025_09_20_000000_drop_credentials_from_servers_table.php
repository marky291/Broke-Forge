<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('servers', 'sudo_password')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->dropColumn('sudo_password');
            });
        }

        if (Schema::hasColumn('servers', 'db_password')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->dropColumn('db_password');
            });
        }
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            if (! Schema::hasColumn('servers', 'sudo_password')) {
                $table->string('sudo_password', 191)->nullable();
            }

            if (! Schema::hasColumn('servers', 'db_password')) {
                $table->string('db_password', 191)->nullable();
            }
        });
    }
};
