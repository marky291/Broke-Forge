<?php

namespace App\Packages\Services\Sites\Command\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeCommand implements ValidationRule
{
    /**
     * Dangerous command patterns that should be blocked
     */
    private const DANGEROUS_PATTERNS = [
        // System modification
        '/\b(rm\s+-rf\s+\/(?!home\/brokeforge))/i', // rm -rf / (except brokeforge home)
        '/\b(mkfs|fdisk|parted|dd\s+if=.*of=\/dev)/i', // Filesystem operations
        '/\b(reboot|shutdown|poweroff|halt)\b/i', // System power operations

        // User/Permission manipulation (only allow within brokeforge scope)
        '/\b(userdel|usermod|groupdel|groupmod)\b(?!.*brokeforge)/i',
        '/\b(passwd|chpasswd)\b(?!.*brokeforge)/i',
        '/\b(chmod|chown|chgrp)\s+.*(?!\/home\/brokeforge)/i', // Only allow on brokeforge files

        // Network attacks
        '/\b(nmap|masscan|hping|tcpdump)\b/i',
        '/\b(nc|netcat)\s+.*-[el]/i', // Netcat listeners

        // Reverse shells
        '/\b(bash|sh|python|perl|ruby|php)\s+.*-[ic]\s+.*\/dev\/tcp/i',
        '/\/dev\/tcp\/[\d.]+\/\d+/i',

        // Credential theft
        '/\b(cat|grep|find|locate)\b.*\/(\.ssh|\.aws|\.kube|\.docker|\.npm|\.gem)/i',
        '/\b(cat|grep|find)\b.*\/(shadow|passwd|sudoers)/i',

        // Download and execute
        '/(curl|wget)\s+.*\|\s*(bash|sh|python|perl|ruby|php)/i',

        // Kernel/system manipulation
        '/\b(insmod|rmmod|modprobe)\b/i',
        '/\b(sysctl)\b/i',

        // Cron manipulation (prevent cron within cron)
        '/\b(crontab)\b/i',

        // Service manipulation (prevent bypassing BrokeForge)
        '/\b(systemctl|service)\s+(stop|disable|mask)\b.*\b(nginx|php|mysql|postgresql)/i',

        // Command injection attempts
        '/[;&|`$()]/i', // Command separators and substitution
        '/\$\{.*\}/i', // Variable substitution
        '/\$\(.*\)/i', // Command substitution
    ];

    /**
     * Maximum command length
     */
    private const MAX_COMMAND_LENGTH = 1000;

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a valid command string.');

            return;
        }

        // Check length
        if (strlen($value) > self::MAX_COMMAND_LENGTH) {
            $fail('The :attribute exceeds maximum length of '.self::MAX_COMMAND_LENGTH.' characters.');

            return;
        }

        // Check for dangerous patterns
        foreach (self::DANGEROUS_PATTERNS as $pattern) {
            if (preg_match($pattern, $value)) {
                $fail('The :attribute contains potentially dangerous commands or syntax. Please contact support if you need to run system-level operations.');

                return;
            }
        }

        // Check for null bytes (command injection technique)
        if (strpos($value, "\0") !== false) {
            $fail('The :attribute contains invalid characters.');

            return;
        }

        // Warn about sudo usage (should not be needed)
        if (preg_match('/\bsudo\b/i', $value)) {
            $fail('The :attribute should not use sudo. Commands run with appropriate privileges automatically.');

            return;
        }
    }
}
