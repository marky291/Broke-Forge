<?php

namespace Tests\Unit\Packages\Services\PHP\Services;

use App\Packages\Services\PHP\Services\PhpConfigurationService;
use Tests\TestCase;

class PhpConfigurationServiceTest extends TestCase
{
    /**
     * Test getAvailableVersions returns array of PHP versions.
     */
    public function test_get_available_versions_returns_array_of_php_versions(): void
    {
        // Act
        $versions = PhpConfigurationService::getAvailableVersions();

        // Assert
        $this->assertIsArray($versions);
        $this->assertNotEmpty($versions);
        $this->assertArrayHasKey('8.1', $versions);
        $this->assertArrayHasKey('8.2', $versions);
        $this->assertArrayHasKey('8.3', $versions);
        $this->assertArrayHasKey('8.4', $versions);
    }

    /**
     * Test getAvailableVersions returns correct format.
     */
    public function test_get_available_versions_returns_correct_format(): void
    {
        // Act
        $versions = PhpConfigurationService::getAvailableVersions();

        // Assert
        foreach ($versions as $key => $value) {
            $this->assertIsString($key);
            $this->assertIsString($value);
            $this->assertStringStartsWith('PHP ', $value);
        }
    }

    /**
     * Test getAvailableVersions includes PHP 8.4.
     */
    public function test_get_available_versions_includes_php_eight_four(): void
    {
        // Act
        $versions = PhpConfigurationService::getAvailableVersions();

        // Assert
        $this->assertEquals('PHP 8.4', $versions['8.4']);
    }

    /**
     * Test getAvailableExtensions returns array of extensions.
     */
    public function test_get_available_extensions_returns_array_of_extensions(): void
    {
        // Act
        $extensions = PhpConfigurationService::getAvailableExtensions();

        // Assert
        $this->assertIsArray($extensions);
        $this->assertNotEmpty($extensions);
    }

    /**
     * Test getAvailableExtensions includes common extensions.
     */
    public function test_get_available_extensions_includes_common_extensions(): void
    {
        // Act
        $extensions = PhpConfigurationService::getAvailableExtensions();

        // Assert - Test some common extensions
        $this->assertArrayHasKey('curl', $extensions);
        $this->assertArrayHasKey('mysql', $extensions);
        $this->assertArrayHasKey('redis', $extensions);
        $this->assertArrayHasKey('opcache', $extensions);
        $this->assertArrayHasKey('pdo', $extensions);
        $this->assertArrayHasKey('mbstring', $extensions);
    }

    /**
     * Test getAvailableExtensions returns correct format.
     */
    public function test_get_available_extensions_returns_correct_format(): void
    {
        // Act
        $extensions = PhpConfigurationService::getAvailableExtensions();

        // Assert
        foreach ($extensions as $key => $value) {
            $this->assertIsString($key, "Extension key should be string: {$key}");
            $this->assertIsString($value, "Extension value should be string for key: {$key}");
            $this->assertNotEmpty($value, "Extension description should not be empty for: {$key}");
        }
    }

    /**
     * Test getAvailableExtensions includes descriptions.
     */
    public function test_get_available_extensions_includes_descriptions(): void
    {
        // Act
        $extensions = PhpConfigurationService::getAvailableExtensions();

        // Assert - Verify specific extension descriptions
        $this->assertEquals('cURL - Client URL Library', $extensions['curl']);
        $this->assertEquals('Redis - In-memory data structure store', $extensions['redis']);
        $this->assertEquals('OPcache - Bytecode cache', $extensions['opcache']);
    }

    /**
     * Test getAvailableExtensions includes database drivers.
     */
    public function test_get_available_extensions_includes_database_drivers(): void
    {
        // Act
        $extensions = PhpConfigurationService::getAvailableExtensions();

        // Assert
        $this->assertArrayHasKey('mysql', $extensions);
        $this->assertArrayHasKey('pgsql', $extensions);
        $this->assertArrayHasKey('sqlite3', $extensions);
        $this->assertArrayHasKey('mongodb', $extensions);
    }

    /**
     * Test getAvailableExtensions includes development tools.
     */
    public function test_get_available_extensions_includes_development_tools(): void
    {
        // Act
        $extensions = PhpConfigurationService::getAvailableExtensions();

        // Assert
        $this->assertArrayHasKey('xdebug', $extensions);
    }

    /**
     * Test getDefaultSettings returns array of default PHP settings.
     */
    public function test_get_default_settings_returns_array_of_default_php_settings(): void
    {
        // Act
        $settings = PhpConfigurationService::getDefaultSettings();

        // Assert
        $this->assertIsArray($settings);
        $this->assertNotEmpty($settings);
    }

    /**
     * Test getDefaultSettings includes required settings.
     */
    public function test_get_default_settings_includes_required_settings(): void
    {
        // Act
        $settings = PhpConfigurationService::getDefaultSettings();

        // Assert
        $this->assertArrayHasKey('memory_limit', $settings);
        $this->assertArrayHasKey('max_execution_time', $settings);
        $this->assertArrayHasKey('upload_max_filesize', $settings);
        $this->assertArrayHasKey('post_max_size', $settings);
        $this->assertArrayHasKey('max_input_vars', $settings);
        $this->assertArrayHasKey('max_file_uploads', $settings);
    }

    /**
     * Test getDefaultSettings returns correct default values.
     */
    public function test_get_default_settings_returns_correct_default_values(): void
    {
        // Act
        $settings = PhpConfigurationService::getDefaultSettings();

        // Assert
        $this->assertEquals('256M', $settings['memory_limit']);
        $this->assertEquals(30, $settings['max_execution_time']);
        $this->assertEquals('2M', $settings['upload_max_filesize']);
        $this->assertEquals('8M', $settings['post_max_size']);
        $this->assertEquals(1000, $settings['max_input_vars']);
        $this->assertEquals(20, $settings['max_file_uploads']);
    }

    /**
     * Test getDefaultSettings has reasonable memory limit.
     */
    public function test_get_default_settings_has_reasonable_memory_limit(): void
    {
        // Act
        $settings = PhpConfigurationService::getDefaultSettings();

        // Assert
        $this->assertEquals('256M', $settings['memory_limit']);
        $this->assertStringEndsWith('M', $settings['memory_limit']);
    }

    /**
     * Test getDefaultSettings has reasonable execution time.
     */
    public function test_get_default_settings_has_reasonable_execution_time(): void
    {
        // Act
        $settings = PhpConfigurationService::getDefaultSettings();

        // Assert
        $this->assertIsInt($settings['max_execution_time']);
        $this->assertGreaterThan(0, $settings['max_execution_time']);
        $this->assertLessThanOrEqual(300, $settings['max_execution_time']);
    }

    /**
     * Test getValidationRules returns array of validation rules.
     */
    public function test_get_validation_rules_returns_array_of_validation_rules(): void
    {
        // Act
        $rules = PhpConfigurationService::getValidationRules();

        // Assert
        $this->assertIsArray($rules);
        $this->assertNotEmpty($rules);
    }

    /**
     * Test getValidationRules includes version validation.
     */
    public function test_get_validation_rules_includes_version_validation(): void
    {
        // Act
        $rules = PhpConfigurationService::getValidationRules();

        // Assert
        $this->assertArrayHasKey('version', $rules);
        $this->assertStringContainsString('required', $rules['version']);
        $this->assertStringContainsString('in:', $rules['version']);
    }

    /**
     * Test getValidationRules version rule includes all available versions.
     */
    public function test_get_validation_rules_version_rule_includes_all_available_versions(): void
    {
        // Act
        $rules = PhpConfigurationService::getValidationRules();
        $versions = PhpConfigurationService::getAvailableVersions();

        // Assert
        $versionRule = $rules['version'];
        foreach (array_keys($versions) as $version) {
            $this->assertStringContainsString($version, $versionRule);
        }
    }

    /**
     * Test getValidationRules includes extensions validation.
     */
    public function test_get_validation_rules_includes_extensions_validation(): void
    {
        // Act
        $rules = PhpConfigurationService::getValidationRules();

        // Assert
        $this->assertArrayHasKey('extensions', $rules);
        $this->assertArrayHasKey('extensions.*', $rules);
        $this->assertEquals('array', $rules['extensions']);
        $this->assertStringContainsString('string', $rules['extensions.*']);
        $this->assertStringContainsString('in:', $rules['extensions.*']);
    }

    /**
     * Test getValidationRules extensions rule includes all available extensions.
     */
    public function test_get_validation_rules_extensions_rule_includes_all_available_extensions(): void
    {
        // Act
        $rules = PhpConfigurationService::getValidationRules();
        $extensions = PhpConfigurationService::getAvailableExtensions();

        // Assert
        $extensionsRule = $rules['extensions.*'];
        foreach (array_keys($extensions) as $extension) {
            $this->assertStringContainsString($extension, $extensionsRule);
        }
    }

    /**
     * Test getValidationRules includes memory_limit validation.
     */
    public function test_get_validation_rules_includes_memory_limit_validation(): void
    {
        // Act
        $rules = PhpConfigurationService::getValidationRules();

        // Assert
        $this->assertArrayHasKey('memory_limit', $rules);
        $this->assertStringContainsString('nullable', $rules['memory_limit']);
        $this->assertStringContainsString('string', $rules['memory_limit']);
        $this->assertStringContainsString('regex:', $rules['memory_limit']);
    }

    /**
     * Test getValidationRules includes max_execution_time validation.
     */
    public function test_get_validation_rules_includes_max_execution_time_validation(): void
    {
        // Act
        $rules = PhpConfigurationService::getValidationRules();

        // Assert
        $this->assertArrayHasKey('max_execution_time', $rules);
        $this->assertStringContainsString('nullable', $rules['max_execution_time']);
        $this->assertStringContainsString('integer', $rules['max_execution_time']);
        $this->assertStringContainsString('min:0', $rules['max_execution_time']);
        $this->assertStringContainsString('max:300', $rules['max_execution_time']);
    }

    /**
     * Test getValidationRules includes upload_max_filesize validation.
     */
    public function test_get_validation_rules_includes_upload_max_filesize_validation(): void
    {
        // Act
        $rules = PhpConfigurationService::getValidationRules();

        // Assert
        $this->assertArrayHasKey('upload_max_filesize', $rules);
        $this->assertStringContainsString('nullable', $rules['upload_max_filesize']);
        $this->assertStringContainsString('string', $rules['upload_max_filesize']);
        $this->assertStringContainsString('regex:', $rules['upload_max_filesize']);
    }

    /**
     * Test getValidationRules includes post_max_size validation.
     */
    public function test_get_validation_rules_includes_post_max_size_validation(): void
    {
        // Act
        $rules = PhpConfigurationService::getValidationRules();

        // Assert
        $this->assertArrayHasKey('post_max_size', $rules);
        $this->assertStringContainsString('nullable', $rules['post_max_size']);
        $this->assertStringContainsString('string', $rules['post_max_size']);
        $this->assertStringContainsString('regex:', $rules['post_max_size']);
    }

    /**
     * Test getValidationRules includes is_cli_default validation.
     */
    public function test_get_validation_rules_includes_is_cli_default_validation(): void
    {
        // Act
        $rules = PhpConfigurationService::getValidationRules();

        // Assert
        $this->assertArrayHasKey('is_cli_default', $rules);
        $this->assertStringContainsString('nullable', $rules['is_cli_default']);
        $this->assertStringContainsString('boolean', $rules['is_cli_default']);
    }

    /**
     * Test all methods return consistent data.
     */
    public function test_all_methods_return_consistent_data(): void
    {
        // Act
        $versions1 = PhpConfigurationService::getAvailableVersions();
        $versions2 = PhpConfigurationService::getAvailableVersions();
        $extensions1 = PhpConfigurationService::getAvailableExtensions();
        $extensions2 = PhpConfigurationService::getAvailableExtensions();
        $settings1 = PhpConfigurationService::getDefaultSettings();
        $settings2 = PhpConfigurationService::getDefaultSettings();

        // Assert - Multiple calls return same data
        $this->assertEquals($versions1, $versions2);
        $this->assertEquals($extensions1, $extensions2);
        $this->assertEquals($settings1, $settings2);
    }

    /**
     * Test getAvailableVersions returns exactly 4 versions.
     */
    public function test_get_available_versions_returns_exactly_four_versions(): void
    {
        // Act
        $versions = PhpConfigurationService::getAvailableVersions();

        // Assert
        $this->assertCount(4, $versions);
    }

    /**
     * Test getAvailableExtensions returns more than 10 extensions.
     */
    public function test_get_available_extensions_returns_more_than_ten_extensions(): void
    {
        // Act
        $extensions = PhpConfigurationService::getAvailableExtensions();

        // Assert
        $this->assertGreaterThan(10, count($extensions));
    }

    /**
     * Test getDefaultSettings returns exactly 6 settings.
     */
    public function test_get_default_settings_returns_exactly_six_settings(): void
    {
        // Act
        $settings = PhpConfigurationService::getDefaultSettings();

        // Assert
        $this->assertCount(6, $settings);
    }

    /**
     * Test getValidationRules covers main configurable settings.
     */
    public function test_get_validation_rules_covers_main_configurable_settings(): void
    {
        // Act
        $rules = PhpConfigurationService::getValidationRules();

        // Assert - Main configurable settings should have validation rules
        $requiredRules = ['memory_limit', 'max_execution_time', 'upload_max_filesize', 'post_max_size'];
        foreach ($requiredRules as $key) {
            $this->assertArrayHasKey($key, $rules, "Validation rule missing for setting: {$key}");
        }
    }
}
