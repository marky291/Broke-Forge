<?php

namespace App\Packages\Services\Sites\Command\Rules;

use App\Models\Server;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that PHP commands in a task use an installed PHP version.
 *
 * Checks for patterns like "php8.4", "php8.3", etc. and verifies
 * the version is installed and active on the server.
 */
class ValidPhpCommand implements ValidationRule
{
    public function __construct(
        protected ?Server $server
    ) {}

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        // Skip validation if no server context (e.g., unit tests without route binding)
        if ($this->server === null) {
            return;
        }

        // Get active PHP versions for this server
        $activeVersions = $this->server->phps()
            ->where('status', 'active')
            ->pluck('version')
            ->toArray();

        // Check if command uses bare "php" without version (e.g., "php artisan" but not "php8.4 artisan")
        // Match: starts with "php " or contains " php " or "/php " but NOT "php8.x"
        if (preg_match('/(?:^|\/|\s)php(?!\d)(\s|$)/', $value)) {
            if (empty($activeVersions)) {
                $fail('Please specify a PHP version (e.g., php8.3). No PHP versions are currently active on this server.');
            } else {
                $availableVersions = implode(', ', array_map(fn ($v) => "php{$v}", $activeVersions));
                $fail("Please specify a PHP version instead of 'php'. Available: {$availableVersions}");
            }

            return;
        }

        // Match PHP version patterns like: php8.4, php8.3, /usr/bin/php8.4
        if (preg_match('/\bphp(\d+\.\d+)\b/', $value, $matches)) {
            $requestedVersion = $matches[1];

            if (! in_array($requestedVersion, $activeVersions)) {
                if (empty($activeVersions)) {
                    $fail("PHP {$requestedVersion} is not installed on this server. No PHP versions are currently active.");
                } else {
                    $availableVersions = implode(', ', array_map(fn ($v) => "php{$v}", $activeVersions));
                    $fail("PHP {$requestedVersion} is not installed on this server. Available: {$availableVersions}");
                }
            }
        }
    }
}
