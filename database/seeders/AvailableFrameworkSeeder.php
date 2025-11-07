<?php

namespace Database\Seeders;

use App\Models\AvailableFramework;
use Illuminate\Database\Seeder;

class AvailableFrameworkSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing frameworks (use delete instead of truncate to avoid foreign key constraint issues)
        AvailableFramework::query()->delete();

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
            $this->command->info("✓ Created {$framework['name']} framework");
        }

        $this->command->info("\n✓ Seeded 4 frameworks successfully!");
    }
}
