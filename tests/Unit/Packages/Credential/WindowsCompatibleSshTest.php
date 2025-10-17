<?php

namespace Tests\Unit\Packages\Credential;

use App\Packages\Credential\WindowsCompatibleSsh;
use Tests\TestCase;

class WindowsCompatibleSshTest extends TestCase
{
    /**
     * Test returns command string directly for localhost.
     */
    public function test_returns_command_string_directly_for_localhost(): void
    {
        // Arrange
        $ssh = WindowsCompatibleSsh::create('testuser', 'localhost');
        $command = 'echo "test"';

        // Act
        $result = $ssh->getExecuteCommand($command);

        // Assert - localhost should return command directly without SSH wrapper
        $this->assertEquals('echo "test"', $result);
    }

    /**
     * Test returns command string directly for local.
     */
    public function test_returns_command_string_directly_for_local(): void
    {
        // Arrange
        $ssh = WindowsCompatibleSsh::create('testuser', 'local');
        $command = 'ls -la';

        // Act
        $result = $ssh->getExecuteCommand($command);

        // Assert - local should return command directly
        $this->assertEquals('ls -la', $result);
    }

    /**
     * Test returns command string directly for 127.0.0.1.
     */
    public function test_returns_command_string_directly_for_127_0_0_1(): void
    {
        // Arrange
        $ssh = WindowsCompatibleSsh::create('testuser', '127.0.0.1');
        $command = 'pwd';

        // Act
        $result = $ssh->getExecuteCommand($command);

        // Assert - 127.0.0.1 should return command directly
        $this->assertEquals('pwd', $result);
    }

    /**
     * Test handles array of commands for localhost.
     */
    public function test_handles_array_of_commands_for_localhost(): void
    {
        // Arrange
        $ssh = WindowsCompatibleSsh::create('testuser', 'localhost');
        $commands = [
            'cd /tmp',
            'ls -la',
            'pwd',
        ];

        // Act
        $result = $ssh->getExecuteCommand($commands);

        // Assert - multiple commands should be joined with newlines
        $expected = implode(PHP_EOL, $commands);
        $this->assertEquals($expected, $result);
    }

    /**
     * Test creates valid SSH instance.
     */
    public function test_creates_valid_ssh_instance(): void
    {
        // Arrange & Act
        $ssh = WindowsCompatibleSsh::create('root', '192.168.1.1', 22);

        // Assert
        $this->assertInstanceOf(WindowsCompatibleSsh::class, $ssh);
    }

    /**
     * Test accepts custom SSH port.
     */
    public function test_accepts_custom_ssh_port(): void
    {
        // Arrange & Act
        $ssh = WindowsCompatibleSsh::create('brokeforge', '10.0.0.1', 2222);

        // Assert
        $this->assertInstanceOf(WindowsCompatibleSsh::class, $ssh);
    }

    /**
     * Test can chain configuration methods.
     */
    public function test_can_chain_configuration_methods(): void
    {
        // Arrange & Act
        $ssh = WindowsCompatibleSsh::create('testuser', '192.168.1.100')
            ->disableStrictHostKeyChecking()
            ->enableQuietMode()
            ->addExtraOption('-o ConnectTimeout=30');

        // Assert
        $this->assertInstanceOf(WindowsCompatibleSsh::class, $ssh);
    }

    /**
     * Test remote host command includes SSH wrapper (OS-specific).
     */
    public function test_remote_host_command_includes_ssh_wrapper(): void
    {
        // Arrange
        $ssh = WindowsCompatibleSsh::create('testuser', '192.168.1.50');
        $command = 'echo "hello"';

        // Act
        $result = $ssh->getExecuteCommand($command);

        // Assert - remote host should wrap command in SSH
        if (PHP_OS_FAMILY === 'Windows') {
            // On Windows, should use base64 encoding
            $this->assertStringContainsString('ssh', $result);
            $this->assertStringContainsString('testuser@192.168.1.50', $result);
            $this->assertStringContainsString('base64 -d', $result);
        } else {
            // On Unix, falls back to parent (heredoc)
            $this->assertStringContainsString('ssh', $result);
            $this->assertStringContainsString('testuser@192.168.1.50', $result);
        }
    }

    /**
     * Test Windows uses base64 encoding for complex commands (Windows only).
     */
    public function test_windows_uses_base64_encoding_for_complex_commands(): void
    {
        // Skip if not on Windows
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('This test only runs on Windows');
        }

        // Arrange
        $ssh = WindowsCompatibleSsh::create('root', '192.168.1.100');
        $command = 'echo "test" && ls -la';

        // Act
        $result = $ssh->getExecuteCommand($command);

        // Assert - should use base64 encoding on Windows
        $this->assertStringContainsString('base64 -d', $result);
        $this->assertStringContainsString('bash', $result);

        // Verify the command is base64 encoded in the result
        $base64Command = base64_encode($command);
        $this->assertStringContainsString($base64Command, $result);
    }

    /**
     * Test Unix uses parent implementation (Unix only).
     */
    public function test_unix_uses_parent_implementation(): void
    {
        // Skip if on Windows
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('This test only runs on Unix/Linux/macOS');
        }

        // Arrange
        $ssh = WindowsCompatibleSsh::create('root', '192.168.1.100');
        $command = 'echo "test"';

        // Act
        $result = $ssh->getExecuteCommand($command);

        // Assert - on Unix, should use parent's heredoc implementation
        $this->assertStringContainsString('ssh', $result);
        $this->assertStringContainsString('root@192.168.1.100', $result);

        // Parent implementation uses heredoc, so shouldn't have base64
        $this->assertStringNotContainsString('base64 -d', $result);
    }

    /**
     * Test private key configuration works.
     */
    public function test_private_key_configuration_works(): void
    {
        // Arrange
        $keyPath = '/tmp/test_key';

        // Act
        $ssh = WindowsCompatibleSsh::create('testuser', 'localhost')
            ->usePrivateKey($keyPath);

        // Assert
        $this->assertInstanceOf(WindowsCompatibleSsh::class, $ssh);
    }

    /**
     * Test handles multiple extra options.
     */
    public function test_handles_multiple_extra_options(): void
    {
        // Arrange & Act
        $ssh = WindowsCompatibleSsh::create('testuser', '192.168.1.1')
            ->addExtraOption('-o ConnectTimeout=60')
            ->addExtraOption('-o ServerAliveInterval=15')
            ->addExtraOption('-o ServerAliveCountMax=3');

        // Assert
        $this->assertInstanceOf(WindowsCompatibleSsh::class, $ssh);
    }

    /**
     * Test localhost behavior is consistent across OS.
     */
    public function test_localhost_behavior_is_consistent_across_os(): void
    {
        // Arrange
        $testCases = [
            ['host' => 'localhost', 'command' => 'echo "test"', 'expected' => 'echo "test"'],
            ['host' => 'local', 'command' => 'ls', 'expected' => 'ls'],
            ['host' => '127.0.0.1', 'command' => 'pwd', 'expected' => 'pwd'],
        ];

        foreach ($testCases as $testCase) {
            // Act
            $ssh = WindowsCompatibleSsh::create('user', $testCase['host']);
            $result = $ssh->getExecuteCommand($testCase['command']);

            // Assert
            $this->assertEquals(
                $testCase['expected'],
                $result,
                "Failed for host: {$testCase['host']}"
            );
        }
    }

    /**
     * Test command string uses PHP_EOL for line breaks.
     */
    public function test_command_string_uses_php_eol_for_line_breaks(): void
    {
        // Arrange
        $ssh = WindowsCompatibleSsh::create('testuser', 'localhost');
        $commands = ['command1', 'command2', 'command3'];

        // Act
        $result = $ssh->getExecuteCommand($commands);

        // Assert - commands should be joined with PHP_EOL
        $expected = implode(PHP_EOL, $commands);
        $this->assertEquals($expected, $result);
    }
}
