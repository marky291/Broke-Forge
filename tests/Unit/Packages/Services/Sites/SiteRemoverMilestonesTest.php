<?php

namespace Tests\Unit\Packages\Services\Sites;

use App\Packages\Services\Sites\SiteRemoverMilestones;
use PHPUnit\Framework\TestCase;

class SiteRemoverMilestonesTest extends TestCase
{
    private SiteRemoverMilestones $milestones;

    protected function setUp(): void
    {
        parent::setUp();
        $this->milestones = new SiteRemoverMilestones;
    }

    public function test_extends_milestones_base_class(): void
    {
        $this->assertInstanceOf(\App\Packages\Base\Milestones::class, $this->milestones);
    }

    public function test_count_labels_returns_correct_number(): void
    {
        $this->assertEquals(5, $this->milestones->countLabels());
    }

    public function test_has_all_required_constants(): void
    {
        $this->assertEquals('disable_site', SiteRemoverMilestones::DISABLE_SITE);
        $this->assertEquals('test_configuration', SiteRemoverMilestones::TEST_CONFIGURATION);
        $this->assertEquals('reload_nginx', SiteRemoverMilestones::RELOAD_NGINX);
        $this->assertEquals('archive_configuration', SiteRemoverMilestones::ARCHIVE_CONFIGURATION);
        $this->assertEquals('complete', SiteRemoverMilestones::COMPLETE);
    }

    public function test_all_constants_are_strings(): void
    {
        $this->assertIsString(SiteRemoverMilestones::DISABLE_SITE);
        $this->assertIsString(SiteRemoverMilestones::TEST_CONFIGURATION);
        $this->assertIsString(SiteRemoverMilestones::RELOAD_NGINX);
        $this->assertIsString(SiteRemoverMilestones::ARCHIVE_CONFIGURATION);
        $this->assertIsString(SiteRemoverMilestones::COMPLETE);
    }

    public function test_constants_have_unique_values(): void
    {
        $constants = [
            SiteRemoverMilestones::DISABLE_SITE,
            SiteRemoverMilestones::TEST_CONFIGURATION,
            SiteRemoverMilestones::RELOAD_NGINX,
            SiteRemoverMilestones::ARCHIVE_CONFIGURATION,
            SiteRemoverMilestones::COMPLETE,
        ];

        $this->assertEquals(count($constants), count(array_unique($constants)));
    }

    public function test_count_matches_actual_constants(): void
    {
        $reflection = new \ReflectionClass(SiteRemoverMilestones::class);
        $constants = $reflection->getConstants();

        // Should have 5 milestone constants + 1 LABELS array = 6 total
        $this->assertCount(6, $constants);
        $this->assertEquals(5, $this->milestones->countLabels());
    }
}
