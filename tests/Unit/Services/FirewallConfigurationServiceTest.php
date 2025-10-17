<?php

namespace Tests\Unit\Services;

use App\Services\FirewallConfigurationService;
use Tests\TestCase;

class FirewallConfigurationServiceTest extends TestCase
{
    /**
     * Test validatePortRange returns true for valid single port.
     */
    public function test_validate_port_range_returns_true_for_valid_single_port(): void
    {
        // Arrange
        $service = new FirewallConfigurationService;

        // Act & Assert
        $this->assertTrue($service->validatePortRange('80'));
        $this->assertTrue($service->validatePortRange('443'));
        $this->assertTrue($service->validatePortRange('22'));
        $this->assertTrue($service->validatePortRange('1'));
        $this->assertTrue($service->validatePortRange('65535'));
    }

    /**
     * Test validatePortRange returns false for invalid single port.
     */
    public function test_validate_port_range_returns_false_for_invalid_single_port(): void
    {
        // Arrange
        $service = new FirewallConfigurationService;

        // Act & Assert
        $this->assertFalse($service->validatePortRange('0'));
        $this->assertFalse($service->validatePortRange('65536'));
        $this->assertFalse($service->validatePortRange('70000'));
        $this->assertFalse($service->validatePortRange('-1'));
        $this->assertFalse($service->validatePortRange('abc'));
    }

    /**
     * Test validatePortRange returns true for valid port range.
     */
    public function test_validate_port_range_returns_true_for_valid_port_range(): void
    {
        // Arrange
        $service = new FirewallConfigurationService;

        // Act & Assert
        $this->assertTrue($service->validatePortRange('8000:8100'));
        $this->assertTrue($service->validatePortRange('1:65535'));
        $this->assertTrue($service->validatePortRange('3000:3500'));
        $this->assertTrue($service->validatePortRange('80:80')); // Same start and end
    }

    /**
     * Test validatePortRange returns false for invalid port range.
     */
    public function test_validate_port_range_returns_false_for_invalid_port_range(): void
    {
        // Arrange
        $service = new FirewallConfigurationService;

        // Act & Assert
        $this->assertFalse($service->validatePortRange('8100:8000')); // Start > end
        $this->assertFalse($service->validatePortRange('0:100')); // Start < 1
        $this->assertFalse($service->validatePortRange('100:70000')); // End > 65535
        $this->assertFalse($service->validatePortRange('abc:def')); // Non-numeric
        $this->assertFalse($service->validatePortRange('100:')); // Missing end
        $this->assertFalse($service->validatePortRange(':100')); // Missing start
    }

    /**
     * Test validatePortRange handles edge cases.
     */
    public function test_validate_port_range_handles_edge_cases(): void
    {
        // Arrange
        $service = new FirewallConfigurationService;

        // Act & Assert
        $this->assertFalse($service->validatePortRange(''));
        $this->assertFalse($service->validatePortRange('  '));
        $this->assertFalse($service->validatePortRange('80:'));
        $this->assertFalse($service->validatePortRange(':443'));
    }

    /**
     * Test getDefaultRules returns array of default rules.
     */
    public function test_get_default_rules_returns_array_of_default_rules(): void
    {
        // Arrange
        $service = new FirewallConfigurationService;

        // Act
        $rules = $service->getDefaultRules();

        // Assert
        $this->assertIsArray($rules);
        $this->assertCount(3, $rules);
    }

    /**
     * Test getDefaultRules includes SSH rule.
     */
    public function test_get_default_rules_includes_ssh_rule(): void
    {
        // Arrange
        $service = new FirewallConfigurationService;

        // Act
        $rules = $service->getDefaultRules();

        // Assert
        $sshRule = collect($rules)->firstWhere('port', '22');
        $this->assertNotNull($sshRule);
        $this->assertEquals('SSH Access', $sshRule['name']);
        $this->assertEquals('allow', $sshRule['rule_type']);
        $this->assertTrue($sshRule['required']);
        $this->assertNull($sshRule['from_ip_address']);
    }

    /**
     * Test getDefaultRules includes HTTP rule.
     */
    public function test_get_default_rules_includes_http_rule(): void
    {
        // Arrange
        $service = new FirewallConfigurationService;

        // Act
        $rules = $service->getDefaultRules();

        // Assert
        $httpRule = collect($rules)->firstWhere('port', '80');
        $this->assertNotNull($httpRule);
        $this->assertEquals('HTTP Traffic', $httpRule['name']);
        $this->assertEquals('allow', $httpRule['rule_type']);
        $this->assertFalse($httpRule['required']);
    }

    /**
     * Test getDefaultRules includes HTTPS rule.
     */
    public function test_get_default_rules_includes_https_rule(): void
    {
        // Arrange
        $service = new FirewallConfigurationService;

        // Act
        $rules = $service->getDefaultRules();

        // Assert
        $httpsRule = collect($rules)->firstWhere('port', '443');
        $this->assertNotNull($httpsRule);
        $this->assertEquals('HTTPS Traffic', $httpsRule['name']);
        $this->assertEquals('allow', $httpsRule['rule_type']);
        $this->assertFalse($httpsRule['required']);
    }

    /**
     * Test getCommonPorts returns port mappings.
     */
    public function test_get_common_ports_returns_port_mappings(): void
    {
        // Arrange
        $service = new FirewallConfigurationService;

        // Act
        $ports = $service->getCommonPorts();

        // Assert
        $this->assertIsArray($ports);
        $this->assertArrayHasKey('22', $ports);
        $this->assertArrayHasKey('80', $ports);
        $this->assertArrayHasKey('443', $ports);
        $this->assertArrayHasKey('3306', $ports);
        $this->assertArrayHasKey('5432', $ports);
    }

    /**
     * Test getCommonPorts includes correct service names.
     */
    public function test_get_common_ports_includes_correct_service_names(): void
    {
        // Arrange
        $service = new FirewallConfigurationService;

        // Act
        $ports = $service->getCommonPorts();

        // Assert
        $this->assertEquals('SSH', $ports['22']);
        $this->assertEquals('HTTP', $ports['80']);
        $this->assertEquals('HTTPS', $ports['443']);
        $this->assertEquals('MySQL', $ports['3306']);
        $this->assertEquals('PostgreSQL', $ports['5432']);
        $this->assertEquals('Redis', $ports['6379']);
    }

    /**
     * Test getRuleTypeOptions returns allow and deny options.
     */
    public function test_get_rule_type_options_returns_allow_and_deny_options(): void
    {
        // Arrange
        $service = new FirewallConfigurationService;

        // Act
        $options = $service->getRuleTypeOptions();

        // Assert
        $this->assertIsArray($options);
        $this->assertArrayHasKey('allow', $options);
        $this->assertArrayHasKey('deny', $options);
    }

    /**
     * Test getRuleTypeOptions includes required metadata.
     */
    public function test_get_rule_type_options_includes_required_metadata(): void
    {
        // Arrange
        $service = new FirewallConfigurationService;

        // Act
        $options = $service->getRuleTypeOptions();

        // Assert
        $this->assertEquals('Allow', $options['allow']['label']);
        $this->assertEquals('green', $options['allow']['color']);
        $this->assertArrayHasKey('description', $options['allow']);

        $this->assertEquals('Deny', $options['deny']['label']);
        $this->assertEquals('red', $options['deny']['color']);
        $this->assertArrayHasKey('description', $options['deny']);
    }

    /**
     * Test getFirewallStatus returns status messages.
     */
    public function test_get_firewall_status_returns_status_messages(): void
    {
        // Arrange
        $service = new FirewallConfigurationService;

        // Act
        $statuses = $service->getFirewallStatus();

        // Assert
        $this->assertIsArray($statuses);
        $this->assertArrayHasKey('enabled', $statuses);
        $this->assertArrayHasKey('disabled', $statuses);
        $this->assertArrayHasKey('not_installed', $statuses);
    }

    /**
     * Test generateUfwCommand creates basic allow command.
     */
    public function test_generate_ufw_command_creates_basic_allow_command(): void
    {
        // Arrange
        $service = new FirewallConfigurationService;
        $ruleData = [
            'rule_type' => 'allow',
            'port' => '80',
            'from_ip_address' => null,
        ];

        // Act
        $command = $service->generateUfwCommand($ruleData);

        // Assert
        $this->assertEquals('ufw allow to any port 80', $command);
    }

    /**
     * Test generateUfwCommand creates allow command with IP restriction.
     */
    public function test_generate_ufw_command_creates_allow_command_with_ip_restriction(): void
    {
        // Arrange
        $service = new FirewallConfigurationService;
        $ruleData = [
            'rule_type' => 'allow',
            'port' => '22',
            'from_ip_address' => '192.168.1.100',
        ];

        // Act
        $command = $service->generateUfwCommand($ruleData);

        // Assert
        $this->assertEquals('ufw allow from 192.168.1.100 to any port 22', $command);
    }

    /**
     * Test generateUfwCommand creates deny command.
     */
    public function test_generate_ufw_command_creates_deny_command(): void
    {
        // Arrange
        $service = new FirewallConfigurationService;
        $ruleData = [
            'rule_type' => 'deny',
            'port' => '3306',
            'from_ip_address' => null,
        ];

        // Act
        $command = $service->generateUfwCommand($ruleData);

        // Assert
        $this->assertEquals('ufw deny to any port 3306', $command);
    }

    /**
     * Test generateUfwCommand creates deny command with IP restriction.
     */
    public function test_generate_ufw_command_creates_deny_command_with_ip_restriction(): void
    {
        // Arrange
        $service = new FirewallConfigurationService;
        $ruleData = [
            'rule_type' => 'deny',
            'port' => '3306',
            'from_ip_address' => '10.0.0.0/8',
        ];

        // Act
        $command = $service->generateUfwCommand($ruleData);

        // Assert
        $this->assertEquals('ufw deny from 10.0.0.0/8 to any port 3306', $command);
    }

    /**
     * Test generateUfwCommand handles port ranges.
     */
    public function test_generate_ufw_command_handles_port_ranges(): void
    {
        // Arrange
        $service = new FirewallConfigurationService;
        $ruleData = [
            'rule_type' => 'allow',
            'port' => '8000:8100',
            'from_ip_address' => null,
        ];

        // Act
        $command = $service->generateUfwCommand($ruleData);

        // Assert
        $this->assertEquals('ufw allow to any port 8000:8100', $command);
    }

    /**
     * Test validatePortRange boundary values.
     */
    public function test_validate_port_range_boundary_values(): void
    {
        // Arrange
        $service = new FirewallConfigurationService;

        // Act & Assert - Minimum boundary
        $this->assertTrue($service->validatePortRange('1'));
        $this->assertFalse($service->validatePortRange('0'));

        // Maximum boundary
        $this->assertTrue($service->validatePortRange('65535'));
        $this->assertFalse($service->validatePortRange('65536'));

        // Range boundaries
        $this->assertTrue($service->validatePortRange('1:65535'));
        $this->assertFalse($service->validatePortRange('0:65535'));
        $this->assertFalse($service->validatePortRange('1:65536'));
    }

    /**
     * Test all default rules have required fields.
     */
    public function test_all_default_rules_have_required_fields(): void
    {
        // Arrange
        $service = new FirewallConfigurationService;
        $requiredFields = ['name', 'port', 'rule_type', 'from_ip_address', 'description', 'required'];

        // Act
        $rules = $service->getDefaultRules();

        // Assert
        foreach ($rules as $rule) {
            foreach ($requiredFields as $field) {
                $this->assertArrayHasKey($field, $rule, "Default rule missing required field: {$field}");
            }
        }
    }
}
