<?php

namespace App\Services;

class FirewallConfigurationService
{
    public function getDefaultRules(): array
    {
        return [
            [
                'name' => 'SSH Access',
                'port' => '22',
                'rule_type' => 'allow',
                'from_ip_address' => null,
                'description' => 'Allow SSH access from anywhere',
                'required' => true,
            ],
            [
                'name' => 'HTTP Traffic',
                'port' => '80',
                'rule_type' => 'allow',
                'from_ip_address' => null,
                'description' => 'Allow HTTP web traffic',
                'required' => false,
            ],
            [
                'name' => 'HTTPS Traffic',
                'port' => '443',
                'rule_type' => 'allow',
                'from_ip_address' => null,
                'description' => 'Allow HTTPS web traffic',
                'required' => false,
            ],
        ];
    }

    public function getCommonPorts(): array
    {
        return [
            '21' => 'FTP',
            '22' => 'SSH',
            '25' => 'SMTP',
            '53' => 'DNS',
            '80' => 'HTTP',
            '110' => 'POP3',
            '143' => 'IMAP',
            '443' => 'HTTPS',
            '993' => 'IMAPS',
            '995' => 'POP3S',
            '3306' => 'MySQL',
            '5432' => 'PostgreSQL',
            '6379' => 'Redis',
            '8080' => 'Alternative HTTP',
            '8443' => 'Alternative HTTPS',
        ];
    }

    public function validatePortRange(string $port): bool
    {
        // Handle single ports
        if (is_numeric($port)) {
            $portNum = (int) $port;

            return $portNum >= 1 && $portNum <= 65535;
        }

        // Handle port ranges (e.g., "8000:8100")
        if (str_contains($port, ':')) {
            [$start, $end] = explode(':', $port, 2);

            if (! is_numeric($start) || ! is_numeric($end)) {
                return false;
            }

            $startPort = (int) $start;
            $endPort = (int) $end;

            return $startPort >= 1 && $endPort <= 65535 && $startPort <= $endPort;
        }

        return false;
    }

    public function getRuleTypeOptions(): array
    {
        return [
            'allow' => [
                'label' => 'Allow',
                'description' => 'Allow traffic on this port',
                'color' => 'green',
            ],
            'deny' => [
                'label' => 'Deny',
                'description' => 'Block traffic on this port',
                'color' => 'red',
            ],
        ];
    }

    public function getFirewallStatus(): array
    {
        return [
            'enabled' => 'Firewall is active and protecting your server',
            'disabled' => 'Firewall is disabled - server is exposed',
            'not_installed' => 'Firewall is not installed on this server',
        ];
    }

    public function generateUfwCommand(array $ruleData): string
    {
        $command = 'ufw ';

        // Add rule type (allow/deny)
        $command .= $ruleData['rule_type'].' ';

        // Add from IP if specified
        if (! empty($ruleData['from_ip_address'])) {
            $command .= 'from '.$ruleData['from_ip_address'].' ';
        }

        // Add to port
        $command .= 'to any port '.$ruleData['port'];

        return $command;
    }
}
