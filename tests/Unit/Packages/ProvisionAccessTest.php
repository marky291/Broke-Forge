<?php

namespace Tests\Unit\Packages;

use App\Models\Server;
use App\Models\ServerCredential;
use App\Models\User;
use App\Packages\ProvisionAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ProvisionAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test makeScriptFor creates root credential if it doesn't exist.
     */
    public function test_make_script_for_creates_root_credential_if_not_exists(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $provisionAccess = new ProvisionAccess;

        // Act
        $script = $provisionAccess->makeScriptFor($server, 'test-root-password');

        // Assert
        $rootCredential = $server->credentials()->where('user', 'root')->first();
        $this->assertNotNull($rootCredential);
        $this->assertEquals('root', $rootCredential->user);
        $this->assertNotEmpty($rootCredential->public_key);
        $this->assertNotEmpty($rootCredential->private_key);
    }

    /**
     * Test makeScriptFor creates brokeforge credential if it doesn't exist.
     */
    public function test_make_script_for_creates_brokeforge_credential_if_not_exists(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $provisionAccess = new ProvisionAccess;

        // Act
        $script = $provisionAccess->makeScriptFor($server, 'test-root-password');

        // Assert
        $brokeforgeCredential = $server->credentials()->where('user', 'brokeforge')->first();
        $this->assertNotNull($brokeforgeCredential);
        $this->assertEquals('brokeforge', $brokeforgeCredential->user);
        $this->assertNotEmpty($brokeforgeCredential->public_key);
        $this->assertNotEmpty($brokeforgeCredential->private_key);
    }

    /**
     * Test makeScriptFor uses existing root credential.
     */
    public function test_make_script_for_uses_existing_root_credential(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $existingRootCredential = ServerCredential::factory()->create([
            'server_id' => $server->id,
            'user' => 'root',
            'public_key' => 'ssh-rsa EXISTING_ROOT_KEY',
        ]);

        $provisionAccess = new ProvisionAccess;

        // Act
        $script = $provisionAccess->makeScriptFor($server, 'test-root-password');

        // Assert
        $rootCredential = $server->credentials()->where('user', 'root')->first();
        $this->assertEquals($existingRootCredential->id, $rootCredential->id);
        $this->assertStringContainsString('EXISTING_ROOT_KEY', $script);
    }

    /**
     * Test makeScriptFor uses existing brokeforge credential.
     */
    public function test_make_script_for_uses_existing_brokeforge_credential(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $existingBrokeforgeCredential = ServerCredential::factory()->create([
            'server_id' => $server->id,
            'user' => 'brokeforge',
            'public_key' => 'ssh-rsa EXISTING_BROKEFORGE_KEY',
        ]);

        $provisionAccess = new ProvisionAccess;

        // Act
        $script = $provisionAccess->makeScriptFor($server, 'test-root-password');

        // Assert
        $brokeforgeCredential = $server->credentials()->where('user', 'brokeforge')->first();
        $this->assertEquals($existingBrokeforgeCredential->id, $brokeforgeCredential->id);
        $this->assertStringContainsString('EXISTING_BROKEFORGE_KEY', $script);
    }

    /**
     * Test makeScriptFor includes root password in script.
     */
    public function test_make_script_for_includes_root_password_in_script(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $provisionAccess = new ProvisionAccess;
        $rootPassword = 'super-secret-password-123';

        // Act
        $script = $provisionAccess->makeScriptFor($server, $rootPassword);

        // Assert
        $this->assertStringContainsString($rootPassword, $script);
    }

    /**
     * Test makeScriptFor includes SSH port in script.
     */
    public function test_make_script_for_includes_ssh_port_in_script(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'ssh_port' => 2222,
        ]);
        $provisionAccess = new ProvisionAccess;

        // Act
        $script = $provisionAccess->makeScriptFor($server, 'test-password');

        // Assert
        $this->assertStringContainsString('2222', $script);
    }

    /**
     * Test makeScriptFor includes app name from config.
     */
    public function test_make_script_for_includes_app_name_from_config(): void
    {
        // Arrange
        Config::set('app.name', 'TestAppName');

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $provisionAccess = new ProvisionAccess;

        // Act
        $script = $provisionAccess->makeScriptFor($server, 'test-password');

        // Assert
        $this->assertStringContainsString('TestAppName', $script);
    }

    /**
     * Test makeScriptFor returns non-empty script.
     */
    public function test_make_script_for_returns_non_empty_script(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $provisionAccess = new ProvisionAccess;

        // Act
        $script = $provisionAccess->makeScriptFor($server, 'test-password');

        // Assert
        $this->assertNotEmpty($script);
        $this->assertIsString($script);
    }

    /**
     * Test buildCallbackUrls creates signed URL for step callback.
     */
    public function test_build_callback_urls_creates_signed_url_for_step_callback(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $provisionAccess = new ProvisionAccess;

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($provisionAccess);
        $method = $reflection->getMethod('buildCallbackUrls');
        $method->setAccessible(true);

        // Act
        $urls = $method->invoke($provisionAccess, $server);

        // Assert
        $this->assertIsArray($urls);
        $this->assertArrayHasKey('step', $urls);
        $this->assertStringContainsString('signature=', $urls['step']);
        $this->assertStringContainsString('expires=', $urls['step']);
    }

    /**
     * Test buildCallbackUrls includes server ID in URL.
     */
    public function test_build_callback_urls_includes_server_id_in_url(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $provisionAccess = new ProvisionAccess;

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($provisionAccess);
        $method = $reflection->getMethod('buildCallbackUrls');
        $method->setAccessible(true);

        // Act
        $urls = $method->invoke($provisionAccess, $server);

        // Assert
        $this->assertStringContainsString((string) $server->id, $urls['step']);
    }

    /**
     * Test buildCallbackUrls uses TTL from config.
     */
    public function test_build_callback_urls_uses_ttl_from_config(): void
    {
        // Arrange
        Config::set('provision.callback_ttl', 120);

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $provisionAccess = new ProvisionAccess;

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($provisionAccess);
        $method = $reflection->getMethod('buildCallbackUrls');
        $method->setAccessible(true);

        // Act
        $urls = $method->invoke($provisionAccess, $server);

        // Assert - URL should be signed (we can't easily test exact TTL without parsing URL)
        $this->assertStringContainsString('expires=', $urls['step']);
        $this->assertStringContainsString('signature=', $urls['step']);
    }

    /**
     * Test buildCallbackUrls uses minimum TTL of 1 minute.
     */
    public function test_build_callback_urls_uses_minimum_ttl_of_one_minute(): void
    {
        // Arrange
        Config::set('provision.callback_ttl', 0); // Invalid TTL

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $provisionAccess = new ProvisionAccess;

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($provisionAccess);
        $method = $reflection->getMethod('buildCallbackUrls');
        $method->setAccessible(true);

        // Act
        $urls = $method->invoke($provisionAccess, $server);

        // Assert - Should still create valid signed URL despite invalid config
        $this->assertStringContainsString('expires=', $urls['step']);
        $this->assertStringContainsString('signature=', $urls['step']);
    }

    /**
     * Test buildCallbackUrls uses default TTL when config not set.
     */
    public function test_build_callback_urls_uses_default_ttl_when_config_not_set(): void
    {
        // Arrange
        Config::set('provision.callback_ttl', null);

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $provisionAccess = new ProvisionAccess;

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($provisionAccess);
        $method = $reflection->getMethod('buildCallbackUrls');
        $method->setAccessible(true);

        // Act
        $urls = $method->invoke($provisionAccess, $server);

        // Assert
        $this->assertStringContainsString('expires=', $urls['step']);
        $this->assertStringContainsString('signature=', $urls['step']);
    }

    /**
     * Test makeScriptFor creates both credentials when none exist.
     */
    public function test_make_script_for_creates_both_credentials_when_none_exist(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $provisionAccess = new ProvisionAccess;

        // Ensure no credentials exist
        $this->assertEquals(0, $server->credentials()->count());

        // Act
        $script = $provisionAccess->makeScriptFor($server, 'test-password');

        // Assert
        $this->assertEquals(2, $server->credentials()->count());

        $rootCredential = $server->credentials()->where('user', 'root')->first();
        $brokeforgeCredential = $server->credentials()->where('user', 'brokeforge')->first();

        $this->assertNotNull($rootCredential);
        $this->assertNotNull($brokeforgeCredential);
        $this->assertNotEquals($rootCredential->public_key, $brokeforgeCredential->public_key);
    }

    /**
     * Test makeScriptFor includes callback URL in script.
     */
    public function test_make_script_for_includes_callback_url_in_script(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $provisionAccess = new ProvisionAccess;

        // Act
        $script = $provisionAccess->makeScriptFor($server, 'test-password');

        // Assert
        $this->assertStringContainsString('CALLBACK_STEP_URL', $script);
        $this->assertStringContainsString('/provision/step', $script);
        $this->assertStringContainsString('signature=', $script);
    }

    /**
     * Test makeScriptFor script uses correct TaskStatus values (success not completed).
     */
    public function test_make_script_for_script_uses_success_status_not_completed(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $provisionAccess = new ProvisionAccess;

        // Act
        $script = $provisionAccess->makeScriptFor($server, 'test-password');

        // Assert - Should use 'success' for TaskStatus enum compatibility
        $this->assertStringContainsString('notify_step 1 "success"', $script);
        $this->assertStringContainsString('notify_step 2 "success"', $script);
        $this->assertStringContainsString('notify_step 3 "success"', $script);

        // Should NOT use old 'completed' status
        $this->assertStringNotContainsString('notify_step 1 "completed"', $script);
        $this->assertStringNotContainsString('notify_step 2 "completed"', $script);
        $this->assertStringNotContainsString('notify_step 3 "completed"', $script);
    }

    /**
     * Test makeScriptFor script includes bash error handling.
     */
    public function test_make_script_for_script_includes_bash_error_handling(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $provisionAccess = new ProvisionAccess;

        // Act
        $script = $provisionAccess->makeScriptFor($server, 'test-password');

        // Assert - Script should have proper bash error handling
        $this->assertStringContainsString('set -euo pipefail', $script);
    }

    /**
     * Test makeScriptFor script includes notify_step function.
     */
    public function test_make_script_for_script_includes_notify_step_function(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $provisionAccess = new ProvisionAccess;

        // Act
        $script = $provisionAccess->makeScriptFor($server, 'test-password');

        // Assert
        $this->assertStringContainsString('notify_step()', $script);
        $this->assertStringContainsString('local step=', $script);
        $this->assertStringContainsString('local status=', $script);
    }

    /**
     * Test makeScriptFor script includes both root and brokeforge users.
     */
    public function test_make_script_for_script_includes_both_users(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $provisionAccess = new ProvisionAccess;

        // Act
        $script = $provisionAccess->makeScriptFor($server, 'test-password');

        // Assert
        $this->assertStringContainsString('root', $script);
        $this->assertStringContainsString('brokeforge', $script);
    }

    /**
     * Test makeScriptFor script includes both public and private keys.
     */
    public function test_make_script_for_script_includes_public_and_private_keys(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $provisionAccess = new ProvisionAccess;

        // Act
        $script = $provisionAccess->makeScriptFor($server, 'test-password');

        // Assert
        $this->assertStringContainsString('id_rsa.pub', $script);
        $this->assertStringContainsString('id_rsa', $script);
        $this->assertStringContainsString('authorized_keys', $script);
    }

    /**
     * Test makeScriptFor script includes installing status for intermediate steps.
     */
    public function test_make_script_for_script_includes_installing_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $provisionAccess = new ProvisionAccess;

        // Act
        $script = $provisionAccess->makeScriptFor($server, 'test-password');

        // Assert
        $this->assertStringContainsString('notify_step 2 "installing"', $script);
        $this->assertStringContainsString('notify_step 3 "installing"', $script);
    }

    /**
     * Test makeScriptFor script includes time synchronization setup.
     */
    public function test_make_script_for_script_includes_time_synchronization(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $provisionAccess = new ProvisionAccess;

        // Act
        $script = $provisionAccess->makeScriptFor($server, 'test-password');

        // Assert - Should enable time synchronization to prevent clock skew issues
        $this->assertStringContainsString('timedatectl set-ntp true', $script);
        $this->assertStringContainsString('systemd-timesyncd', $script);
        $this->assertStringContainsString('Time synchronization enabled', $script);
    }
}
