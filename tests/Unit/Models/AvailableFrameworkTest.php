<?php

namespace Tests\Unit\Models;

use App\Models\AvailableFramework;
use App\Models\ServerSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailableFrameworkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed frameworks for testing
        $this->artisan('db:seed', ['--class' => 'AvailableFrameworkSeeder']);
    }

    /**
     * Test that requiresDatabase returns true when database requirement is true.
     */
    public function test_requires_database_returns_true_when_database_is_required(): void
    {
        // Arrange
        $framework = AvailableFramework::where('slug', 'laravel')->first();

        // Act & Assert
        $this->assertTrue($framework->requiresDatabase());
    }

    /**
     * Test that requiresDatabase returns false when database requirement is false.
     */
    public function test_requires_database_returns_false_when_database_is_not_required(): void
    {
        // Arrange
        $framework = AvailableFramework::where('slug', 'static-html')->first();

        // Act & Assert
        $this->assertFalse($framework->requiresDatabase());
    }

    /**
     * Test that requiresRedis returns true when redis requirement is true.
     */
    public function test_requires_redis_returns_true_when_redis_is_required(): void
    {
        // Arrange
        $framework = AvailableFramework::where('slug', 'laravel')->first();

        // Act & Assert
        $this->assertTrue($framework->requiresRedis());
    }

    /**
     * Test that requiresRedis returns false when redis requirement is false.
     */
    public function test_requires_redis_returns_false_when_redis_is_not_required(): void
    {
        // Arrange
        $framework = AvailableFramework::where('slug', 'wordpress')->first();

        // Act & Assert
        $this->assertFalse($framework->requiresRedis());
    }

    /**
     * Test that requiresNodejs returns true when nodejs requirement is true.
     */
    public function test_requires_nodejs_returns_true_when_nodejs_is_required(): void
    {
        // Arrange
        $framework = AvailableFramework::where('slug', 'laravel')->first();

        // Act & Assert
        $this->assertTrue($framework->requiresNodejs());
    }

    /**
     * Test that requiresNodejs returns false when nodejs requirement is false.
     */
    public function test_requires_nodejs_returns_false_when_nodejs_is_not_required(): void
    {
        // Arrange
        $framework = AvailableFramework::where('slug', 'wordpress')->first();

        // Act & Assert
        $this->assertFalse($framework->requiresNodejs());
    }

    /**
     * Test that requiresComposer returns true when composer requirement is true.
     */
    public function test_requires_composer_returns_true_when_composer_is_required(): void
    {
        // Arrange
        $framework = AvailableFramework::where('slug', 'laravel')->first();

        // Act & Assert
        $this->assertTrue($framework->requiresComposer());
    }

    /**
     * Test that requiresComposer returns false when composer requirement is false.
     */
    public function test_requires_composer_returns_false_when_composer_is_not_required(): void
    {
        // Arrange
        $framework = AvailableFramework::where('slug', 'wordpress')->first();

        // Act & Assert
        $this->assertFalse($framework->requiresComposer());
    }

    /**
     * Test that supportsEnv returns true when environment editing is supported.
     */
    public function test_supports_env_returns_true_when_env_is_supported(): void
    {
        // Arrange
        $framework = AvailableFramework::where('slug', 'laravel')->first();

        // Act & Assert
        $this->assertTrue($framework->supportsEnv());
    }

    /**
     * Test that supportsEnv returns false when environment editing is not supported.
     */
    public function test_supports_env_returns_false_when_env_is_not_supported(): void
    {
        // Arrange
        $framework = AvailableFramework::where('slug', 'static-html')->first();

        // Act & Assert
        $this->assertFalse($framework->supportsEnv());
    }

    /**
     * Test that getEnvFilePath returns correct file path.
     */
    public function test_get_env_file_path_returns_correct_file_path(): void
    {
        // Arrange
        $framework = AvailableFramework::where('slug', 'laravel')->first();

        // Act
        $envPath = $framework->getEnvFilePath();

        // Assert
        $this->assertEquals('.env', $envPath);
    }

    /**
     * Test that getEnvFilePath returns WordPress config file path.
     */
    public function test_get_env_file_path_returns_wordpress_config_path(): void
    {
        // Arrange
        $framework = AvailableFramework::where('slug', 'wordpress')->first();

        // Act
        $envPath = $framework->getEnvFilePath();

        // Assert
        $this->assertEquals('wp-config.php', $envPath);
    }

    /**
     * Test that getEnvFilePath returns null for frameworks without env support.
     */
    public function test_get_env_file_path_returns_null_when_no_file_path(): void
    {
        // Arrange
        $framework = AvailableFramework::where('slug', 'static-html')->first();

        // Act
        $envPath = $framework->getEnvFilePath();

        // Assert
        $this->assertNull($envPath);
    }

    /**
     * Test that env is cast to array.
     */
    public function test_env_is_cast_to_array(): void
    {
        // Arrange
        $framework = AvailableFramework::where('slug', 'laravel')->first();

        // Act
        $env = $framework->env;

        // Assert
        $this->assertIsArray($env);
        $this->assertArrayHasKey('file_path', $env);
        $this->assertArrayHasKey('supports', $env);
    }

    /**
     * Test that requirements is cast to array.
     */
    public function test_requirements_is_cast_to_array(): void
    {
        // Arrange
        $framework = AvailableFramework::where('slug', 'laravel')->first();

        // Act
        $requirements = $framework->requirements;

        // Assert
        $this->assertIsArray($requirements);
        $this->assertArrayHasKey('database', $requirements);
        $this->assertArrayHasKey('redis', $requirements);
        $this->assertArrayHasKey('nodejs', $requirements);
        $this->assertArrayHasKey('composer', $requirements);
    }

    /**
     * Test that framework has many sites relationship.
     */
    public function test_framework_has_many_sites(): void
    {
        // Arrange
        $framework = AvailableFramework::where('slug', 'laravel')->first();
        ServerSite::factory()->count(3)->create(['available_framework_id' => $framework->id]);

        // Act
        $sites = $framework->sites;

        // Assert
        $this->assertCount(3, $sites);
        $this->assertInstanceOf(ServerSite::class, $sites->first());
    }

    /**
     * Test that all seeded frameworks exist.
     */
    public function test_all_seeded_frameworks_exist(): void
    {
        // Act & Assert
        $this->assertDatabaseCount('available_frameworks', 4);
        $this->assertDatabaseHas('available_frameworks', ['slug' => 'laravel']);
        $this->assertDatabaseHas('available_frameworks', ['slug' => 'wordpress']);
        $this->assertDatabaseHas('available_frameworks', ['slug' => 'generic-php']);
        $this->assertDatabaseHas('available_frameworks', ['slug' => 'static-html']);
    }

    /**
     * Test that Laravel framework has all dependencies.
     */
    public function test_laravel_framework_has_all_dependencies(): void
    {
        // Arrange
        $framework = AvailableFramework::where('slug', 'laravel')->first();

        // Act & Assert
        $this->assertTrue($framework->requiresDatabase());
        $this->assertTrue($framework->requiresRedis());
        $this->assertTrue($framework->requiresNodejs());
        $this->assertTrue($framework->requiresComposer());
        $this->assertTrue($framework->supportsEnv());
    }

    /**
     * Test that WordPress framework has only database dependency.
     */
    public function test_wordpress_framework_has_only_database_dependency(): void
    {
        // Arrange
        $framework = AvailableFramework::where('slug', 'wordpress')->first();

        // Act & Assert
        $this->assertTrue($framework->requiresDatabase());
        $this->assertFalse($framework->requiresRedis());
        $this->assertFalse($framework->requiresNodejs());
        $this->assertFalse($framework->requiresComposer());
        $this->assertTrue($framework->supportsEnv());
    }

    /**
     * Test that Static HTML framework has no dependencies.
     */
    public function test_static_html_framework_has_no_dependencies(): void
    {
        // Arrange
        $framework = AvailableFramework::where('slug', 'static-html')->first();

        // Act & Assert
        $this->assertFalse($framework->requiresDatabase());
        $this->assertFalse($framework->requiresRedis());
        $this->assertFalse($framework->requiresNodejs());
        $this->assertFalse($framework->requiresComposer());
        $this->assertFalse($framework->supportsEnv());
    }

    /**
     * Test that getPublicDirectory returns empty string for WordPress.
     */
    public function test_get_public_directory_returns_empty_string_for_wordpress(): void
    {
        // Arrange
        $framework = AvailableFramework::where('slug', 'wordpress')->first();

        // Act
        $publicDir = $framework->getPublicDirectory();

        // Assert
        $this->assertEquals('', $publicDir);
    }

    /**
     * Test that getPublicDirectory returns /public for Laravel.
     */
    public function test_get_public_directory_returns_public_for_laravel(): void
    {
        // Arrange
        $framework = AvailableFramework::where('slug', 'laravel')->first();

        // Act
        $publicDir = $framework->getPublicDirectory();

        // Assert
        $this->assertEquals('/public', $publicDir);
    }

    /**
     * Test that getPublicDirectory returns /public for GenericPhp.
     */
    public function test_get_public_directory_returns_public_for_generic_php(): void
    {
        // Arrange
        $framework = AvailableFramework::where('slug', 'generic-php')->first();

        // Act
        $publicDir = $framework->getPublicDirectory();

        // Assert
        $this->assertEquals('/public', $publicDir);
    }

    /**
     * Test that getPublicDirectory returns /public for StaticHTML.
     */
    public function test_get_public_directory_returns_public_for_static_html(): void
    {
        // Arrange
        $framework = AvailableFramework::where('slug', 'static-html')->first();

        // Act
        $publicDir = $framework->getPublicDirectory();

        // Assert
        $this->assertEquals('/public', $publicDir);
    }

    /**
     * Test that hasPublicSubdirectory returns false for WordPress.
     */
    public function test_has_public_subdirectory_returns_false_for_wordpress(): void
    {
        // Arrange
        $framework = AvailableFramework::where('slug', 'wordpress')->first();

        // Act & Assert
        $this->assertFalse($framework->hasPublicSubdirectory());
    }

    /**
     * Test that hasPublicSubdirectory returns true for Laravel.
     */
    public function test_has_public_subdirectory_returns_true_for_laravel(): void
    {
        // Arrange
        $framework = AvailableFramework::where('slug', 'laravel')->first();

        // Act & Assert
        $this->assertTrue($framework->hasPublicSubdirectory());
    }

    /**
     * Test that hasPublicSubdirectory returns true for GenericPhp.
     */
    public function test_has_public_subdirectory_returns_true_for_generic_php(): void
    {
        // Arrange
        $framework = AvailableFramework::where('slug', 'generic-php')->first();

        // Act & Assert
        $this->assertTrue($framework->hasPublicSubdirectory());
    }

    /**
     * Test that hasPublicSubdirectory returns true for StaticHTML.
     */
    public function test_has_public_subdirectory_returns_true_for_static_html(): void
    {
        // Arrange
        $framework = AvailableFramework::where('slug', 'static-html')->first();

        // Act & Assert
        $this->assertTrue($framework->hasPublicSubdirectory());
    }
}
