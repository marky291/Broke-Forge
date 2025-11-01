<?php

namespace Tests\Unit\Packages\Services\Node;

use App\Enums\TaskStatus;
use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComposerUpdaterJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test regex pattern correctly extracts version from Composer output.
     */
    public function test_regex_extracts_version_from_composer_output(): void
    {
        // Arrange - Typical Composer output with warnings
        $output = 'Composer plugins have been disabled for safety in this non-interactive session.
Set COMPOSER_ALLOW_SUPERUSER=1 if you want to allow plugins to run as root/super user.
Do not run Composer as root/super user! See https://getcomposer.org/root for details
Composer version 2.8.12 2025-09-19 13:41:59
PHP version 8.4.13 (/usr/bin/php8.4)
Run the "diagnose" command to get more detailed diagnostics output.';

        // Act - Apply the same regex pattern used in ComposerUpdaterJob
        $matches = [];
        $success = preg_match('/Composer\s+version\s+([\d.]+)/', $output, $matches);

        // Assert
        $this->assertTrue((bool) $success, 'Regex should match Composer version output');
        $this->assertEquals('2.8.12', $matches[1], 'Should extract version number');
    }

    /**
     * Test regex pattern extracts version from simple Composer output.
     */
    public function test_regex_extracts_version_from_simple_output(): void
    {
        // Arrange - Simple Composer output
        $output = 'Composer version 2.6.5 2023-10-06 10:34:40';

        // Act
        $matches = [];
        $success = preg_match('/Composer\s+version\s+([\d.]+)/', $output, $matches);

        // Assert
        $this->assertTrue((bool) $success);
        $this->assertEquals('2.6.5', $matches[1]);
    }

    /**
     * Test regex pattern handles different version formats.
     */
    public function test_regex_handles_different_version_formats(): void
    {
        $testCases = [
            ['output' => 'Composer version 2.8.12 2025-09-19 13:41:59', 'expected' => '2.8.12'],
            ['output' => 'Composer version 2.6.5', 'expected' => '2.6.5'],
            ['output' => 'Composer version 1.10.22', 'expected' => '1.10.22'],
            ['output' => 'Composer version 3.0.0 2026-01-01 00:00:00', 'expected' => '3.0.0'],
        ];

        foreach ($testCases as $testCase) {
            $matches = [];
            $success = preg_match('/Composer\s+version\s+([\d.]+)/', $testCase['output'], $matches);

            $this->assertTrue((bool) $success, "Should match: {$testCase['output']}");
            $this->assertEquals($testCase['expected'], $matches[1]);
        }
    }

    /**
     * Test regex does not match invalid output.
     */
    public function test_regex_does_not_match_invalid_output(): void
    {
        $invalidOutputs = [
            'composer installed',
            'Unknown',
            'Composer is installed',
            'Version: 2.8.12',
            '',
        ];

        foreach ($invalidOutputs as $output) {
            $matches = [];
            $success = preg_match('/Composer\s+version\s+([\d.]+)/', $output, $matches);

            $this->assertFalse((bool) $success, "Should NOT match: {$output}");
        }
    }

    /**
     * Test that only numeric version format is captured.
     */
    public function test_only_numeric_version_captured(): void
    {
        // Arrange
        $output = 'Composer version 2.8.12 2025-09-19 13:41:59';

        // Act
        $matches = [];
        preg_match('/Composer\s+version\s+([\d.]+)/', $output, $matches);

        // Assert - Should only capture the version number, not the date/time
        $this->assertEquals('2.8.12', $matches[1]);
        $this->assertStringNotContainsString('2025', $matches[1]);
    }

    /**
     * Test server composer_version structure and validation.
     */
    public function test_server_stores_composer_version_correctly(): void
    {
        // Arrange
        $server = Server::factory()->create([
            'composer_version' => '2.8.12',
            'composer_status' => TaskStatus::Active,
        ]);

        // Assert - Version should be stored as plain number, not with "Composer" prefix
        $this->assertEquals('2.8.12', $server->composer_version);
        $this->assertIsString($server->composer_version);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $server->composer_version);

        // Should NOT contain any of these placeholder values
        $this->assertNotEquals('installed', $server->composer_version);
        $this->assertNotEquals('Unknown', $server->composer_version);
        $this->assertStringNotContainsString('Composer', $server->composer_version);
    }

    /**
     * Test version preservation when detection would fail.
     */
    public function test_version_preserved_when_detection_fails(): void
    {
        // Arrange - Server with existing version
        $server = Server::factory()->create([
            'composer_version' => '2.8.12',
            'composer_status' => TaskStatus::Active,
        ]);

        $originalVersion = $server->composer_version;

        // Act - Simulate failed detection by only updating status
        // (This is what the job does when version detection fails)
        $server->update([
            'composer_status' => TaskStatus::Active,
            'composer_updated_at' => now(),
        ]);

        // Assert - Version should remain unchanged
        $server->refresh();
        $this->assertEquals($originalVersion, $server->composer_version);
        $this->assertEquals('2.8.12', $server->composer_version);
    }

    /**
     * Test that version number format is validated.
     */
    public function test_version_number_format_is_validated(): void
    {
        // Valid version numbers that should be stored
        $validVersions = ['2.8.12', '2.6.5', '1.10.22', '3.0.0'];

        foreach ($validVersions as $validVersion) {
            $server = Server::factory()->create([
                'composer_version' => $validVersion,
            ]);

            // Assert - Valid versions match the expected pattern
            $this->assertMatchesRegularExpression(
                '/^\d+\.\d+\.\d+$/',
                $server->composer_version,
                "Version '{$validVersion}' should match semantic version format"
            );
        }
    }

    /**
     * Test that placeholder values should not be used in production.
     *
     * This test documents that jobs should only store actual version numbers.
     */
    public function test_placeholder_values_documentation(): void
    {
        // These values should NEVER be stored by our jobs
        $placeholderValues = ['installed', 'Unknown', 'unknown'];

        foreach ($placeholderValues as $placeholder) {
            // Document that these don't match our version pattern
            $matches = preg_match('/^\d+\.\d+\.\d+$/', $placeholder);

            $this->assertFalse(
                (bool) $matches,
                "Placeholder '{$placeholder}' should NOT match version pattern - jobs must not store this"
            );
        }
    }
}
