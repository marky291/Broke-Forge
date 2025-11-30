<?php

namespace Tests\Unit\Packages\Services\Sites\Command\Rules;

use App\Models\Server;
use App\Models\ServerPhp;
use App\Packages\Services\Sites\Command\Rules\ValidPhpCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidPhpCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test commands without PHP pass validation.
     */
    public function test_commands_without_php_pass_validation(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $rule = new ValidPhpCommand($server);
        $commandsWithoutPhp = [
            'npm run build',
            'composer install',
            'node server.js',
            '/usr/bin/node app.js',
        ];

        foreach ($commandsWithoutPhp as $command) {
            $failCalled = false;
            $fail = function () use (&$failCalled) {
                $failCalled = true;
            };

            // Act
            $rule->validate('command', $command, $fail);

            // Assert
            $this->assertFalse($failCalled, "Command '{$command}' without PHP should pass validation");
        }
    }

    /**
     * Test bare php command requires explicit version.
     */
    public function test_bare_php_command_requires_explicit_version(): void
    {
        // Arrange
        $server = Server::factory()->create();
        ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'status' => 'active',
        ]);
        ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.4',
            'status' => 'active',
        ]);

        $rule = new ValidPhpCommand($server);
        $barePhpCommands = [
            'php artisan migrate',
            'php artisan queue:work',
            '/usr/bin/php artisan cache:clear',
        ];

        foreach ($barePhpCommands as $command) {
            $failCalled = false;
            $failMessage = '';
            $fail = function ($message) use (&$failCalled, &$failMessage) {
                $failCalled = true;
                $failMessage = $message;
            };

            // Act
            $rule->validate('command', $command, $fail);

            // Assert
            $this->assertTrue($failCalled, "Command '{$command}' with bare 'php' should require explicit version");
            $this->assertStringContainsString('specify a PHP version', $failMessage);
            $this->assertStringContainsString('php8.3', $failMessage);
            $this->assertStringContainsString('php8.4', $failMessage);
        }
    }

    /**
     * Test commands with installed PHP version pass validation.
     */
    public function test_commands_with_installed_php_version_pass_validation(): void
    {
        // Arrange
        $server = Server::factory()->create();
        ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'status' => 'active',
        ]);
        ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.4',
            'status' => 'active',
        ]);

        $rule = new ValidPhpCommand($server);
        $validCommands = [
            'php8.3 artisan queue:work',
            'php8.4 artisan migrate',
            '/usr/bin/php8.3 artisan cache:clear',
            '/usr/bin/php8.4 -v',
        ];

        foreach ($validCommands as $command) {
            $failCalled = false;
            $fail = function () use (&$failCalled) {
                $failCalled = true;
            };

            // Act
            $rule->validate('command', $command, $fail);

            // Assert
            $this->assertFalse($failCalled, "Command '{$command}' with installed PHP should pass validation");
        }
    }

    /**
     * Test commands with uninstalled PHP version fail validation.
     */
    public function test_commands_with_uninstalled_php_version_fail_validation(): void
    {
        // Arrange
        $server = Server::factory()->create();
        ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'status' => 'active',
        ]);

        $rule = new ValidPhpCommand($server);

        $failCalled = false;
        $failMessage = '';
        $fail = function ($message) use (&$failCalled, &$failMessage) {
            $failCalled = true;
            $failMessage = $message;
        };

        // Act
        $rule->validate('command', 'php8.4 artisan queue:work', $fail);

        // Assert
        $this->assertTrue($failCalled, 'Command with uninstalled PHP version should fail validation');
        $this->assertStringContainsString('8.4', $failMessage);
        $this->assertStringContainsString('not installed', $failMessage);
        $this->assertStringContainsString('php8.3', $failMessage);
    }

    /**
     * Test commands with inactive PHP version fail validation.
     */
    public function test_commands_with_inactive_php_version_fail_validation(): void
    {
        // Arrange
        $server = Server::factory()->create();
        ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'status' => 'installing', // Not active
        ]);

        $rule = new ValidPhpCommand($server);

        $failCalled = false;
        $failMessage = '';
        $fail = function ($message) use (&$failCalled, &$failMessage) {
            $failCalled = true;
            $failMessage = $message;
        };

        // Act
        $rule->validate('command', 'php8.3 artisan migrate', $fail);

        // Assert
        $this->assertTrue($failCalled, 'Command with inactive PHP version should fail validation');
        $this->assertStringContainsString('not installed', $failMessage);
    }

    /**
     * Test error message shows available versions when some PHP is installed.
     */
    public function test_error_message_shows_available_versions(): void
    {
        // Arrange
        $server = Server::factory()->create();
        ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.2',
            'status' => 'active',
        ]);
        ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'status' => 'active',
        ]);

        $rule = new ValidPhpCommand($server);

        $failCalled = false;
        $failMessage = '';
        $fail = function ($message) use (&$failCalled, &$failMessage) {
            $failCalled = true;
            $failMessage = $message;
        };

        // Act
        $rule->validate('command', 'php8.4 artisan test', $fail);

        // Assert
        $this->assertTrue($failCalled);
        $this->assertStringContainsString('Available:', $failMessage);
        $this->assertStringContainsString('php8.2', $failMessage);
        $this->assertStringContainsString('php8.3', $failMessage);
    }

    /**
     * Test error message when no PHP versions are active.
     */
    public function test_error_message_when_no_php_versions_active(): void
    {
        // Arrange
        $server = Server::factory()->create();
        // No PHP installed

        $rule = new ValidPhpCommand($server);

        $failCalled = false;
        $failMessage = '';
        $fail = function ($message) use (&$failCalled, &$failMessage) {
            $failCalled = true;
            $failMessage = $message;
        };

        // Act
        $rule->validate('command', 'php8.4 artisan migrate', $fail);

        // Assert
        $this->assertTrue($failCalled);
        $this->assertStringContainsString('No PHP versions are currently active', $failMessage);
    }

    /**
     * Test non-string values are ignored.
     */
    public function test_non_string_values_are_ignored(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $rule = new ValidPhpCommand($server);
        $invalidValues = [
            123,
            ['command'],
            null,
            true,
        ];

        foreach ($invalidValues as $value) {
            $failCalled = false;
            $fail = function () use (&$failCalled) {
                $failCalled = true;
            };

            // Act
            $rule->validate('command', $value, $fail);

            // Assert
            $this->assertFalse($failCalled, 'Non-string values should be ignored (other rules handle type validation)');
        }
    }

    /**
     * Test PHP version patterns are correctly detected.
     */
    public function test_php_version_patterns_are_correctly_detected(): void
    {
        // Arrange
        $server = Server::factory()->create();
        ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'status' => 'active',
        ]);

        $rule = new ValidPhpCommand($server);

        // Commands that should trigger version check and fail (8.4 not installed)
        $commandsWithVersion = [
            'php8.4 artisan migrate',
            '/usr/bin/php8.4 artisan queue:work',
            'php8.4 -r "echo 1;"',
        ];

        foreach ($commandsWithVersion as $command) {
            $failCalled = false;
            $fail = function () use (&$failCalled) {
                $failCalled = true;
            };

            // Act
            $rule->validate('command', $command, $fail);

            // Assert
            $this->assertTrue($failCalled, "Command '{$command}' should trigger version validation");
        }
    }

    /**
     * Test null server skips validation gracefully.
     */
    public function test_null_server_skips_validation(): void
    {
        // Arrange
        $rule = new ValidPhpCommand(null);

        $commands = [
            'php artisan migrate',
            'php8.4 artisan queue:work',
            'php7.4 artisan cache:clear', // Even deprecated versions pass - no server context
        ];

        foreach ($commands as $command) {
            $failCalled = false;
            $fail = function () use (&$failCalled) {
                $failCalled = true;
            };

            // Act
            $rule->validate('command', $command, $fail);

            // Assert
            $this->assertFalse($failCalled, "Command '{$command}' should pass when no server context is available");
        }
    }
}
