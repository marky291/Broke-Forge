<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->integer('ssh_port')->default(22)->after('user');
            $table->string('ssh_root_user')->default('root')->after('user');
            $table->string('ssh_app_user')->after('ssh_root_user');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['ssh_root_user', 'ssh_app_user']);
        });
    }
};
