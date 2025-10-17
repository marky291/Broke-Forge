<?php

namespace Tests\Unit\Packages\Services\Sites\Command\Rules;

use App\Packages\Services\Sites\Command\Rules\ValidCronExpression;
use Tests\TestCase;

class ValidCronExpressionTest extends TestCase
{
    /**
     * Test valid cron expressions pass validation.
     */
    public function test_valid_cron_expressions_pass_validation(): void
    {
        // Arrange
        $rule = new ValidCronExpression;
        $validExpressions = [
            '* * * * *',        // Every minute
            '0 0 * * *',        // Daily at midnight
            '0 0 * * 0',        // Weekly on Sunday
            '0 0 1 * *',        // Monthly on 1st
            '0 0 1 1 *',        // Yearly on Jan 1st
            '0-59 * * * *',     // Range (minute)
            '0 0-23 * * *',     // Range (hour)
            '0 0 1-31 * *',     // Range (day)
            '0 0 * 1-12 *',     // Range (month)
            '0 0 * * 0-6',      // Range (weekday)
            '0,30 * * * *',     // List (minutes 0 and 30)
            '0 9,17 * * *',     // List (hours 9 and 17)
            '0 0 1,15 * *',     // List (days 1 and 15)
            '0 0 * 1,7 *',      // List (months 1 and 7)
            '0 0 * * 1,5',      // List (weekdays Mon and Fri)
            '15 10 * * *',      // Specific time (10:15 AM)
            '30 2 1 * *',       // First day of month at 2:30 AM
            '0 22 * * 1-5',     // Weekdays at 10 PM
            '10/10 * * * *',    // Starting at 10, every 10
        ];

        foreach ($validExpressions as $expression) {
            $failCalled = false;
            $fail = function () use (&$failCalled) {
                $failCalled = true;
            };

            // Act
            $rule->validate('schedule', $expression, $fail);

            // Assert
            $this->assertFalse($failCalled, "Expression '{$expression}' should be valid but was rejected");
        }
    }

    /**
     * Test expressions with wrong number of parts are rejected.
     */
    public function test_expressions_with_wrong_number_of_parts_are_rejected(): void
    {
        // Arrange
        $rule = new ValidCronExpression;
        $invalidExpressions = [
            '',                     // Empty
            '*',                    // 1 part
            '* *',                  // 2 parts
            '* * *',                // 3 parts
            '* * * *',              // 4 parts
            '* * * * * *',          // 6 parts
            '* * * * * * *',        // 7 parts
        ];

        foreach ($invalidExpressions as $expression) {
            $failCalled = false;
            $failMessage = '';
            $fail = function ($message) use (&$failCalled, &$failMessage) {
                $failCalled = true;
                $failMessage = $message;
            };

            // Act
            $rule->validate('schedule', $expression, $fail);

            // Assert
            $this->assertTrue($failCalled, "Expression '{$expression}' should be rejected for wrong part count");
            $this->assertStringContainsString('exactly 5 parts', $failMessage);
        }
    }

    /**
     * Test invalid minute values are rejected.
     */
    public function test_invalid_minute_values_are_rejected(): void
    {
        // Arrange
        $rule = new ValidCronExpression;
        $invalidExpressions = [
            '60 * * * *',       // > 59
            '99 * * * *',       // Way out of range
            '-1 * * * *',       // Negative
            'a * * * *',        // Letter
            '1.5 * * * *',      // Decimal
            '1-60 * * * *',     // Range exceeds limit
            '60/5 * * * *',     // Step starts out of range
        ];

        foreach ($invalidExpressions as $expression) {
            $failCalled = false;
            $failMessage = '';
            $fail = function ($message) use (&$failCalled, &$failMessage) {
                $failCalled = true;
                $failMessage = $message;
            };

            // Act
            $rule->validate('schedule', $expression, $fail);

            // Assert
            $this->assertTrue($failCalled, "Expression '{$expression}' should be rejected for invalid minute");
            $this->assertStringContainsString('minute', $failMessage);
        }
    }

    /**
     * Test invalid hour values are rejected.
     */
    public function test_invalid_hour_values_are_rejected(): void
    {
        // Arrange
        $rule = new ValidCronExpression;
        $invalidExpressions = [
            '0 24 * * *',       // > 23
            '0 99 * * *',       // Way out of range
            '0 -1 * * *',       // Negative
            '0 a * * *',        // Letter
            '0 12.5 * * *',     // Decimal
            '0 0-24 * * *',     // Range exceeds limit
            '0 24/2 * * *',     // Step starts out of range
        ];

        foreach ($invalidExpressions as $expression) {
            $failCalled = false;
            $failMessage = '';
            $fail = function ($message) use (&$failCalled, &$failMessage) {
                $failCalled = true;
                $failMessage = $message;
            };

            // Act
            $rule->validate('schedule', $expression, $fail);

            // Assert
            $this->assertTrue($failCalled, "Expression '{$expression}' should be rejected for invalid hour");
            $this->assertStringContainsString('hour', $failMessage);
        }
    }

    /**
     * Test invalid day values are rejected.
     */
    public function test_invalid_day_values_are_rejected(): void
    {
        // Arrange
        $rule = new ValidCronExpression;
        $invalidExpressions = [
            '0 0 0 * *',        // < 1
            '0 0 32 * *',       // > 31
            '0 0 99 * *',       // Way out of range
            '0 0 -1 * *',       // Negative
            '0 0 a * *',        // Letter
            '0 0 15.5 * *',     // Decimal
            '0 0 0-10 * *',     // Range starts at 0
            '0 0 1-32 * *',     // Range exceeds limit
        ];

        foreach ($invalidExpressions as $expression) {
            $failCalled = false;
            $failMessage = '';
            $fail = function ($message) use (&$failCalled, &$failMessage) {
                $failCalled = true;
                $failMessage = $message;
            };

            // Act
            $rule->validate('schedule', $expression, $fail);

            // Assert
            $this->assertTrue($failCalled, "Expression '{$expression}' should be rejected for invalid day");
            $this->assertStringContainsString('day', $failMessage);
        }
    }

    /**
     * Test invalid month values are rejected.
     */
    public function test_invalid_month_values_are_rejected(): void
    {
        // Arrange
        $rule = new ValidCronExpression;
        $invalidExpressions = [
            '0 0 * 0 *',        // < 1
            '0 0 * 13 *',       // > 12
            '0 0 * 99 *',       // Way out of range
            '0 0 * -1 *',       // Negative
            '0 0 * a *',        // Letter
            '0 0 * 6.5 *',      // Decimal
            '0 0 * 0-6 *',      // Range starts at 0
            '0 0 * 1-13 *',     // Range exceeds limit
        ];

        foreach ($invalidExpressions as $expression) {
            $failCalled = false;
            $failMessage = '';
            $fail = function ($message) use (&$failCalled, &$failMessage) {
                $failCalled = true;
                $failMessage = $message;
            };

            // Act
            $rule->validate('schedule', $expression, $fail);

            // Assert
            $this->assertTrue($failCalled, "Expression '{$expression}' should be rejected for invalid month");
            $this->assertStringContainsString('month', $failMessage);
        }
    }

    /**
     * Test invalid weekday values are rejected.
     */
    public function test_invalid_weekday_values_are_rejected(): void
    {
        // Arrange
        $rule = new ValidCronExpression;
        $invalidExpressions = [
            '0 0 * * 7',        // > 6
            '0 0 * * 99',       // Way out of range
            '0 0 * * -1',       // Negative
            '0 0 * * a',        // Letter
            '0 0 * * 3.5',      // Decimal
            '0 0 * * 0-7',      // Range exceeds limit
            '0 0 * * 7/1',      // Step starts out of range
        ];

        foreach ($invalidExpressions as $expression) {
            $failCalled = false;
            $failMessage = '';
            $fail = function ($message) use (&$failCalled, &$failMessage) {
                $failCalled = true;
                $failMessage = $message;
            };

            // Act
            $rule->validate('schedule', $expression, $fail);

            // Assert
            $this->assertTrue($failCalled, "Expression '{$expression}' should be rejected for invalid weekday");
            $this->assertStringContainsString('weekday', $failMessage);
        }
    }

    /**
     * Test non-string values are rejected.
     */
    public function test_non_string_values_are_rejected(): void
    {
        // Arrange
        $rule = new ValidCronExpression;
        $invalidValues = [
            123,
            ['* * * * *'],
            null,
            true,
            5.5,
        ];

        foreach ($invalidValues as $value) {
            $failCalled = false;
            $failMessage = '';
            $fail = function ($message) use (&$failCalled, &$failMessage) {
                $failCalled = true;
                $failMessage = $message;
            };

            // Act
            $rule->validate('schedule', $value, $fail);

            // Assert
            $this->assertTrue($failCalled);
            $this->assertStringContainsString('must be a valid cron expression', $failMessage);
        }
    }

    /**
     * Test wildcard works in all positions.
     */
    public function test_wildcard_works_in_all_positions(): void
    {
        // Arrange
        $rule = new ValidCronExpression;
        $validExpressions = [
            '* 0 1 1 0',        // Wildcard minute
            '0 * 1 1 0',        // Wildcard hour
            '0 0 * 1 0',        // Wildcard day
            '0 0 1 * 0',        // Wildcard month
            '0 0 1 1 *',        // Wildcard weekday
            '* * * * *',        // All wildcards
        ];

        foreach ($validExpressions as $expression) {
            $failCalled = false;
            $fail = function () use (&$failCalled) {
                $failCalled = true;
            };

            // Act
            $rule->validate('schedule', $expression, $fail);

            // Assert
            $this->assertFalse($failCalled, "Expression '{$expression}' with wildcards should be valid");
        }
    }

    /**
     * Test step values work correctly.
     */
    public function test_step_values_work_correctly(): void
    {
        // Arrange
        $rule = new ValidCronExpression;
        $validExpressions = [
            '10/10 * * * *',    // Starting at 10, every 10
            '0/15 * * * *',     // Starting at 0, every 15
            '5/5 0 * * *',      // Starting at 5, every 5 minutes
        ];

        foreach ($validExpressions as $expression) {
            $failCalled = false;
            $fail = function () use (&$failCalled) {
                $failCalled = true;
            };

            // Act
            $rule->validate('schedule', $expression, $fail);

            // Assert
            $this->assertFalse($failCalled, "Expression '{$expression}' with steps should be valid");
        }
    }

    /**
     * Test range values work correctly.
     */
    public function test_range_values_work_correctly(): void
    {
        // Arrange
        $rule = new ValidCronExpression;
        $validExpressions = [
            '0-30 * * * *',     // Minute range
            '0 9-17 * * *',     // Hour range
            '0 0 1-15 * *',     // Day range
            '0 0 * 1-6 *',      // Month range
            '0 0 * * 1-5',      // Weekday range
        ];

        foreach ($validExpressions as $expression) {
            $failCalled = false;
            $fail = function () use (&$failCalled) {
                $failCalled = true;
            };

            // Act
            $rule->validate('schedule', $expression, $fail);

            // Assert
            $this->assertFalse($failCalled, "Expression '{$expression}' with ranges should be valid");
        }
    }

    /**
     * Test list values work correctly.
     */
    public function test_list_values_work_correctly(): void
    {
        // Arrange
        $rule = new ValidCronExpression;
        $validExpressions = [
            '0,15,30,45 * * * *',   // Minute list
            '0 6,12,18 * * *',      // Hour list
            '0 0 1,15,30 * *',      // Day list
            '0 0 * 1,4,7,10 *',     // Month list
            '0 0 * * 1,3,5',        // Weekday list
        ];

        foreach ($validExpressions as $expression) {
            $failCalled = false;
            $fail = function () use (&$failCalled) {
                $failCalled = true;
            };

            // Act
            $rule->validate('schedule', $expression, $fail);

            // Assert
            $this->assertFalse($failCalled, "Expression '{$expression}' with lists should be valid");
        }
    }

    /**
     * Test expressions with extra whitespace.
     */
    public function test_expressions_with_extra_whitespace(): void
    {
        // Arrange
        $rule = new ValidCronExpression;
        $validExpressions = [
            '  * * * * *  ',        // Leading/trailing spaces
            '*  *  *  *  *',        // Multiple spaces between
            '	* * * * *',            // Tab before
        ];

        foreach ($validExpressions as $expression) {
            $failCalled = false;
            $fail = function () use (&$failCalled) {
                $failCalled = true;
            };

            // Act
            $rule->validate('schedule', $expression, $fail);

            // Assert
            $this->assertFalse($failCalled, 'Expression with whitespace should be valid after trimming');
        }
    }

    /**
     * Test boundary values for each field.
     */
    public function test_boundary_values_for_each_field(): void
    {
        // Arrange
        $rule = new ValidCronExpression;
        $validExpressions = [
            '0 0 1 1 0',        // All minimum values
            '59 23 31 12 6',    // All maximum values
        ];

        foreach ($validExpressions as $expression) {
            $failCalled = false;
            $fail = function () use (&$failCalled) {
                $failCalled = true;
            };

            // Act
            $rule->validate('schedule', $expression, $fail);

            // Assert
            $this->assertFalse($failCalled, "Expression '{$expression}' at boundaries should be valid");
        }
    }

    /**
     * Test complex combined patterns.
     */
    public function test_complex_combined_patterns(): void
    {
        // Arrange
        $rule = new ValidCronExpression;
        $validExpressions = [
            '0,30 8,12,18 1-15 * 1-5',    // Complex list/range combo
            '15 2/2 1 1 1-5',             // Steps and ranges
        ];

        foreach ($validExpressions as $expression) {
            $failCalled = false;
            $fail = function () use (&$failCalled) {
                $failCalled = true;
            };

            // Act
            $rule->validate('schedule', $expression, $fail);

            // Assert
            $this->assertFalse($failCalled, "Complex expression '{$expression}' should be valid");
        }
    }
}
