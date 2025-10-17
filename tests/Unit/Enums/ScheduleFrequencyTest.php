<?php

namespace Tests\Unit\Enums;

use App\Enums\ScheduleFrequency;
use Tests\TestCase;

class ScheduleFrequencyTest extends TestCase
{
    /**
     * Test cronExpression returns correct expression for Minutely frequency.
     */
    public function test_cron_expression_returns_correct_expression_for_minutely_frequency(): void
    {
        // Arrange
        $frequency = ScheduleFrequency::Minutely;

        // Act
        $expression = $frequency->cronExpression();

        // Assert
        $this->assertEquals('* * * * *', $expression);
    }

    /**
     * Test cronExpression returns correct expression for Hourly frequency.
     */
    public function test_cron_expression_returns_correct_expression_for_hourly_frequency(): void
    {
        // Arrange
        $frequency = ScheduleFrequency::Hourly;

        // Act
        $expression = $frequency->cronExpression();

        // Assert
        $this->assertEquals('0 * * * *', $expression);
    }

    /**
     * Test cronExpression returns correct expression for Daily frequency.
     */
    public function test_cron_expression_returns_correct_expression_for_daily_frequency(): void
    {
        // Arrange
        $frequency = ScheduleFrequency::Daily;

        // Act
        $expression = $frequency->cronExpression();

        // Assert
        $this->assertEquals('0 0 * * *', $expression);
    }

    /**
     * Test cronExpression returns correct expression for Weekly frequency.
     */
    public function test_cron_expression_returns_correct_expression_for_weekly_frequency(): void
    {
        // Arrange
        $frequency = ScheduleFrequency::Weekly;

        // Act
        $expression = $frequency->cronExpression();

        // Assert
        $this->assertEquals('0 0 * * 0', $expression);
    }

    /**
     * Test cronExpression returns correct expression for Monthly frequency.
     */
    public function test_cron_expression_returns_correct_expression_for_monthly_frequency(): void
    {
        // Arrange
        $frequency = ScheduleFrequency::Monthly;

        // Act
        $expression = $frequency->cronExpression();

        // Assert
        $this->assertEquals('0 0 1 * *', $expression);
    }

    /**
     * Test cronExpression returns null for Custom frequency.
     */
    public function test_cron_expression_returns_null_for_custom_frequency(): void
    {
        // Arrange
        $frequency = ScheduleFrequency::Custom;

        // Act
        $expression = $frequency->cronExpression();

        // Assert
        $this->assertNull($expression);
    }

    /**
     * Test label returns correct label for Minutely frequency.
     */
    public function test_label_returns_correct_label_for_minutely_frequency(): void
    {
        // Arrange
        $frequency = ScheduleFrequency::Minutely;

        // Act
        $label = $frequency->label();

        // Assert
        $this->assertEquals('Every Minute', $label);
    }

    /**
     * Test label returns correct label for Hourly frequency.
     */
    public function test_label_returns_correct_label_for_hourly_frequency(): void
    {
        // Arrange
        $frequency = ScheduleFrequency::Hourly;

        // Act
        $label = $frequency->label();

        // Assert
        $this->assertEquals('Every Hour', $label);
    }

    /**
     * Test label returns correct label for Daily frequency.
     */
    public function test_label_returns_correct_label_for_daily_frequency(): void
    {
        // Arrange
        $frequency = ScheduleFrequency::Daily;

        // Act
        $label = $frequency->label();

        // Assert
        $this->assertEquals('Daily at Midnight', $label);
    }

    /**
     * Test label returns correct label for Weekly frequency.
     */
    public function test_label_returns_correct_label_for_weekly_frequency(): void
    {
        // Arrange
        $frequency = ScheduleFrequency::Weekly;

        // Act
        $label = $frequency->label();

        // Assert
        $this->assertEquals('Weekly (Sunday at Midnight)', $label);
    }

    /**
     * Test label returns correct label for Monthly frequency.
     */
    public function test_label_returns_correct_label_for_monthly_frequency(): void
    {
        // Arrange
        $frequency = ScheduleFrequency::Monthly;

        // Act
        $label = $frequency->label();

        // Assert
        $this->assertEquals('Monthly (1st at Midnight)', $label);
    }

    /**
     * Test label returns correct label for Custom frequency.
     */
    public function test_label_returns_correct_label_for_custom_frequency(): void
    {
        // Arrange
        $frequency = ScheduleFrequency::Custom;

        // Act
        $label = $frequency->label();

        // Assert
        $this->assertEquals('Custom Cron Expression', $label);
    }

    /**
     * Test all frequencies have valid cron expressions or null.
     */
    public function test_all_frequencies_have_valid_cron_expressions_or_null(): void
    {
        // Arrange & Act & Assert
        foreach (ScheduleFrequency::cases() as $frequency) {
            $expression = $frequency->cronExpression();

            // Custom should be null, all others should be strings
            if ($frequency === ScheduleFrequency::Custom) {
                $this->assertNull($expression, 'Custom frequency should return null');
            } else {
                $this->assertIsString($expression, "{$frequency->value} should return a string cron expression");
                $this->assertNotEmpty($expression, "{$frequency->value} should not return an empty string");
            }
        }
    }

    /**
     * Test all frequencies have labels.
     */
    public function test_all_frequencies_have_labels(): void
    {
        // Arrange & Act & Assert
        foreach (ScheduleFrequency::cases() as $frequency) {
            $label = $frequency->label();

            $this->assertIsString($label, "{$frequency->value} should return a string label");
            $this->assertNotEmpty($label, "{$frequency->value} should not return an empty label");
        }
    }

    /**
     * Test cron expressions are valid cron syntax.
     */
    public function test_cron_expressions_are_valid_cron_syntax(): void
    {
        // Arrange
        $frequencies = [
            ScheduleFrequency::Minutely,
            ScheduleFrequency::Hourly,
            ScheduleFrequency::Daily,
            ScheduleFrequency::Weekly,
            ScheduleFrequency::Monthly,
        ];

        // Act & Assert
        foreach ($frequencies as $frequency) {
            $expression = $frequency->cronExpression();

            // Cron expressions should have 5 parts: minute hour day month weekday
            $parts = explode(' ', $expression);
            $this->assertCount(5, $parts, "{$frequency->value} should have 5 parts in cron expression");
        }
    }

    /**
     * Test weekly frequency runs on Sunday.
     */
    public function test_weekly_frequency_runs_on_sunday(): void
    {
        // Arrange
        $frequency = ScheduleFrequency::Weekly;

        // Act
        $expression = $frequency->cronExpression();

        // Assert - 0 = Sunday in cron
        $this->assertStringEndsWith('0', $expression);
    }

    /**
     * Test monthly frequency runs on first day of month.
     */
    public function test_monthly_frequency_runs_on_first_day_of_month(): void
    {
        // Arrange
        $frequency = ScheduleFrequency::Monthly;

        // Act
        $expression = $frequency->cronExpression();

        // Assert - Should contain "1" for first day of month
        $this->assertEquals('0 0 1 * *', $expression);
    }

    /**
     * Test enum has exactly six cases.
     */
    public function test_enum_has_exactly_six_cases(): void
    {
        // Act
        $cases = ScheduleFrequency::cases();

        // Assert
        $this->assertCount(6, $cases);
    }
}
