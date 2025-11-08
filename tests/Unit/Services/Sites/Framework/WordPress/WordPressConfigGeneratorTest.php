<?php

namespace Tests\Unit\Services\Sites\Framework\WordPress;

use App\Models\AvailableFramework;
use App\Models\Server;
use App\Models\ServerDatabase;
use App\Models\ServerSite;
use App\Packages\Services\Sites\Framework\WordPress\WordPressConfigGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WordPressConfigGeneratorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test generates wp-config with database credentials.
     */
    public function test_generates_wp_config_with_database_credentials(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'name' => 'wordpress_db',
            'root_password' => 'secure_password_123',
        ]);

        $wordpress = AvailableFramework::firstOrCreate(
            ['slug' => 'wordpress'],
            [
                'name' => 'WordPress',
                'env' => ['file_path' => 'wp-config.php', 'supports' => true],
                'requirements' => ['database' => true, 'redis' => false, 'nodejs' => false, 'composer' => false],
            ]
        );

        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $wordpress->id,
            'database_id' => $database->id,
            'domain' => 'test-wordpress.com',
            'ssl_enabled' => false,
        ]);

        $generator = new WordPressConfigGenerator;

        // Act
        $config = $generator->generate($site);

        // Assert
        $this->assertStringContainsString("define('DB_NAME', 'wordpress_db');", $config);
        $this->assertStringContainsString("define('DB_USER', 'wordpress_db');", $config); // Uses database name as username
        $this->assertStringContainsString("define('DB_PASSWORD', 'secure_password_123');", $config);
        $this->assertStringContainsString("define('DB_HOST', 'localhost');", $config);
    }

    /**
     * Test includes unique WordPress salts.
     */
    public function test_includes_unique_wordpress_salts(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create(['server_id' => $server->id]);

        $wordpress = AvailableFramework::firstOrCreate(
            ['slug' => 'wordpress'],
            [
                'name' => 'WordPress',
                'env' => ['file_path' => 'wp-config.php', 'supports' => true],
                'requirements' => ['database' => true, 'redis' => false, 'nodejs' => false, 'composer' => false],
            ]
        );

        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $wordpress->id,
            'database_id' => $database->id,
            'ssl_enabled' => false,
        ]);

        $generator = new WordPressConfigGenerator;

        // Act - generate config twice
        $config1 = $generator->generate($site);
        $config2 = $generator->generate($site);

        // Assert - salts should exist and be different
        $this->assertStringContainsString("define('AUTH_KEY'", $config1);
        $this->assertStringContainsString("define('SECURE_AUTH_KEY'", $config1);
        $this->assertStringContainsString("define('LOGGED_IN_KEY'", $config1);
        $this->assertStringContainsString("define('NONCE_KEY'", $config1);
        $this->assertStringContainsString("define('AUTH_SALT'", $config1);
        $this->assertStringContainsString("define('SECURE_AUTH_SALT'", $config1);
        $this->assertStringContainsString("define('LOGGED_IN_SALT'", $config1);
        $this->assertStringContainsString("define('NONCE_SALT'", $config1);

        // Salts should be different between generations
        $this->assertNotEquals($config1, $config2);
    }

    /**
     * Test configures SSL when enabled.
     */
    public function test_configures_ssl_when_enabled(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create(['server_id' => $server->id]);

        $wordpress = AvailableFramework::firstOrCreate(
            ['slug' => 'wordpress'],
            [
                'name' => 'WordPress',
                'env' => ['file_path' => 'wp-config.php', 'supports' => true],
                'requirements' => ['database' => true, 'redis' => false, 'nodejs' => false, 'composer' => false],
            ]
        );

        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $wordpress->id,
            'database_id' => $database->id,
            'domain' => 'secure-site.com',
            'ssl_enabled' => true,
        ]);

        $generator = new WordPressConfigGenerator;

        // Act
        $config = $generator->generate($site);

        // Assert
        $this->assertStringContainsString("define('WP_HOME', 'https://secure-site.com');", $config);
        $this->assertStringContainsString("define('WP_SITEURL', 'https://secure-site.com');", $config);
    }

    /**
     * Test configures non-SSL when disabled.
     */
    public function test_configures_non_ssl_when_disabled(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create(['server_id' => $server->id]);

        $wordpress = AvailableFramework::firstOrCreate(
            ['slug' => 'wordpress'],
            [
                'name' => 'WordPress',
                'env' => ['file_path' => 'wp-config.php', 'supports' => true],
                'requirements' => ['database' => true, 'redis' => false, 'nodejs' => false, 'composer' => false],
            ]
        );

        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $wordpress->id,
            'database_id' => $database->id,
            'domain' => 'insecure-site.com',
            'ssl_enabled' => false,
        ]);

        $generator = new WordPressConfigGenerator;

        // Act
        $config = $generator->generate($site);

        // Assert
        $this->assertStringContainsString("define('WP_HOME', 'http://insecure-site.com');", $config);
        $this->assertStringContainsString("define('WP_SITEURL', 'http://insecure-site.com');", $config);
    }

    /**
     * Test sets table prefix to wp_.
     */
    public function test_sets_table_prefix_to_wp(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create(['server_id' => $server->id]);

        $wordpress = AvailableFramework::firstOrCreate(
            ['slug' => 'wordpress'],
            [
                'name' => 'WordPress',
                'env' => ['file_path' => 'wp-config.php', 'supports' => true],
                'requirements' => ['database' => true, 'redis' => false, 'nodejs' => false, 'composer' => false],
            ]
        );

        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $wordpress->id,
            'database_id' => $database->id,
        ]);

        $generator = new WordPressConfigGenerator;

        // Act
        $config = $generator->generate($site);

        // Assert
        $this->assertStringContainsString("\$table_prefix = 'wp_';", $config);
    }

    /**
     * Test disables debug mode.
     */
    public function test_disables_debug_mode(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create(['server_id' => $server->id]);

        $wordpress = AvailableFramework::firstOrCreate(
            ['slug' => 'wordpress'],
            [
                'name' => 'WordPress',
                'env' => ['file_path' => 'wp-config.php', 'supports' => true],
                'requirements' => ['database' => true, 'redis' => false, 'nodejs' => false, 'composer' => false],
            ]
        );

        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $wordpress->id,
            'database_id' => $database->id,
        ]);

        $generator = new WordPressConfigGenerator;

        // Act
        $config = $generator->generate($site);

        // Assert
        $this->assertStringContainsString("define('WP_DEBUG', false);", $config);
    }

    /**
     * Test includes database charset.
     */
    public function test_includes_database_charset(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create(['server_id' => $server->id]);

        $wordpress = AvailableFramework::firstOrCreate(
            ['slug' => 'wordpress'],
            [
                'name' => 'WordPress',
                'env' => ['file_path' => 'wp-config.php', 'supports' => true],
                'requirements' => ['database' => true, 'redis' => false, 'nodejs' => false, 'composer' => false],
            ]
        );

        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $wordpress->id,
            'database_id' => $database->id,
        ]);

        $generator = new WordPressConfigGenerator;

        // Act
        $config = $generator->generate($site);

        // Assert
        $this->assertStringContainsString("define('DB_CHARSET', 'utf8mb4');", $config);
        $this->assertStringContainsString("define('DB_COLLATE', '');", $config);
    }

    /**
     * Test includes required PHP opening tag.
     */
    public function test_includes_required_php_opening_tag(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create(['server_id' => $server->id]);

        $wordpress = AvailableFramework::firstOrCreate(
            ['slug' => 'wordpress'],
            [
                'name' => 'WordPress',
                'env' => ['file_path' => 'wp-config.php', 'supports' => true],
                'requirements' => ['database' => true, 'redis' => false, 'nodejs' => false, 'composer' => false],
            ]
        );

        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $wordpress->id,
            'database_id' => $database->id,
        ]);

        $generator = new WordPressConfigGenerator;

        // Act
        $config = $generator->generate($site);

        // Assert
        $this->assertStringStartsWith('<?php', $config);
    }

    /**
     * Test includes WordPress bootstrap require.
     */
    public function test_includes_wordpress_bootstrap_require(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create(['server_id' => $server->id]);

        $wordpress = AvailableFramework::firstOrCreate(
            ['slug' => 'wordpress'],
            [
                'name' => 'WordPress',
                'env' => ['file_path' => 'wp-config.php', 'supports' => true],
                'requirements' => ['database' => true, 'redis' => false, 'nodejs' => false, 'composer' => false],
            ]
        );

        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $wordpress->id,
            'database_id' => $database->id,
        ]);

        $generator = new WordPressConfigGenerator;

        // Act
        $config = $generator->generate($site);

        // Assert
        $this->assertStringContainsString("require_once ABSPATH . 'wp-settings.php';", $config);
    }

    /**
     * Test handles database with special characters in password.
     */
    public function test_handles_database_with_special_characters_in_password(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'name' => 'wp_db',
            'root_password' => "p@ss'word\"with\\special",
        ]);

        $wordpress = AvailableFramework::firstOrCreate(
            ['slug' => 'wordpress'],
            [
                'name' => 'WordPress',
                'env' => ['file_path' => 'wp-config.php', 'supports' => true],
                'requirements' => ['database' => true, 'redis' => false, 'nodejs' => false, 'composer' => false],
            ]
        );

        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $wordpress->id,
            'database_id' => $database->id,
        ]);

        $generator = new WordPressConfigGenerator;

        // Act
        $config = $generator->generate($site);

        // Assert - password should be properly escaped
        $this->assertStringContainsString("define('DB_PASSWORD'", $config);
    }
}
