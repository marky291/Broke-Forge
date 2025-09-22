<?php

namespace Tests\Unit\Packages\Services\Database\MySQL;

use App\Packages\Base\Milestones;
use App\Packages\Services\Database\MySQL\MySqlRemoverMilestones;
use Tests\TestCase;

class MySqlRemoverMilestonesTest extends TestCase
{
    private MySqlRemoverMilestones $milestones;

    protected function setUp(): void
    {
        parent::setUp();
        $this->milestones = new MySqlRemoverMilestones;
    }

    public function test_extends_milestones_class(): void
    {
        $this->assertInstanceOf(Milestones::class, $this->milestones);
    }

    public function test_count_labels_returns_correct_count(): void
    {
        $this->assertEquals(7, $this->milestones->countLabels());
    }

    public function test_all_milestone_constants_exist(): void
    {
        $this->assertEquals('stop_service', MySqlRemoverMilestones::STOP_SERVICE);
        $this->assertEquals('backup_databases', MySqlRemoverMilestones::BACKUP_DATABASES);
        $this->assertEquals('remove_packages', MySqlRemoverMilestones::REMOVE_PACKAGES);
        $this->assertEquals('remove_data_directories', MySqlRemoverMilestones::REMOVE_DATA_DIRECTORIES);
        $this->assertEquals('remove_user_group', MySqlRemoverMilestones::REMOVE_USER_GROUP);
        $this->assertEquals('update_firewall', MySqlRemoverMilestones::UPDATE_FIREWALL);
        $this->assertEquals('uninstallation_complete', MySqlRemoverMilestones::UNINSTALLATION_COMPLETE);
    }

    public function test_milestone_constants_are_strings(): void
    {
        $this->assertIsString(MySqlRemoverMilestones::STOP_SERVICE);
        $this->assertIsString(MySqlRemoverMilestones::BACKUP_DATABASES);
        $this->assertIsString(MySqlRemoverMilestones::REMOVE_PACKAGES);
        $this->assertIsString(MySqlRemoverMilestones::REMOVE_DATA_DIRECTORIES);
        $this->assertIsString(MySqlRemoverMilestones::REMOVE_USER_GROUP);
        $this->assertIsString(MySqlRemoverMilestones::UPDATE_FIREWALL);
        $this->assertIsString(MySqlRemoverMilestones::UNINSTALLATION_COMPLETE);
    }

    public function test_milestone_constants_have_unique_values(): void
    {
        $constants = [
            MySqlRemoverMilestones::STOP_SERVICE,
            MySqlRemoverMilestones::BACKUP_DATABASES,
            MySqlRemoverMilestones::REMOVE_PACKAGES,
            MySqlRemoverMilestones::REMOVE_DATA_DIRECTORIES,
            MySqlRemoverMilestones::REMOVE_USER_GROUP,
            MySqlRemoverMilestones::UPDATE_FIREWALL,
            MySqlRemoverMilestones::UNINSTALLATION_COMPLETE,
        ];

        $this->assertEquals(count($constants), count(array_unique($constants)));
    }

    public function test_milestone_constants_follow_naming_convention(): void
    {
        $constants = [
            MySqlRemoverMilestones::STOP_SERVICE,
            MySqlRemoverMilestones::BACKUP_DATABASES,
            MySqlRemoverMilestones::REMOVE_PACKAGES,
            MySqlRemoverMilestones::REMOVE_DATA_DIRECTORIES,
            MySqlRemoverMilestones::REMOVE_USER_GROUP,
            MySqlRemoverMilestones::UPDATE_FIREWALL,
            MySqlRemoverMilestones::UNINSTALLATION_COMPLETE,
        ];

        foreach ($constants as $constant) {
            // Should be lowercase with underscores
            $this->assertEquals(strtolower($constant), $constant);
            $this->assertStringNotContainsString(' ', $constant);
            $this->assertStringNotContainsString('-', $constant);
        }
    }

    public function test_count_labels_matches_actual_constant_count(): void
    {
        $reflection = new \ReflectionClass(MySqlRemoverMilestones::class);
        $constants = $reflection->getConstants();

        // Filter out the LABELS constant as it's not a milestone
        $milestoneConstants = array_filter($constants, function ($key) {
            return $key !== 'LABELS';
        }, ARRAY_FILTER_USE_KEY);

        $this->assertEquals(count($milestoneConstants), $this->milestones->countLabels());
    }

    public function test_labels_constant_exists(): void
    {
        $reflection = new \ReflectionClass(MySqlRemoverMilestones::class);
        $this->assertTrue($reflection->hasConstant('LABELS'));
    }

    public function test_milestone_constants_reflect_removal_process(): void
    {
        // Test that the milestone constants represent a logical removal sequence
        $expectedOrder = [
            'STOP_SERVICE',
            'BACKUP_DATABASES',
            'REMOVE_PACKAGES',
            'REMOVE_DATA_DIRECTORIES',
            'REMOVE_USER_GROUP',
            'UPDATE_FIREWALL',
            'UNINSTALLATION_COMPLETE',
        ];

        $reflection = new \ReflectionClass(MySqlRemoverMilestones::class);
        $actualConstants = array_keys($reflection->getConstants());

        // Filter out LABELS constant
        $actualConstants = array_filter($actualConstants, fn ($key) => $key !== 'LABELS');

        $this->assertEquals($expectedOrder, array_values($actualConstants));
    }
}
