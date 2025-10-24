<?php

namespace Tests\Unit\Packages\Services\Firewall;

use App\Models\Server;
use App\Packages\Services\Firewall\FirewallRuleInstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class FirewallRuleInstallerTest extends TestCase
{
    use RefreshDatabase;

    private function invokeProtectedMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Test simple port allow without source/destination generates correct UFW command.
     */
    public function test_builds_simple_allow_command_without_source(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $installer = new FirewallRuleInstaller($server);

        $rule = [
            'port' => 80,
            'protocol' => 'tcp',
            'action' => 'allow',
        ];

        // Act
        $command = $this->invokeProtectedMethod($installer, 'buildUfwCommand', [$rule]);

        // Assert
        $this->assertEquals('ufw allow 80/tcp', $command);
    }

    /**
     * Test port allow with source IP uses extended syntax.
     */
    public function test_builds_allow_command_with_source_ip(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $installer = new FirewallRuleInstaller($server);

        $rule = [
            'port' => 80,
            'protocol' => 'tcp',
            'action' => 'allow',
            'source' => '192.168.0.1',
        ];

        // Act
        $command = $this->invokeProtectedMethod($installer, 'buildUfwCommand', [$rule]);

        // Assert
        $this->assertEquals('ufw allow from 192.168.0.1 to any port 80 proto tcp', $command);
    }

    /**
     * Test port allow with source subnet uses extended syntax.
     */
    public function test_builds_allow_command_with_source_subnet(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $installer = new FirewallRuleInstaller($server);

        $rule = [
            'port' => 3306,
            'protocol' => 'tcp',
            'action' => 'allow',
            'source' => '10.0.0.0/8',
        ];

        // Act
        $command = $this->invokeProtectedMethod($installer, 'buildUfwCommand', [$rule]);

        // Assert
        $this->assertEquals('ufw allow from 10.0.0.0/8 to any port 3306 proto tcp', $command);
    }

    /**
     * Test port allow with destination IP uses extended syntax.
     */
    public function test_builds_allow_command_with_destination_ip(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $installer = new FirewallRuleInstaller($server);

        $rule = [
            'port' => 443,
            'protocol' => 'tcp',
            'action' => 'allow',
            'destination' => '10.0.0.1',
        ];

        // Act
        $command = $this->invokeProtectedMethod($installer, 'buildUfwCommand', [$rule]);

        // Assert
        $this->assertEquals('ufw allow to 10.0.0.1 port 443 proto tcp', $command);
    }

    /**
     * Test port allow with both source and destination uses extended syntax.
     */
    public function test_builds_allow_command_with_source_and_destination(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $installer = new FirewallRuleInstaller($server);

        $rule = [
            'port' => 22,
            'protocol' => 'tcp',
            'action' => 'allow',
            'source' => '192.168.1.0/24',
            'destination' => '10.0.0.5',
        ];

        // Act
        $command = $this->invokeProtectedMethod($installer, 'buildUfwCommand', [$rule]);

        // Assert
        $this->assertEquals('ufw allow from 192.168.1.0/24 to 10.0.0.5 port 22 proto tcp', $command);
    }

    /**
     * Test simple command with comment.
     */
    public function test_builds_simple_command_with_comment(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $installer = new FirewallRuleInstaller($server);

        $rule = [
            'port' => 80,
            'protocol' => 'tcp',
            'action' => 'allow',
            'comment' => 'HTTP',
        ];

        // Act
        $command = $this->invokeProtectedMethod($installer, 'buildUfwCommand', [$rule]);

        // Assert
        $this->assertEquals("ufw allow 80/tcp comment 'HTTP'", $command);
    }

    /**
     * Test extended command with comment.
     */
    public function test_builds_extended_command_with_comment(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $installer = new FirewallRuleInstaller($server);

        $rule = [
            'port' => 3306,
            'protocol' => 'tcp',
            'action' => 'allow',
            'source' => '10.0.0.0/8',
            'comment' => 'MySQL Internal',
        ];

        // Act
        $command = $this->invokeProtectedMethod($installer, 'buildUfwCommand', [$rule]);

        // Assert
        $this->assertEquals("ufw allow from 10.0.0.0/8 to any port 3306 proto tcp comment 'MySQL Internal'", $command);
    }

    /**
     * Test deny action works correctly.
     */
    public function test_builds_deny_command(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $installer = new FirewallRuleInstaller($server);

        $rule = [
            'port' => 23,
            'protocol' => 'tcp',
            'action' => 'deny',
        ];

        // Act
        $command = $this->invokeProtectedMethod($installer, 'buildUfwCommand', [$rule]);

        // Assert
        $this->assertEquals('ufw deny 23/tcp', $command);
    }

    /**
     * Test deny with source uses extended syntax.
     */
    public function test_builds_deny_command_with_source(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $installer = new FirewallRuleInstaller($server);

        $rule = [
            'port' => 23,
            'protocol' => 'tcp',
            'action' => 'deny',
            'source' => '192.168.1.100',
        ];

        // Act
        $command = $this->invokeProtectedMethod($installer, 'buildUfwCommand', [$rule]);

        // Assert
        $this->assertEquals('ufw deny from 192.168.1.100 to any port 23 proto tcp', $command);
    }

    /**
     * Test UDP protocol works correctly.
     */
    public function test_builds_command_with_udp_protocol(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $installer = new FirewallRuleInstaller($server);

        $rule = [
            'port' => 53,
            'protocol' => 'udp',
            'action' => 'allow',
        ];

        // Act
        $command = $this->invokeProtectedMethod($installer, 'buildUfwCommand', [$rule]);

        // Assert
        $this->assertEquals('ufw allow 53/udp', $command);
    }

    /**
     * Test UDP with source uses extended syntax.
     */
    public function test_builds_command_with_udp_and_source(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $installer = new FirewallRuleInstaller($server);

        $rule = [
            'port' => 53,
            'protocol' => 'udp',
            'action' => 'allow',
            'source' => '8.8.8.8',
        ];

        // Act
        $command = $this->invokeProtectedMethod($installer, 'buildUfwCommand', [$rule]);

        // Assert
        $this->assertEquals('ufw allow from 8.8.8.8 to any port 53 proto udp', $command);
    }

    /**
     * Test default action is 'allow' when not specified.
     */
    public function test_defaults_to_allow_action(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $installer = new FirewallRuleInstaller($server);

        $rule = [
            'port' => 8080,
            'protocol' => 'tcp',
        ];

        // Act
        $command = $this->invokeProtectedMethod($installer, 'buildUfwCommand', [$rule]);

        // Assert
        $this->assertEquals('ufw allow 8080/tcp', $command);
    }

    /**
     * Test default protocol is 'tcp' when not specified.
     */
    public function test_defaults_to_tcp_protocol(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $installer = new FirewallRuleInstaller($server);

        $rule = [
            'port' => 8080,
            'action' => 'allow',
        ];

        // Act
        $command = $this->invokeProtectedMethod($installer, 'buildUfwCommand', [$rule]);

        // Assert
        $this->assertEquals('ufw allow 8080/tcp', $command);
    }

    /**
     * Test comment with special characters is properly escaped.
     */
    public function test_escapes_comment_with_special_characters(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $installer = new FirewallRuleInstaller($server);

        $rule = [
            'port' => 80,
            'protocol' => 'tcp',
            'action' => 'allow',
            'comment' => "HTTP's Port",
        ];

        // Act
        $command = $this->invokeProtectedMethod($installer, 'buildUfwCommand', [$rule]);

        // Assert - escapeshellarg wraps in single quotes and escapes internal single quotes
        $this->assertStringContainsString('comment', $command);
        $this->assertStringContainsString('HTTP', $command);
    }

    /**
     * Test port range works correctly.
     */
    public function test_builds_command_with_port_range(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $installer = new FirewallRuleInstaller($server);

        $rule = [
            'port' => '3000:3005',
            'protocol' => 'tcp',
            'action' => 'allow',
        ];

        // Act
        $command = $this->invokeProtectedMethod($installer, 'buildUfwCommand', [$rule]);

        // Assert
        $this->assertEquals('ufw allow 3000:3005/tcp', $command);
    }

    /**
     * Test port range with source uses extended syntax.
     */
    public function test_builds_command_with_port_range_and_source(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $installer = new FirewallRuleInstaller($server);

        $rule = [
            'port' => '3000:3005',
            'protocol' => 'tcp',
            'action' => 'allow',
            'source' => '192.168.1.0/24',
        ];

        // Act
        $command = $this->invokeProtectedMethod($installer, 'buildUfwCommand', [$rule]);

        // Assert
        $this->assertEquals('ufw allow from 192.168.1.0/24 to any port 3000:3005 proto tcp', $command);
    }
}
