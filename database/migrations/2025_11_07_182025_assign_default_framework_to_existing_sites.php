<?php

use App\Models\AvailableFramework;
use App\Models\ServerSite;
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
        // Ensure frameworks are seeded
        if (AvailableFramework::count() === 0) {
            $this->seedFrameworks();
        }

        // Get Laravel framework (or first available framework as fallback)
        $defaultFramework = AvailableFramework::where('slug', 'laravel')->first()
            ?? AvailableFramework::first();

        if (! $defaultFramework) {
            throw new \RuntimeException('No frameworks available. Please run AvailableFrameworkSeeder first.');
        }

        // Assign default framework to all sites with null framework
        ServerSite::whereNull('available_framework_id')
            ->update(['available_framework_id' => $defaultFramework->id]);

        // Drop the existing foreign key constraint
        Schema::table('server_sites', function (Blueprint $table) {
            $table->dropForeign(['available_framework_id']);
        });

        // Make the column NOT NULL with a default value
        Schema::table('server_sites', function (Blueprint $table) use ($defaultFramework) {
            $table->foreignId('available_framework_id')
                ->nullable(false)
                ->default($defaultFramework->id)
                ->change();
        });

        // Recreate the foreign key constraint with RESTRICT instead of SET NULL
        Schema::table('server_sites', function (Blueprint $table) {
            $table->foreign('available_framework_id')
                ->references('id')
                ->on('available_frameworks')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the RESTRICT foreign key
        Schema::table('server_sites', function (Blueprint $table) {
            $table->dropForeign(['available_framework_id']);
        });

        // Make the column nullable again
        Schema::table('server_sites', function (Blueprint $table) {
            $table->foreignId('available_framework_id')
                ->nullable()
                ->change();
        });

        // Recreate the foreign key constraint with SET NULL
        Schema::table('server_sites', function (Blueprint $table) {
            $table->foreign('available_framework_id')
                ->references('id')
                ->on('available_frameworks')
                ->onDelete('set null');
        });
    }

    /**
     * Seed the frameworks if they don't exist.
     */
    protected function seedFrameworks(): void
    {
        $frameworks = [
            [
                'name' => 'Laravel',
                'slug' => 'laravel',
                'env' => [
                    'file_path' => '.env',
                    'supports' => true,
                ],
                'requirements' => [
                    'database' => true,
                    'redis' => true,
                    'nodejs' => true,
                    'composer' => true,
                ],
                'description' => 'Laravel PHP framework with full-stack capabilities',
            ],
            [
                'name' => 'WordPress',
                'slug' => 'wordpress',
                'env' => [
                    'file_path' => 'wp-config.php',
                    'supports' => true,
                ],
                'requirements' => [
                    'database' => true,
                    'redis' => false,
                    'nodejs' => false,
                    'composer' => false,
                ],
                'description' => 'WordPress CMS with PHP and MySQL',
            ],
            [
                'name' => 'Generic PHP',
                'slug' => 'generic-php',
                'env' => [
                    'file_path' => '.env',
                    'supports' => true,
                ],
                'requirements' => [
                    'database' => false,
                    'redis' => false,
                    'nodejs' => false,
                    'composer' => true,
                ],
                'description' => 'Generic PHP application with Composer',
            ],
            [
                'name' => 'Static HTML',
                'slug' => 'static-html',
                'env' => [
                    'file_path' => null,
                    'supports' => false,
                ],
                'requirements' => [
                    'database' => false,
                    'redis' => false,
                    'nodejs' => false,
                    'composer' => false,
                ],
                'description' => 'Static HTML/CSS/JS website',
            ],
        ];

        foreach ($frameworks as $framework) {
            AvailableFramework::create($framework);
        }
    }
};
