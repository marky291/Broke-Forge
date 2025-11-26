<?php

namespace Tests\Unit\Models;

use App\Models\AvailableFramework;
use App\Models\ServerSite;
use App\Packages\Services\Sites\Framework\GenericPhp\GenericPhpInstallerJob;
use App\Packages\Services\Sites\Framework\Laravel\LaravelInstallerJob;
use App\Packages\Services\Sites\Framework\StaticHtml\StaticHtmlInstallerJob;
use App\Packages\Services\Sites\Framework\WordPress\WordPressInstallerJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailableFrameworkTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that requiresDatabase returns true when database requirement is true.
     */
    public function test_requires_database_returns_true_when_database_is_required(): void
    {
        // Arrange
        $framework = AvailableFramework::factory()->laravel()->create();

        // Act & Assert
        $this->assertTrue($framework->requiresDatabase());
    }

    /**
     * Test that requiresDatabase returns false when database requirement is false.
     */
    public function test_requires_database_returns_false_when_database_is_not_required(): void
    {
        // Arrange
        $framework = AvailableFramework::factory()->staticHtml()->create();

        // Act & Assert
        $this->assertFalse($framework->requiresDatabase());
    }

    /**
     * Test that requiresRedis returns true when redis requirement is true.
     */
    public function test_requires_redis_returns_true_when_redis_is_required(): void
    {
        // Arrange
        $framework = AvailableFramework::factory()->laravel()->create();

        // Act & Assert
        $this->assertTrue($framework->requiresRedis());
    }

    /**
     * Test that requiresRedis returns false when redis requirement is false.
     */
    public function test_requires_redis_returns_false_when_redis_is_not_required(): void
    {
        // Arrange
        $framework = AvailableFramework::factory()->wordpress()->create();

        // Act & Assert
        $this->assertFalse($framework->requiresRedis());
    }

    /**
     * Test that requiresNodejs returns true when nodejs requirement is true.
     */
    public function test_requires_nodejs_returns_true_when_nodejs_is_required(): void
    {
        // Arrange
        $framework = AvailableFramework::factory()->laravel()->create();

        // Act & Assert
        $this->assertTrue($framework->requiresNodejs());
    }

    /**
     * Test that requiresNodejs returns false when nodejs requirement is false.
     */
    public function test_requires_nodejs_returns_false_when_nodejs_is_not_required(): void
    {
        // Arrange
        $framework = AvailableFramework::factory()->wordpress()->create();

        // Act & Assert
        $this->assertFalse($framework->requiresNodejs());
    }

    /**
     * Test that requiresComposer returns true when composer requirement is true.
     */
    public function test_requires_composer_returns_true_when_composer_is_required(): void
    {
        // Arrange
        $framework = AvailableFramework::factory()->laravel()->create();

        // Act & Assert
        $this->assertTrue($framework->requiresComposer());
    }

    /**
     * Test that requiresComposer returns false when composer requirement is false.
     */
    public function test_requires_composer_returns_false_when_composer_is_not_required(): void
    {
        // Arrange
        $framework = AvailableFramework::factory()->wordpress()->create();

        // Act & Assert
        $this->assertFalse($framework->requiresComposer());
    }

    /**
     * Test that supportsEnv returns true when environment editing is supported.
     */
    public function test_supports_env_returns_true_when_env_is_supported(): void
    {
        // Arrange
        $framework = AvailableFramework::factory()->laravel()->create();

        // Act & Assert
        $this->assertTrue($framework->supportsEnv());
    }

    /**
     * Test that supportsEnv returns false when environment editing is not supported.
     */
    public function test_supports_env_returns_false_when_env_is_not_supported(): void
    {
        // Arrange
        $framework = AvailableFramework::factory()->staticHtml()->create();

        // Act & Assert
        $this->assertFalse($framework->supportsEnv());
    }

    /**
     * Test that getEnvFilePath returns correct file path.
     */
    public function test_get_env_file_path_returns_correct_file_path(): void
    {
        // Arrange
        $framework = AvailableFramework::factory()->laravel()->create();

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
        $framework = AvailableFramework::factory()->wordpress()->create();

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
        $framework = AvailableFramework::factory()->staticHtml()->create();

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
        $framework = AvailableFramework::factory()->laravel()->create();

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
        $framework = AvailableFramework::factory()->laravel()->create();

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
        $framework = AvailableFramework::factory()->laravel()->create();
        ServerSite::factory()->count(3)->create(['available_framework_id' => $framework->id]);

        // Act
        $sites = $framework->sites;

        // Assert
        $this->assertCount(3, $sites);
        $this->assertInstanceOf(ServerSite::class, $sites->first());
    }

    /**
     * Test that factory states create frameworks with correct slugs.
     */
    public function test_factory_states_create_correct_slugs(): void
    {
        // Act
        $laravel = AvailableFramework::factory()->laravel()->create();
        $wordpress = AvailableFramework::factory()->wordpress()->create();
        $genericPhp = AvailableFramework::factory()->genericPhp()->create();
        $staticHtml = AvailableFramework::factory()->staticHtml()->create();

        // Assert
        $this->assertDatabaseCount('available_frameworks', 4);
        $this->assertEquals(AvailableFramework::LARAVEL, $laravel->slug);
        $this->assertEquals(AvailableFramework::WORDPRESS, $wordpress->slug);
        $this->assertEquals(AvailableFramework::GENERIC_PHP, $genericPhp->slug);
        $this->assertEquals(AvailableFramework::STATIC_HTML, $staticHtml->slug);
    }

    /**
     * Test that Laravel framework has all dependencies.
     */
    public function test_laravel_framework_has_all_dependencies(): void
    {
        // Arrange
        $framework = AvailableFramework::factory()->laravel()->create();

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
        $framework = AvailableFramework::factory()->wordpress()->create();

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
        $framework = AvailableFramework::factory()->staticHtml()->create();

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
        $framework = AvailableFramework::factory()->wordpress()->create();

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
        $framework = AvailableFramework::factory()->laravel()->create();

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
        $framework = AvailableFramework::factory()->genericPhp()->create();

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
        $framework = AvailableFramework::factory()->staticHtml()->create();

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
        $framework = AvailableFramework::factory()->wordpress()->create();

        // Act & Assert
        $this->assertFalse($framework->hasPublicSubdirectory());
    }

    /**
     * Test that hasPublicSubdirectory returns true for Laravel.
     */
    public function test_has_public_subdirectory_returns_true_for_laravel(): void
    {
        // Arrange
        $framework = AvailableFramework::factory()->laravel()->create();

        // Act & Assert
        $this->assertTrue($framework->hasPublicSubdirectory());
    }

    /**
     * Test that hasPublicSubdirectory returns true for GenericPhp.
     */
    public function test_has_public_subdirectory_returns_true_for_generic_php(): void
    {
        // Arrange
        $framework = AvailableFramework::factory()->genericPhp()->create();

        // Act & Assert
        $this->assertTrue($framework->hasPublicSubdirectory());
    }

    /**
     * Test that hasPublicSubdirectory returns true for StaticHTML.
     */
    public function test_has_public_subdirectory_returns_true_for_static_html(): void
    {
        // Arrange
        $framework = AvailableFramework::factory()->staticHtml()->create();

        // Act & Assert
        $this->assertTrue($framework->hasPublicSubdirectory());
    }

    /**
     * Test that slug constants are defined correctly.
     */
    public function test_slug_constants_are_defined_correctly(): void
    {
        $this->assertEquals('laravel', AvailableFramework::LARAVEL);
        $this->assertEquals('wordpress', AvailableFramework::WORDPRESS);
        $this->assertEquals('generic-php', AvailableFramework::GENERIC_PHP);
        $this->assertEquals('static-html', AvailableFramework::STATIC_HTML);
    }

    /**
     * Test that getInstallerClass returns correct installer for Laravel.
     */
    public function test_get_installer_class_returns_laravel_installer(): void
    {
        // Arrange
        $framework = AvailableFramework::factory()->laravel()->create();

        // Act
        $installerClass = $framework->getInstallerClass();

        // Assert
        $this->assertEquals(LaravelInstallerJob::class, $installerClass);
    }

    /**
     * Test that getInstallerClass returns correct installer for WordPress.
     */
    public function test_get_installer_class_returns_wordpress_installer(): void
    {
        // Arrange
        $framework = AvailableFramework::factory()->wordpress()->create();

        // Act
        $installerClass = $framework->getInstallerClass();

        // Assert
        $this->assertEquals(WordPressInstallerJob::class, $installerClass);
    }

    /**
     * Test that getInstallerClass returns correct installer for Generic PHP.
     */
    public function test_get_installer_class_returns_generic_php_installer(): void
    {
        // Arrange
        $framework = AvailableFramework::factory()->genericPhp()->create();

        // Act
        $installerClass = $framework->getInstallerClass();

        // Assert
        $this->assertEquals(GenericPhpInstallerJob::class, $installerClass);
    }

    /**
     * Test that getInstallerClass returns correct installer for Static HTML.
     */
    public function test_get_installer_class_returns_static_html_installer(): void
    {
        // Arrange
        $framework = AvailableFramework::factory()->staticHtml()->create();

        // Act
        $installerClass = $framework->getInstallerClass();

        // Assert
        $this->assertEquals(StaticHtmlInstallerJob::class, $installerClass);
    }

    /**
     * Test that getInstallerClass throws exception for unknown framework.
     */
    public function test_get_installer_class_throws_exception_for_unknown_framework(): void
    {
        // Arrange
        $framework = AvailableFramework::factory()->create(['slug' => 'unknown-framework']);

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown framework: unknown-framework');

        // Act
        $framework->getInstallerClass();
    }

    /**
     * Test that requiresGitRepository returns true for Laravel.
     */
    public function test_requires_git_repository_returns_true_for_laravel(): void
    {
        // Arrange
        $framework = AvailableFramework::factory()->laravel()->create();

        // Act & Assert
        $this->assertTrue($framework->requiresGitRepository());
    }

    /**
     * Test that requiresGitRepository returns false for WordPress.
     */
    public function test_requires_git_repository_returns_false_for_wordpress(): void
    {
        // Arrange
        $framework = AvailableFramework::factory()->wordpress()->create();

        // Act & Assert
        $this->assertFalse($framework->requiresGitRepository());
    }

    /**
     * Test that requiresGitRepository returns true for Generic PHP.
     */
    public function test_requires_git_repository_returns_true_for_generic_php(): void
    {
        // Arrange
        $framework = AvailableFramework::factory()->genericPhp()->create();

        // Act & Assert
        $this->assertTrue($framework->requiresGitRepository());
    }

    /**
     * Test that requiresGitRepository returns true for Static HTML.
     */
    public function test_requires_git_repository_returns_true_for_static_html(): void
    {
        // Arrange
        $framework = AvailableFramework::factory()->staticHtml()->create();

        // Act & Assert
        $this->assertTrue($framework->requiresGitRepository());
    }

    /**
     * Test that requiresPhpVersion returns true for Laravel.
     */
    public function test_requires_php_version_returns_true_for_laravel(): void
    {
        // Arrange
        $framework = AvailableFramework::factory()->laravel()->create();

        // Act & Assert
        $this->assertTrue($framework->requiresPhpVersion());
    }

    /**
     * Test that requiresPhpVersion returns true for WordPress.
     */
    public function test_requires_php_version_returns_true_for_wordpress(): void
    {
        // Arrange
        $framework = AvailableFramework::factory()->wordpress()->create();

        // Act & Assert
        $this->assertTrue($framework->requiresPhpVersion());
    }

    /**
     * Test that requiresPhpVersion returns true for Generic PHP.
     */
    public function test_requires_php_version_returns_true_for_generic_php(): void
    {
        // Arrange
        $framework = AvailableFramework::factory()->genericPhp()->create();

        // Act & Assert
        $this->assertTrue($framework->requiresPhpVersion());
    }

    /**
     * Test that requiresPhpVersion returns false for Static HTML.
     */
    public function test_requires_php_version_returns_false_for_static_html(): void
    {
        // Arrange
        $framework = AvailableFramework::factory()->staticHtml()->create();

        // Act & Assert
        $this->assertFalse($framework->requiresPhpVersion());
    }

    /**
     * Test that findBySlug returns correct framework.
     */
    public function test_find_by_slug_returns_correct_framework(): void
    {
        // Arrange
        AvailableFramework::factory()->laravel()->create();

        // Act
        $framework = AvailableFramework::findBySlug(AvailableFramework::LARAVEL);

        // Assert
        $this->assertNotNull($framework);
        $this->assertEquals('Laravel', $framework->name);
        $this->assertEquals(AvailableFramework::LARAVEL, $framework->slug);
    }

    /**
     * Test that findBySlug returns null for non-existent slug.
     */
    public function test_find_by_slug_returns_null_for_non_existent_slug(): void
    {
        // Act
        $framework = AvailableFramework::findBySlug('non-existent');

        // Assert
        $this->assertNull($framework);
    }

    /**
     * Test that slug scope filters by slug correctly.
     */
    public function test_slug_scope_filters_by_slug(): void
    {
        // Arrange
        AvailableFramework::factory()->wordpress()->create();

        // Act
        $framework = AvailableFramework::slug(AvailableFramework::WORDPRESS)->first();

        // Assert
        $this->assertNotNull($framework);
        $this->assertEquals('WordPress', $framework->name);
        $this->assertEquals(AvailableFramework::WORDPRESS, $framework->slug);
    }
}
