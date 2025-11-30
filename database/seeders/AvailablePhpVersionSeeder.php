<?php

namespace Database\Seeders;

use App\Models\AvailablePhpVersion;
use Illuminate\Database\Seeder;

class AvailablePhpVersionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $versions = [
            [
                'version' => '8.1',
                'display_name' => 'PHP 8.1',
                'is_default' => false,
                'is_deprecated' => false,
                'eol_date' => '2025-12-31',
                'sort_order' => 1,
            ],
            [
                'version' => '8.2',
                'display_name' => 'PHP 8.2',
                'is_default' => false,
                'is_deprecated' => false,
                'eol_date' => '2026-12-31',
                'sort_order' => 2,
            ],
            [
                'version' => '8.3',
                'display_name' => 'PHP 8.3',
                'is_default' => false,
                'is_deprecated' => false,
                'eol_date' => '2027-12-31',
                'sort_order' => 3,
            ],
            [
                'version' => '8.4',
                'display_name' => 'PHP 8.4',
                'is_default' => true,
                'is_deprecated' => false,
                'eol_date' => '2028-12-31',
                'sort_order' => 4,
            ],
            [
                'version' => '8.5',
                'display_name' => 'PHP 8.5',
                'is_default' => false,
                'is_deprecated' => false,
                'eol_date' => null,
                'sort_order' => 5,
            ],
        ];

        foreach ($versions as $version) {
            AvailablePhpVersion::updateOrCreate(
                ['version' => $version['version']],
                $version
            );
            $this->command->info("✓ Created/Updated {$version['display_name']}");
        }

        $this->command->info("\n✓ Seeded 5 PHP versions successfully!");
    }
}
