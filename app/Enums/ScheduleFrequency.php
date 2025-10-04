<?php

namespace App\Enums;

enum ScheduleFrequency: string
{
    case Minutely = 'minutely';
    case Hourly = 'hourly';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Custom = 'custom';

    /**
     * Get the cron expression for this frequency
     */
    public function cronExpression(): ?string
    {
        return match ($this) {
            self::Minutely => '* * * * *',
            self::Hourly => '0 * * * *',
            self::Daily => '0 0 * * *',
            self::Weekly => '0 0 * * 0',
            self::Monthly => '0 0 1 * *',
            self::Custom => null, // User provides custom expression
        };
    }

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::Minutely => 'Every Minute',
            self::Hourly => 'Every Hour',
            self::Daily => 'Daily at Midnight',
            self::Weekly => 'Weekly (Sunday at Midnight)',
            self::Monthly => 'Monthly (1st at Midnight)',
            self::Custom => 'Custom Cron Expression',
        };
    }
}
