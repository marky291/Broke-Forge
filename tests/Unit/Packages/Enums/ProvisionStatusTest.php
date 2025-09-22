<?php

namespace Tests\Unit\Packages\Enums;

use App\Packages\Enums\ProvisionStatus;
use Tests\TestCase;

class ProvisionStatusTest extends TestCase
{
    public function test_provision_status_enum_values(): void
    {
        $this->assertEquals('pending', ProvisionStatus::Pending->value);
        $this->assertEquals('connecting', ProvisionStatus::Connecting->value);
        $this->assertEquals('installing', ProvisionStatus::Installing->value);
        $this->assertEquals('completed', ProvisionStatus::Completed->value);
        $this->assertEquals('failed', ProvisionStatus::Failed->value);
    }

    public function test_provision_status_labels(): void
    {
        $this->assertEquals('Pending', ProvisionStatus::Pending->label());
        $this->assertEquals('Setting up access', ProvisionStatus::Connecting->label());
        $this->assertEquals('Installing services', ProvisionStatus::Installing->label());
        $this->assertEquals('Provisioned', ProvisionStatus::Completed->label());
        $this->assertEquals('Failed', ProvisionStatus::Failed->label());
    }

    public function test_provision_status_colors(): void
    {
        $this->assertEquals('gray', ProvisionStatus::Pending->color());
        $this->assertEquals('amber', ProvisionStatus::Connecting->color());
        $this->assertEquals('blue', ProvisionStatus::Installing->color());
        $this->assertEquals('green', ProvisionStatus::Completed->color());
        $this->assertEquals('red', ProvisionStatus::Failed->color());
    }

    public function test_provision_status_from_string(): void
    {
        $this->assertEquals(ProvisionStatus::Pending, ProvisionStatus::from('pending'));
        $this->assertEquals(ProvisionStatus::Connecting, ProvisionStatus::from('connecting'));
        $this->assertEquals(ProvisionStatus::Installing, ProvisionStatus::from('installing'));
        $this->assertEquals(ProvisionStatus::Completed, ProvisionStatus::from('completed'));
        $this->assertEquals(ProvisionStatus::Failed, ProvisionStatus::from('failed'));
    }

    public function test_provision_status_try_from_string(): void
    {
        $this->assertEquals(ProvisionStatus::Pending, ProvisionStatus::tryFrom('pending'));
        $this->assertNull(ProvisionStatus::tryFrom('invalid_status'));
    }

    public function test_provision_status_cases(): void
    {
        $cases = ProvisionStatus::cases();

        $this->assertCount(5, $cases);
        $this->assertContains(ProvisionStatus::Pending, $cases);
        $this->assertContains(ProvisionStatus::Connecting, $cases);
        $this->assertContains(ProvisionStatus::Installing, $cases);
        $this->assertContains(ProvisionStatus::Completed, $cases);
        $this->assertContains(ProvisionStatus::Failed, $cases);
    }

    public function test_provision_status_is_backed_enum(): void
    {
        $reflection = new \ReflectionEnum(ProvisionStatus::class);
        $this->assertTrue($reflection->isBacked());
        $this->assertEquals('string', $reflection->getBackingType()->getName());
    }

    public function test_each_status_has_unique_value(): void
    {
        $values = array_map(fn ($case) => $case->value, ProvisionStatus::cases());
        $this->assertEquals(count($values), count(array_unique($values)));
    }

    public function test_each_status_has_unique_color(): void
    {
        $colors = array_map(fn ($case) => $case->color(), ProvisionStatus::cases());
        $this->assertEquals(count($colors), count(array_unique($colors)));
    }
}
