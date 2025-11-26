<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AvailablePhpVersion>
 */
class AvailablePhpVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'version' => '8.4',
            'display_name' => 'PHP 8.4',
            'is_default' => true,
            'is_deprecated' => false,
            'eol_date' => '2028-12-31',
            'sort_order' => 6,
        ];
    }

    /**
     * Indicate that the PHP version is 7.4.
     */
    public function php74(): static
    {
        return $this->state(fn (array $attributes) => [
            'version' => '7.4',
            'display_name' => 'PHP 7.4',
            'is_default' => false,
            'is_deprecated' => true,
            'eol_date' => '2022-11-28',
            'sort_order' => 1,
        ]);
    }

    /**
     * Indicate that the PHP version is 8.0.
     */
    public function php80(): static
    {
        return $this->state(fn (array $attributes) => [
            'version' => '8.0',
            'display_name' => 'PHP 8.0',
            'is_default' => false,
            'is_deprecated' => true,
            'eol_date' => '2023-11-26',
            'sort_order' => 2,
        ]);
    }

    /**
     * Indicate that the PHP version is 8.1.
     */
    public function php81(): static
    {
        return $this->state(fn (array $attributes) => [
            'version' => '8.1',
            'display_name' => 'PHP 8.1',
            'is_default' => false,
            'is_deprecated' => false,
            'eol_date' => '2025-12-31',
            'sort_order' => 3,
        ]);
    }

    /**
     * Indicate that the PHP version is 8.2.
     */
    public function php82(): static
    {
        return $this->state(fn (array $attributes) => [
            'version' => '8.2',
            'display_name' => 'PHP 8.2',
            'is_default' => false,
            'is_deprecated' => false,
            'eol_date' => '2026-12-31',
            'sort_order' => 4,
        ]);
    }

    /**
     * Indicate that the PHP version is 8.3.
     */
    public function php83(): static
    {
        return $this->state(fn (array $attributes) => [
            'version' => '8.3',
            'display_name' => 'PHP 8.3',
            'is_default' => false,
            'is_deprecated' => false,
            'eol_date' => '2027-12-31',
            'sort_order' => 5,
        ]);
    }

    /**
     * Indicate that the PHP version is 8.4 (default).
     */
    public function php84(): static
    {
        return $this->state(fn (array $attributes) => [
            'version' => '8.4',
            'display_name' => 'PHP 8.4',
            'is_default' => true,
            'is_deprecated' => false,
            'eol_date' => '2028-12-31',
            'sort_order' => 6,
        ]);
    }

    /**
     * Indicate that the PHP version is 8.5.
     */
    public function php85(): static
    {
        return $this->state(fn (array $attributes) => [
            'version' => '8.5',
            'display_name' => 'PHP 8.5',
            'is_default' => false,
            'is_deprecated' => false,
            'eol_date' => null,
            'sort_order' => 7,
        ]);
    }

    /**
     * Indicate that the PHP version is deprecated.
     */
    public function deprecated(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_deprecated' => true,
            'eol_date' => '2022-01-01',
        ]);
    }
}
