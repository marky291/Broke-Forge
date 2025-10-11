<?php

namespace App\Packages\Services\Sites\Command\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidCronExpression implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a valid cron expression.');

            return;
        }

        // Split the cron expression into parts
        $parts = preg_split('/\s+/', trim($value));

        // Cron expression should have exactly 5 parts (minute hour day month weekday)
        if (count($parts) !== 5) {
            $fail('The :attribute must have exactly 5 parts (minute hour day month weekday).');

            return;
        }

        // Validate each part
        $patterns = [
            '/^(\*|([0-9]|[1-5][0-9])(\/[0-9]+)?|([0-9]|[1-5][0-9])-([0-9]|[1-5][0-9])|([0-9]|[1-5][0-9])(,([0-9]|[1-5][0-9]))+)$/', // minute (0-59)
            '/^(\*|([0-9]|1[0-9]|2[0-3])(\/[0-9]+)?|([0-9]|1[0-9]|2[0-3])-([0-9]|1[0-9]|2[0-3])|([0-9]|1[0-9]|2[0-3])(,([0-9]|1[0-9]|2[0-3]))+)$/', // hour (0-23)
            '/^(\*|([1-9]|[12][0-9]|3[01])(\/[0-9]+)?|([1-9]|[12][0-9]|3[01])-([1-9]|[12][0-9]|3[01])|([1-9]|[12][0-9]|3[01])(,([1-9]|[12][0-9]|3[01]))+)$/', // day (1-31)
            '/^(\*|([1-9]|1[012])(\/[0-9]+)?|([1-9]|1[012])-([1-9]|1[012])|([1-9]|1[012])(,([1-9]|1[012]))+)$/', // month (1-12)
            '/^(\*|[0-6](\/[0-9]+)?|[0-6]-[0-6]|[0-6](,[0-6])+)$/', // weekday (0-6)
        ];

        foreach ($parts as $index => $part) {
            if (! preg_match($patterns[$index], $part)) {
                $labels = ['minute', 'hour', 'day', 'month', 'weekday'];
                $fail("The :attribute has an invalid {$labels[$index]} value: {$part}");

                return;
            }
        }
    }
}
