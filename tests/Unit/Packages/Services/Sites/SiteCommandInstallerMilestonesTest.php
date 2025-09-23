<?php

namespace Tests\Unit\Packages\Services\Sites;

use App\Packages\Services\Sites\Command\SiteCommandInstallerMilestones;
use Tests\TestCase;

class SiteCommandInstallerMilestonesTest extends TestCase
{
    private SiteCommandInstallerMilestones $milestones;

    protected function setUp(): void
    {
        parent::setUp();
        $this->milestones = new SiteCommandInstallerMilestones();
    }

    public function test_extends_milestones_base_class(): void
    {
        $this->assertInstanceOf(\App\Packages\Base\Milestones::class, $this->milestones);
    }

    public function test_count_labels_returns_correct_count(): void
    {
        $this->assertEquals(2, $this->milestones->countLabels());
    }

    public function test_has_all_required_constants(): void
    {
        $this->assertTrue(defined(SiteCommandInstallerMilestones::class . '::PREPARE_EXECUTION'));
        $this->assertTrue(defined(SiteCommandInstallerMilestones::class . '::COMMAND_COMPLETE'));
    }

    public function test_all_constants_are_strings(): void
    {
        $this->assertIsString(SiteCommandInstallerMilestones::PREPARE_EXECUTION);
        $this->assertIsString(SiteCommandInstallerMilestones::COMMAND_COMPLETE);
    }

    public function test_constants_have_unique_values(): void
    {
        $values = [
            SiteCommandInstallerMilestones::PREPARE_EXECUTION,
            SiteCommandInstallerMilestones::COMMAND_COMPLETE,
        ];

        $this->assertCount(2, array_unique($values));
    }

    public function test_labels_method_returns_array(): void
    {
        $labels = SiteCommandInstallerMilestones::labels();

        $this->assertIsArray($labels);
        $this->assertCount(2, $labels);
        $this->assertArrayHasKey(SiteCommandInstallerMilestones::PREPARE_EXECUTION, $labels);
        $this->assertArrayHasKey(SiteCommandInstallerMilestones::COMMAND_COMPLETE, $labels);
    }

    public function test_label_method_returns_correct_label(): void
    {
        $this->assertEquals(
            'Preparing command execution',
            SiteCommandInstallerMilestones::label(SiteCommandInstallerMilestones::PREPARE_EXECUTION)
        );

        $this->assertEquals(
            'Command execution complete',
            SiteCommandInstallerMilestones::label(SiteCommandInstallerMilestones::COMMAND_COMPLETE)
        );
    }

    public function test_label_method_returns_null_for_unknown_milestone(): void
    {
        $this->assertNull(SiteCommandInstallerMilestones::label('unknown_milestone'));
    }

    public function test_count_matches_actual_constants(): void
    {
        $reflection = new \ReflectionClass(SiteCommandInstallerMilestones::class);
        $constants = $reflection->getConstants();

        // Filter out LABELS constant
        $milestoneConstants = array_filter($constants, function ($value, $key) {
            return $key !== 'LABELS' && is_string($value);
        }, ARRAY_FILTER_USE_BOTH);

        $this->assertCount($this->milestones->countLabels(), $milestoneConstants);
    }

    public function test_milestone_constants_have_descriptive_values(): void
    {
        $this->assertEquals('prepare_execution', SiteCommandInstallerMilestones::PREPARE_EXECUTION);
        $this->assertEquals('command_complete', SiteCommandInstallerMilestones::COMMAND_COMPLETE);
    }
}