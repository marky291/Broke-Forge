<?php

namespace Tests\Unit\Models;

use App\Models\AvailablePhpVersion;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailablePhpVersionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed PHP versions for testing
        $this->artisan('db:seed', ['--class' => 'AvailablePhpVersionSeeder']);
    }

    /**
     * Test that all seeded PHP versions exist.
     */
    public function test_all_seeded_versions_exist(): void
    {
        // Act & Assert
        $this->assertDatabaseCount('available_php_versions', 5);
        $this->assertDatabaseHas('available_php_versions', ['version' => '8.1']);
        $this->assertDatabaseHas('available_php_versions', ['version' => '8.2']);
        $this->assertDatabaseHas('available_php_versions', ['version' => '8.3']);
        $this->assertDatabaseHas('available_php_versions', ['version' => '8.4']);
        $this->assertDatabaseHas('available_php_versions', ['version' => '8.5']);
    }

    /**
     * Test that is_default is cast to boolean.
     */
    public function test_is_default_is_cast_to_boolean(): void
    {
        // Arrange
        $version = AvailablePhpVersion::where('version', '8.4')->first();

        // Act & Assert
        $this->assertIsBool($version->is_default);
    }

    /**
     * Test that is_deprecated is cast to boolean.
     */
    public function test_is_deprecated_is_cast_to_boolean(): void
    {
        // Arrange
        $version = AvailablePhpVersion::factory()->deprecated()->create(['version' => '7.0']);

        // Act & Assert
        $this->assertIsBool($version->is_deprecated);
    }

    /**
     * Test that eol_date is cast to date.
     */
    public function test_eol_date_is_cast_to_date(): void
    {
        // Arrange
        $version = AvailablePhpVersion::where('version', '8.4')->first();

        // Act & Assert
        $this->assertInstanceOf(Carbon::class, $version->eol_date);
    }

    /**
     * Test that eol_date can be null.
     */
    public function test_eol_date_can_be_null(): void
    {
        // Arrange
        $version = AvailablePhpVersion::where('version', '8.5')->first();

        // Act & Assert
        $this->assertNull($version->eol_date);
    }

    /**
     * Test that sort_order is cast to integer.
     */
    public function test_sort_order_is_cast_to_integer(): void
    {
        // Arrange
        $version = AvailablePhpVersion::where('version', '8.4')->first();

        // Act & Assert
        $this->assertIsInt($version->sort_order);
    }

    /**
     * Test that isDefault returns true for default version.
     */
    public function test_is_default_returns_true_for_default_version(): void
    {
        // Arrange
        $version = AvailablePhpVersion::where('version', '8.4')->first();

        // Act & Assert
        $this->assertTrue($version->isDefault());
    }

    /**
     * Test that isDefault returns false for non-default version.
     */
    public function test_is_default_returns_false_for_non_default_version(): void
    {
        // Arrange
        $version = AvailablePhpVersion::where('version', '8.3')->first();

        // Act & Assert
        $this->assertFalse($version->isDefault());
    }

    /**
     * Test that isDeprecated returns true for deprecated version.
     */
    public function test_is_deprecated_returns_true_for_deprecated_version(): void
    {
        // Arrange
        $version = AvailablePhpVersion::factory()->deprecated()->create(['version' => '7.0']);

        // Act & Assert
        $this->assertTrue($version->isDeprecated());
    }

    /**
     * Test that isDeprecated returns false for active version.
     */
    public function test_is_deprecated_returns_false_for_active_version(): void
    {
        // Arrange
        $version = AvailablePhpVersion::where('version', '8.4')->first();

        // Act & Assert
        $this->assertFalse($version->isDeprecated());
    }

    /**
     * Test that isEndOfLife returns true for past EOL date.
     */
    public function test_is_end_of_life_returns_true_for_past_eol_date(): void
    {
        // Arrange
        $version = AvailablePhpVersion::factory()->deprecated()->create(['version' => '7.0']);

        // Act & Assert
        $this->assertTrue($version->isEndOfLife());
    }

    /**
     * Test that isEndOfLife returns false for future EOL date.
     */
    public function test_is_end_of_life_returns_false_for_future_eol_date(): void
    {
        // Arrange
        $version = AvailablePhpVersion::where('version', '8.4')->first();

        // Act & Assert
        $this->assertFalse($version->isEndOfLife());
    }

    /**
     * Test that isEndOfLife returns false for null EOL date.
     */
    public function test_is_end_of_life_returns_false_for_null_eol_date(): void
    {
        // Arrange
        $version = AvailablePhpVersion::where('version', '8.5')->first();

        // Act & Assert
        $this->assertFalse($version->isEndOfLife());
    }

    /**
     * Test that active scope excludes deprecated versions.
     */
    public function test_active_scope_excludes_deprecated_versions(): void
    {
        // Arrange - add a deprecated version to test exclusion
        AvailablePhpVersion::factory()->deprecated()->create(['version' => '7.0']);

        // Act
        $activeVersions = AvailablePhpVersion::active()->get();

        // Assert
        $this->assertCount(5, $activeVersions);
        $this->assertFalse($activeVersions->contains('version', '7.0'));
        $this->assertTrue($activeVersions->contains('version', '8.1'));
        $this->assertTrue($activeVersions->contains('version', '8.2'));
        $this->assertTrue($activeVersions->contains('version', '8.3'));
        $this->assertTrue($activeVersions->contains('version', '8.4'));
        $this->assertTrue($activeVersions->contains('version', '8.5'));
    }

    /**
     * Test that ordered scope sorts by sort_order.
     */
    public function test_ordered_scope_sorts_by_sort_order(): void
    {
        // Act
        $orderedVersions = AvailablePhpVersion::ordered()->get();

        // Assert
        $this->assertEquals('8.1', $orderedVersions->first()->version);
        $this->assertEquals('8.5', $orderedVersions->last()->version);
    }

    /**
     * Test that PHP 8.4 is the default version.
     */
    public function test_php_84_is_default(): void
    {
        // Arrange
        $version = AvailablePhpVersion::where('version', '8.4')->first();

        // Act & Assert
        $this->assertTrue($version->isDefault());
        $this->assertFalse($version->isDeprecated());
        $this->assertFalse($version->isEndOfLife());
        $this->assertEquals('PHP 8.4', $version->display_name);
    }

    /**
     * Test that PHP 8.5 has no EOL date.
     */
    public function test_php_85_has_no_eol_date(): void
    {
        // Arrange
        $version = AvailablePhpVersion::where('version', '8.5')->first();

        // Act & Assert
        $this->assertNull($version->eol_date);
        $this->assertFalse($version->isEndOfLife());
        $this->assertFalse($version->isDeprecated());
        $this->assertFalse($version->isDefault());
        $this->assertEquals('PHP 8.5', $version->display_name);
    }

    /**
     * Test that active scope can be chained with ordered scope.
     */
    public function test_active_and_ordered_scopes_can_be_chained(): void
    {
        // Act
        $activeOrderedVersions = AvailablePhpVersion::active()->ordered()->get();

        // Assert
        $this->assertCount(5, $activeOrderedVersions);
        $this->assertEquals('8.1', $activeOrderedVersions->first()->version);
        $this->assertEquals('8.5', $activeOrderedVersions->last()->version);
    }

    /**
     * Test that only one version is marked as default.
     */
    public function test_only_one_version_is_marked_as_default(): void
    {
        // Act
        $defaultVersions = AvailablePhpVersion::where('is_default', true)->get();

        // Assert
        $this->assertCount(1, $defaultVersions);
        $this->assertEquals('8.4', $defaultVersions->first()->version);
    }

    /**
     * Test that factory creates valid PHP version.
     */
    public function test_factory_creates_valid_php_version(): void
    {
        // Act
        $version = AvailablePhpVersion::factory()->create(['version' => '9.0']);

        // Assert
        $this->assertInstanceOf(AvailablePhpVersion::class, $version);
        $this->assertEquals('9.0', $version->version);
    }

    /**
     * Test that factory deprecated state works correctly.
     */
    public function test_factory_deprecated_state_works_correctly(): void
    {
        // Act
        $version = AvailablePhpVersion::factory()->deprecated()->create(['version' => '6.0']);

        // Assert
        $this->assertTrue($version->isDeprecated());
        $this->assertTrue($version->isEndOfLife());
    }
}
