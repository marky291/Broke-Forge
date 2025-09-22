<?php

namespace Tests\Unit\Packages\Services\Sites;

use App\Packages\Services\Sites\SiteInstallerMilestones;
use PHPUnit\Framework\TestCase;

class SiteInstallerMilestonesTest extends TestCase
{
    private SiteInstallerMilestones $milestones;

    protected function setUp(): void
    {
        parent::setUp();
        $this->milestones = new SiteInstallerMilestones;
    }

    public function test_extends_milestones_base_class(): void
    {
        $this->assertInstanceOf(\App\Packages\Base\Milestones::class, $this->milestones);
    }

    public function test_count_labels_returns_correct_number(): void
    {
        $this->assertEquals(7, $this->milestones->countLabels());
    }

    public function test_has_all_required_constants(): void
    {
        $this->assertEquals('prepare_directories', SiteInstallerMilestones::PREPARE_DIRECTORIES);
        $this->assertEquals('create_config', SiteInstallerMilestones::CREATE_CONFIG);
        $this->assertEquals('enable_site', SiteInstallerMilestones::ENABLE_SITE);
        $this->assertEquals('test_config', SiteInstallerMilestones::TEST_CONFIG);
        $this->assertEquals('reload_nginx', SiteInstallerMilestones::RELOAD_NGINX);
        $this->assertEquals('set_permissions', SiteInstallerMilestones::SET_PERMISSIONS);
        $this->assertEquals('complete', SiteInstallerMilestones::COMPLETE);
    }

    public function test_all_constants_are_strings(): void
    {
        $this->assertIsString(SiteInstallerMilestones::PREPARE_DIRECTORIES);
        $this->assertIsString(SiteInstallerMilestones::CREATE_CONFIG);
        $this->assertIsString(SiteInstallerMilestones::ENABLE_SITE);
        $this->assertIsString(SiteInstallerMilestones::TEST_CONFIG);
        $this->assertIsString(SiteInstallerMilestones::RELOAD_NGINX);
        $this->assertIsString(SiteInstallerMilestones::SET_PERMISSIONS);
        $this->assertIsString(SiteInstallerMilestones::COMPLETE);
    }

    public function test_constants_have_unique_values(): void
    {
        $constants = [
            SiteInstallerMilestones::PREPARE_DIRECTORIES,
            SiteInstallerMilestones::CREATE_CONFIG,
            SiteInstallerMilestones::ENABLE_SITE,
            SiteInstallerMilestones::TEST_CONFIG,
            SiteInstallerMilestones::RELOAD_NGINX,
            SiteInstallerMilestones::SET_PERMISSIONS,
            SiteInstallerMilestones::COMPLETE,
        ];

        $this->assertEquals(count($constants), count(array_unique($constants)));
    }

    public function test_count_matches_actual_constants(): void
    {
        $reflection = new \ReflectionClass(SiteInstallerMilestones::class);
        $constants = $reflection->getConstants();

        // Should have 7 milestone constants + 1 LABELS array = 8 total
        $this->assertCount(8, $constants);
        $this->assertEquals(7, $this->milestones->countLabels());
    }
}
