<?php

namespace Tests\Unit\Services;

use App\Services\PhpConfigurationService;
use Tests\TestCase;

class PhpConfigurationServiceTest extends TestCase
{
    /**
     * Test getAvailableVersions returns all PHP versions.
     */
    public function test_get_available_versions_returns_all_php_versions(): void
    {
        // Arrange
        $service = new PhpConfigurationService;

        // Act
        $versions = $service->getAvailableVersions();

        // Assert
        $this->assertIsArray($versions);
        $this->assertCount(4, $versions);
        $this->assertArrayHasKey('8.4', $versions);
        $this->assertArrayHasKey('8.3', $versions);
        $this->assertArrayHasKey('8.2', $versions);
        $this->assertArrayHasKey('8.1', $versions);
    }

    /**
     * Test each version has required configuration keys.
     */
    public function test_each_version_has_required_configuration_keys(): void
    {
        // Arrange
        $service = new PhpConfigurationService;

        // Act
        $versions = $service->getAvailableVersions();

        // Assert
        foreach ($versions as $version => $config) {
            $this->assertArrayHasKey('name', $config, "Version {$version} missing 'name'");
            $this->assertArrayHasKey('description', $config, "Version {$version} missing 'description'");
            $this->assertArrayHasKey('status', $config, "Version {$version} missing 'status'");
            $this->assertArrayHasKey('default_modules', $config, "Version {$version} missing 'default_modules'");
            $this->assertArrayHasKey('recommended', $config, "Version {$version} missing 'recommended'");
        }
    }

    /**
     * Test PHP 8.4 is marked as stable and recommended.
     */
    public function test_php_84_is_marked_as_stable_and_recommended(): void
    {
        // Arrange
        $service = new PhpConfigurationService;

        // Act
        $versions = $service->getAvailableVersions();

        // Assert
        $this->assertEquals('stable', $versions['8.4']['status']);
        $this->assertTrue($versions['8.4']['recommended']);
        $this->assertEquals('PHP 8.4', $versions['8.4']['name']);
    }

    /**
     * Test PHP 8.3 is marked as LTS and recommended.
     */
    public function test_php_83_is_marked_as_lts_and_recommended(): void
    {
        // Arrange
        $service = new PhpConfigurationService;

        // Act
        $versions = $service->getAvailableVersions();

        // Assert
        $this->assertEquals('lts', $versions['8.3']['status']);
        $this->assertTrue($versions['8.3']['recommended']);
        $this->assertEquals('PHP 8.3', $versions['8.3']['name']);
    }

    /**
     * Test legacy versions are not recommended.
     */
    public function test_legacy_versions_are_not_recommended(): void
    {
        // Arrange
        $service = new PhpConfigurationService;

        // Act
        $versions = $service->getAvailableVersions();

        // Assert
        $this->assertFalse($versions['8.1']['recommended']);
        $this->assertEquals('legacy', $versions['8.1']['status']);
    }

    /**
     * Test getDefaultModules returns expected PHP modules.
     */
    public function test_get_default_modules_returns_expected_php_modules(): void
    {
        // Arrange
        $service = new PhpConfigurationService;

        // Act
        $modules = $service->getDefaultModules();

        // Assert
        $this->assertIsArray($modules);
        $this->assertContains('bcmath', $modules);
        $this->assertContains('curl', $modules);
        $this->assertContains('mysql', $modules);
        $this->assertContains('opcache', $modules);
        $this->assertContains('gd', $modules);
        $this->assertContains('mbstring', $modules);
        $this->assertContains('xml', $modules);
        $this->assertContains('zip', $modules);
    }

    /**
     * Test getDefaultModules returns consistent modules across versions.
     */
    public function test_get_default_modules_returns_consistent_modules_across_versions(): void
    {
        // Arrange
        $service = new PhpConfigurationService;
        $versions = $service->getAvailableVersions();

        // Act
        $defaultModules = $service->getDefaultModules();

        // Assert - All versions should have the same default modules
        foreach ($versions as $version => $config) {
            $this->assertEquals($defaultModules, $config['default_modules'],
                "Version {$version} has different default modules");
        }
    }

    /**
     * Test getOptionalModules returns modules with descriptions.
     */
    public function test_get_optional_modules_returns_modules_with_descriptions(): void
    {
        // Arrange
        $service = new PhpConfigurationService;

        // Act
        $modules = $service->getOptionalModules();

        // Assert
        $this->assertIsArray($modules);
        $this->assertArrayHasKey('redis', $modules);
        $this->assertArrayHasKey('memcached', $modules);
        $this->assertArrayHasKey('imagick', $modules);
        $this->assertArrayHasKey('xdebug', $modules);
        $this->assertArrayHasKey('mongodb', $modules);
        $this->assertArrayHasKey('ldap', $modules);

        // Check descriptions are strings
        foreach ($modules as $module => $description) {
            $this->assertIsString($description, "Module {$module} should have string description");
        }
    }

    /**
     * Test xdebug module includes production warning.
     */
    public function test_xdebug_module_includes_production_warning(): void
    {
        // Arrange
        $service = new PhpConfigurationService;

        // Act
        $modules = $service->getOptionalModules();

        // Assert
        $this->assertStringContainsString('not for production', $modules['xdebug']);
    }

    /**
     * Test getVersionConfiguration returns configuration for valid version.
     */
    public function test_get_version_configuration_returns_configuration_for_valid_version(): void
    {
        // Arrange
        $service = new PhpConfigurationService;

        // Act
        $config = $service->getVersionConfiguration('8.4');

        // Assert
        $this->assertIsArray($config);
        $this->assertEquals('PHP 8.4', $config['name']);
        $this->assertEquals('stable', $config['status']);
        $this->assertTrue($config['recommended']);
    }

    /**
     * Test getVersionConfiguration returns empty array for invalid version.
     */
    public function test_get_version_configuration_returns_empty_array_for_invalid_version(): void
    {
        // Arrange
        $service = new PhpConfigurationService;

        // Act
        $config = $service->getVersionConfiguration('7.4');

        // Assert
        $this->assertIsArray($config);
        $this->assertEmpty($config);
    }

    /**
     * Test isVersionSupported returns true for valid versions.
     */
    public function test_is_version_supported_returns_true_for_valid_versions(): void
    {
        // Arrange
        $service = new PhpConfigurationService;

        // Act & Assert
        $this->assertTrue($service->isVersionSupported('8.4'));
        $this->assertTrue($service->isVersionSupported('8.3'));
        $this->assertTrue($service->isVersionSupported('8.2'));
        $this->assertTrue($service->isVersionSupported('8.1'));
    }

    /**
     * Test isVersionSupported returns false for invalid versions.
     */
    public function test_is_version_supported_returns_false_for_invalid_versions(): void
    {
        // Arrange
        $service = new PhpConfigurationService;

        // Act & Assert
        $this->assertFalse($service->isVersionSupported('7.4'));
        $this->assertFalse($service->isVersionSupported('9.0'));
        $this->assertFalse($service->isVersionSupported('invalid'));
    }

    /**
     * Test getRecommendedVersion returns stable recommended version.
     */
    public function test_get_recommended_version_returns_stable_recommended_version(): void
    {
        // Arrange
        $service = new PhpConfigurationService;

        // Act
        $version = $service->getRecommendedVersion();

        // Assert
        $this->assertEquals('8.4', $version);
    }

    /**
     * Test getRecommendedVersion logic finds first stable recommended version.
     */
    public function test_get_recommended_version_logic_finds_first_stable_recommended_version(): void
    {
        // Arrange
        $service = new PhpConfigurationService;
        $versions = $service->getAvailableVersions();

        // Act
        $recommendedVersion = $service->getRecommendedVersion();

        // Assert
        $this->assertEquals('stable', $versions[$recommendedVersion]['status']);
        $this->assertTrue($versions[$recommendedVersion]['recommended']);
    }

    /**
     * Test getFpmConfiguration returns expected FPM pool settings.
     */
    public function test_get_fpm_configuration_returns_expected_fpm_pool_settings(): void
    {
        // Arrange
        $service = new PhpConfigurationService;

        // Act
        $config = $service->getFpmConfiguration('8.4');

        // Assert
        $this->assertIsArray($config);
        $this->assertEquals('dynamic', $config['pm']);
        $this->assertEquals(20, $config['pm.max_children']);
        $this->assertEquals(2, $config['pm.start_servers']);
        $this->assertEquals(1, $config['pm.min_spare_servers']);
        $this->assertEquals(3, $config['pm.max_spare_servers']);
        $this->assertEquals('10s', $config['pm.process_idle_timeout']);
        $this->assertEquals(1000, $config['pm.max_requests']);
    }

    /**
     * Test getFpmConfiguration returns same settings for all versions.
     */
    public function test_get_fpm_configuration_returns_same_settings_for_all_versions(): void
    {
        // Arrange
        $service = new PhpConfigurationService;

        // Act
        $config84 = $service->getFpmConfiguration('8.4');
        $config83 = $service->getFpmConfiguration('8.3');
        $config82 = $service->getFpmConfiguration('8.2');

        // Assert
        $this->assertEquals($config84, $config83);
        $this->assertEquals($config84, $config82);
    }

    /**
     * Test all supported versions have default modules.
     */
    public function test_all_supported_versions_have_default_modules(): void
    {
        // Arrange
        $service = new PhpConfigurationService;
        $versions = $service->getAvailableVersions();

        // Act & Assert
        foreach ($versions as $version => $config) {
            $this->assertNotEmpty($config['default_modules'], "Version {$version} has no default modules");
            $this->assertIsArray($config['default_modules'], "Version {$version} default_modules is not an array");
        }
    }

    /**
     * Test default modules list is not empty.
     */
    public function test_default_modules_list_is_not_empty(): void
    {
        // Arrange
        $service = new PhpConfigurationService;

        // Act
        $modules = $service->getDefaultModules();

        // Assert
        $this->assertNotEmpty($modules);
        $this->assertGreaterThan(10, count($modules)); // Should have at least 10 essential modules
    }

    /**
     * Test optional modules list is not empty.
     */
    public function test_optional_modules_list_is_not_empty(): void
    {
        // Arrange
        $service = new PhpConfigurationService;

        // Act
        $modules = $service->getOptionalModules();

        // Assert
        $this->assertNotEmpty($modules);
        $this->assertGreaterThan(3, count($modules));
    }
}
