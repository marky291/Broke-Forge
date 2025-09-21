<?php

use App\Models\User;
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
        if (Schema::hasColumn('servers', 'user')) {
            Schema::table('servers', function (Blueprint $table): void {
                $table->dropColumn('user');
            });
        }

        Schema::table('servers', function (Blueprint $table): void {
            $table
                ->foreignIdFor(User::class)
                ->nullable()
                ->after('connection')
                ->constrained()
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('servers', function (Blueprint $table): void {
            $table->string('user')->nullable()->after('connection');
        });
    }
};
