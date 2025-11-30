<?php

namespace Tests\Unit\Services;

use App\Enums\DatabaseEngine;
use App\Services\DatabaseVersionCompatibility;
use Tests\TestCase;

class DatabaseVersionCompatibilityTest extends TestCase
{
    /**
     * Test isCompatible returns true for compatible MariaDB version and Ubuntu.
     */
    public function test_is_compatible_returns_true_for_compatible_mariadb_version(): void
    {
        // Arrange
        $service = new DatabaseVersionCompatibility;

        // Act & Assert
        $this->assertTrue($service->isCompatible(DatabaseEngine::MariaDB, '11.6', 'jammy'));
        $this->assertTrue($service->isCompatible(DatabaseEngine::MariaDB, '11.6', 'noble'));
        $this->assertTrue($service->isCompatible(DatabaseEngine::MariaDB, '10.6', 'focal'));
        $this->assertTrue($service->isCompatible(DatabaseEngine::MariaDB, '10.6', 'jammy'));
    }

    /**
     * Test isCompatible returns false for incompatible MariaDB version and Ubuntu.
     */
    public function test_is_compatible_returns_false_for_incompatible_mariadb_version(): void
    {
        // Arrange
        $service = new DatabaseVersionCompatibility;

        // Act & Assert
        $this->assertFalse($service->isCompatible(DatabaseEngine::MariaDB, '11.6', 'focal'));
        $this->assertFalse($service->isCompatible(DatabaseEngine::MariaDB, '10.6', 'noble'));
    }

    /**
     * Test isCompatible returns true for compatible PostgreSQL version and Ubuntu.
     */
    public function test_is_compatible_returns_true_for_compatible_postgresql_version(): void
    {
        // Arrange
        $service = new DatabaseVersionCompatibility;

        // Act & Assert
        $this->assertTrue($service->isCompatible(DatabaseEngine::PostgreSQL, '17', 'jammy'));
        $this->assertTrue($service->isCompatible(DatabaseEngine::PostgreSQL, '17', 'noble'));
        $this->assertTrue($service->isCompatible(DatabaseEngine::PostgreSQL, '16', 'focal'));
        $this->assertTrue($service->isCompatible(DatabaseEngine::PostgreSQL, '16', 'jammy'));
        $this->assertTrue($service->isCompatible(DatabaseEngine::PostgreSQL, '13', 'bionic'));
    }

    /**
     * Test isCompatible returns false for incompatible PostgreSQL version and Ubuntu.
     */
    public function test_is_compatible_returns_false_for_incompatible_postgresql_version(): void
    {
        // Arrange
        $service = new DatabaseVersionCompatibility;

        // Act & Assert
        $this->assertFalse($service->isCompatible(DatabaseEngine::PostgreSQL, '17', 'focal'));
        $this->assertFalse($service->isCompatible(DatabaseEngine::PostgreSQL, '17', 'bionic'));
    }

    /**
     * Test isCompatible returns true for compatible MySQL version and Ubuntu.
     */
    public function test_is_compatible_returns_true_for_compatible_mysql_version(): void
    {
        // Arrange
        $service = new DatabaseVersionCompatibility;

        // Act & Assert
        $this->assertTrue($service->isCompatible(DatabaseEngine::MySQL, '8.0', 'focal'));
        $this->assertTrue($service->isCompatible(DatabaseEngine::MySQL, '8.0', 'jammy'));
        $this->assertTrue($service->isCompatible(DatabaseEngine::MySQL, '5.7', 'bionic'));
        $this->assertTrue($service->isCompatible(DatabaseEngine::MySQL, '5.7', 'focal'));
    }

    /**
     * Test isCompatible returns false for incompatible MySQL version and Ubuntu.
     */
    public function test_is_compatible_returns_false_for_incompatible_mysql_version(): void
    {
        // Arrange
        $service = new DatabaseVersionCompatibility;

        // Act & Assert
        $this->assertFalse($service->isCompatible(DatabaseEngine::MySQL, '8.0', 'bionic'));
        $this->assertFalse($service->isCompatible(DatabaseEngine::MySQL, '5.7', 'jammy'));
    }

    /**
     * Test isCompatible returns false for unknown database version.
     */
    public function test_is_compatible_returns_false_for_unknown_database_version(): void
    {
        // Arrange
        $service = new DatabaseVersionCompatibility;

        // Act & Assert
        $this->assertFalse($service->isCompatible(DatabaseEngine::MariaDB, '99.9', 'jammy'));
        $this->assertFalse($service->isCompatible(DatabaseEngine::PostgreSQL, '99', 'jammy'));
    }

    /**
     * Test getUbuntuCodenameForDatabase returns server codename when directly supported.
     */
    public function test_get_ubuntu_codename_for_database_returns_server_codename_when_directly_supported(): void
    {
        // Arrange
        $service = new DatabaseVersionCompatibility;

        // Act & Assert
        $this->assertEquals('jammy', $service->getUbuntuCodenameForDatabase(DatabaseEngine::MariaDB, '11.6', 'jammy'));
        $this->assertEquals('noble', $service->getUbuntuCodenameForDatabase(DatabaseEngine::MariaDB, '11.6', 'noble'));
        $this->assertEquals('focal', $service->getUbuntuCodenameForDatabase(DatabaseEngine::PostgreSQL, '16', 'focal'));
    }

    /**
     * Test getUbuntuCodenameForDatabase returns fallback when server codename not supported.
     */
    public function test_get_ubuntu_codename_for_database_returns_fallback_when_server_codename_not_supported(): void
    {
        // Arrange
        $service = new DatabaseVersionCompatibility;

        // Act & Assert - MariaDB 10.3 on jammy should fallback to focal
        $this->assertEquals('focal', $service->getUbuntuCodenameForDatabase(DatabaseEngine::MariaDB, '10.3', 'jammy'));

        // MariaDB 10.4 on noble should fallback to jammy
        $this->assertEquals('jammy', $service->getUbuntuCodenameForDatabase(DatabaseEngine::MariaDB, '10.4', 'noble'));
    }

    /**
     * Test getUbuntuCodenameForDatabase returns null for incompatible version.
     */
    public function test_get_ubuntu_codename_for_database_returns_null_for_incompatible_version(): void
    {
        // Arrange
        $service = new DatabaseVersionCompatibility;

        // Act & Assert
        $this->assertNull($service->getUbuntuCodenameForDatabase(DatabaseEngine::MariaDB, '99.9', 'jammy'));
        $this->assertNull($service->getUbuntuCodenameForDatabase(DatabaseEngine::MariaDB, '11.6', 'bionic'));
    }

    /**
     * Test getCompatibleVersions returns all compatible versions for Ubuntu codename.
     */
    public function test_get_compatible_versions_returns_all_compatible_versions_for_ubuntu_codename(): void
    {
        // Arrange
        $service = new DatabaseVersionCompatibility;

        // Act
        $jammyMariaDB = $service->getCompatibleVersions(DatabaseEngine::MariaDB, 'jammy');
        $focalPostgreSQL = $service->getCompatibleVersions(DatabaseEngine::PostgreSQL, 'focal');

        // Assert
        $this->assertIsArray($jammyMariaDB);
        $this->assertContains('11.6', $jammyMariaDB);
        $this->assertContains('11.5', $jammyMariaDB);
        $this->assertContains('10.6', $jammyMariaDB);
        $this->assertContains('10.11', $jammyMariaDB);

        $this->assertIsArray($focalPostgreSQL);
        $this->assertNotEmpty($focalPostgreSQL);
        // PostgreSQL 16, 15, 14 all support focal
        $this->assertGreaterThan(3, count($focalPostgreSQL));
    }

    /**
     * Test getCompatibleVersions returns empty array for unknown Ubuntu codename.
     */
    public function test_get_compatible_versions_returns_versions_even_for_unknown_ubuntu_codename(): void
    {
        // Arrange
        $service = new DatabaseVersionCompatibility;

        // Act
        $result = $service->getCompatibleVersions(DatabaseEngine::MariaDB, 'unknown-codename');

        // Assert - Will include versions with fallbacks
        $this->assertIsArray($result);
    }

    /**
     * Test getUbuntuVersion returns correct version for codename.
     */
    public function test_get_ubuntu_version_returns_correct_version_for_codename(): void
    {
        // Arrange
        $service = new DatabaseVersionCompatibility;

        // Act & Assert
        $this->assertEquals('24.04', $service->getUbuntuVersion('noble'));
        $this->assertEquals('22.04', $service->getUbuntuVersion('jammy'));
        $this->assertEquals('20.04', $service->getUbuntuVersion('focal'));
        $this->assertEquals('18.04', $service->getUbuntuVersion('bionic'));
    }

    /**
     * Test getUbuntuVersion returns null for unknown codename.
     */
    public function test_get_ubuntu_version_returns_null_for_unknown_codename(): void
    {
        // Arrange
        $service = new DatabaseVersionCompatibility;

        // Act
        $result = $service->getUbuntuVersion('unknown');

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test validateInstallation returns valid for compatible combination.
     */
    public function test_validate_installation_returns_valid_for_compatible_combination(): void
    {
        // Arrange
        $service = new DatabaseVersionCompatibility;

        // Act
        $result = $service->validateInstallation(DatabaseEngine::MariaDB, '11.6', 'jammy');

        // Assert
        $this->assertTrue($result['valid']);
        $this->assertEquals('jammy', $result['codename']);
        $this->assertFalse($result['using_fallback']);
        $this->assertNull($result['message']);
    }

    /**
     * Test validateInstallation returns valid with fallback message.
     */
    public function test_validate_installation_returns_valid_with_fallback_message(): void
    {
        // Arrange
        $service = new DatabaseVersionCompatibility;

        // Act - MariaDB 10.3 on jammy uses fallback to focal
        $result = $service->validateInstallation(DatabaseEngine::MariaDB, '10.3', 'jammy');

        // Assert
        $this->assertTrue($result['valid']);
        $this->assertEquals('focal', $result['codename']);
        $this->assertTrue($result['using_fallback']);
        $this->assertNotNull($result['message']);
        $this->assertStringContainsString('focal', $result['message']);
        $this->assertStringContainsString('mariadb', strtolower($result['message']));
    }

    /**
     * Test validateInstallation returns invalid for incompatible combination.
     */
    public function test_validate_installation_returns_invalid_for_incompatible_combination(): void
    {
        // Arrange
        $service = new DatabaseVersionCompatibility;

        // Act
        $result = $service->validateInstallation(DatabaseEngine::MariaDB, '11.6', 'focal');

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('not compatible', $result['error']);
        $this->assertStringContainsString('Compatible versions:', $result['error']);
    }

    /**
     * Test validateInstallation returns invalid for unknown database version.
     */
    public function test_validate_installation_returns_invalid_for_unknown_database_version(): void
    {
        // Arrange
        $service = new DatabaseVersionCompatibility;

        // Act
        $result = $service->validateInstallation(DatabaseEngine::PostgreSQL, '99', 'jammy');

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Test MariaDB 11.x series requires Ubuntu 22.04+.
     */
    public function test_mariadb_11_series_requires_ubuntu_22_04_or_newer(): void
    {
        // Arrange
        $service = new DatabaseVersionCompatibility;

        // Act & Assert - Should work on jammy and noble
        $this->assertTrue($service->isCompatible(DatabaseEngine::MariaDB, '11.0', 'jammy'));
        $this->assertTrue($service->isCompatible(DatabaseEngine::MariaDB, '11.6', 'noble'));

        // Should not work on focal or bionic
        $this->assertFalse($service->isCompatible(DatabaseEngine::MariaDB, '11.0', 'focal'));
        $this->assertFalse($service->isCompatible(DatabaseEngine::MariaDB, '11.6', 'bionic'));
    }

    /**
     * Test PostgreSQL 17 requires Ubuntu 22.04+.
     */
    public function test_postgresql_17_requires_ubuntu_22_04_or_newer(): void
    {
        // Arrange
        $service = new DatabaseVersionCompatibility;

        // Act & Assert - Should work on jammy and noble
        $this->assertTrue($service->isCompatible(DatabaseEngine::PostgreSQL, '17', 'jammy'));
        $this->assertTrue($service->isCompatible(DatabaseEngine::PostgreSQL, '17', 'noble'));

        // Should not work on focal or bionic
        $this->assertFalse($service->isCompatible(DatabaseEngine::PostgreSQL, '17', 'focal'));
        $this->assertFalse($service->isCompatible(DatabaseEngine::PostgreSQL, '17', 'bionic'));
    }

    /**
     * Test MySQL 5.7 not available on newer Ubuntu versions.
     */
    public function test_mysql_5_7_not_available_on_newer_ubuntu_versions(): void
    {
        // Arrange
        $service = new DatabaseVersionCompatibility;

        // Act & Assert - Should work on bionic and focal
        $this->assertTrue($service->isCompatible(DatabaseEngine::MySQL, '5.7', 'bionic'));
        $this->assertTrue($service->isCompatible(DatabaseEngine::MySQL, '5.7', 'focal'));

        // Should not work on jammy or noble
        $this->assertFalse($service->isCompatible(DatabaseEngine::MySQL, '5.7', 'jammy'));
        $this->assertFalse($service->isCompatible(DatabaseEngine::MySQL, '5.7', 'noble'));
    }

    /**
     * Test validateInstallation provides helpful error messages.
     */
    public function test_validate_installation_provides_helpful_error_logs(): void
    {
        // Arrange
        $service = new DatabaseVersionCompatibility;

        // Act
        $result = $service->validateInstallation(DatabaseEngine::MariaDB, '11.6', 'focal');

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('mariadb', strtolower($result['error']));
        $this->assertStringContainsString('11.6', $result['error']);
        $this->assertStringContainsString('focal', $result['error']);
        $this->assertStringContainsString('Compatible versions:', $result['error']);
    }
}
