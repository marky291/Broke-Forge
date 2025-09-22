<?php

namespace Tests\Unit\Packages\Services\WebServer;

use App\Packages\Services\WebServer\WebServiceInstallerMilestones;
use PHPUnit\Framework\TestCase;

class WebServiceInstallerMilestonesTest extends TestCase
{
    private WebServiceInstallerMilestones $milestones;

    protected function setUp(): void
    {
        parent::setUp();
        $this->milestones = new WebServiceInstallerMilestones;
    }

    public function test_extends_milestones_base_class(): void
    {
        $this->assertInstanceOf(\App\Packages\Base\Milestones::class, $this->milestones);
    }

    public function test_count_labels_returns_correct_number(): void
    {
        $this->assertEquals(11, $this->milestones->countLabels());
    }

    public function test_has_all_required_constants(): void
    {
        $this->assertEquals('prepare_system', WebServiceInstallerMilestones::PREPARE_SYSTEM);
        $this->assertEquals('setup_repository', WebServiceInstallerMilestones::SETUP_REPOSITORY);
        $this->assertEquals('remove_conflicts', WebServiceInstallerMilestones::REMOVE_CONFLICTS);
        $this->assertEquals('install_software', WebServiceInstallerMilestones::INSTALL_SOFTWARE);
        $this->assertEquals('enable_services', WebServiceInstallerMilestones::ENABLE_SERVICES);
        $this->assertEquals('configure_firewall', WebServiceInstallerMilestones::CONFIGURE_FIREWALL);
        $this->assertEquals('setup_default_site', WebServiceInstallerMilestones::SETUP_DEFAULT_SITE);
        $this->assertEquals('set_permissions', WebServiceInstallerMilestones::SET_PERMISSIONS);
        $this->assertEquals('configure_nginx', WebServiceInstallerMilestones::CONFIGURE_NGINX);
        $this->assertEquals('verify_install', WebServiceInstallerMilestones::VERIFY_INSTALL);
        $this->assertEquals('complete', WebServiceInstallerMilestones::COMPLETE);
    }

    public function test_all_constants_are_strings(): void
    {
        $this->assertIsString(WebServiceInstallerMilestones::PREPARE_SYSTEM);
        $this->assertIsString(WebServiceInstallerMilestones::SETUP_REPOSITORY);
        $this->assertIsString(WebServiceInstallerMilestones::REMOVE_CONFLICTS);
        $this->assertIsString(WebServiceInstallerMilestones::INSTALL_SOFTWARE);
        $this->assertIsString(WebServiceInstallerMilestones::ENABLE_SERVICES);
        $this->assertIsString(WebServiceInstallerMilestones::CONFIGURE_FIREWALL);
        $this->assertIsString(WebServiceInstallerMilestones::SETUP_DEFAULT_SITE);
        $this->assertIsString(WebServiceInstallerMilestones::SET_PERMISSIONS);
        $this->assertIsString(WebServiceInstallerMilestones::CONFIGURE_NGINX);
        $this->assertIsString(WebServiceInstallerMilestones::VERIFY_INSTALL);
        $this->assertIsString(WebServiceInstallerMilestones::COMPLETE);
    }

    public function test_constants_have_unique_values(): void
    {
        $constants = [
            WebServiceInstallerMilestones::PREPARE_SYSTEM,
            WebServiceInstallerMilestones::SETUP_REPOSITORY,
            WebServiceInstallerMilestones::REMOVE_CONFLICTS,
            WebServiceInstallerMilestones::INSTALL_SOFTWARE,
            WebServiceInstallerMilestones::ENABLE_SERVICES,
            WebServiceInstallerMilestones::CONFIGURE_FIREWALL,
            WebServiceInstallerMilestones::SETUP_DEFAULT_SITE,
            WebServiceInstallerMilestones::SET_PERMISSIONS,
            WebServiceInstallerMilestones::CONFIGURE_NGINX,
            WebServiceInstallerMilestones::VERIFY_INSTALL,
            WebServiceInstallerMilestones::COMPLETE,
        ];

        $this->assertEquals(count($constants), count(array_unique($constants)));
    }

    public function test_labels_method_returns_array(): void
    {
        $labels = WebServiceInstallerMilestones::labels();
        $this->assertIsArray($labels);
        $this->assertCount(11, $labels);
    }

    public function test_label_method_returns_correct_label(): void
    {
        $this->assertEquals('Preparing system', WebServiceInstallerMilestones::label('prepare_system'));
        $this->assertEquals('Setup complete', WebServiceInstallerMilestones::label('complete'));
        $this->assertNull(WebServiceInstallerMilestones::label('nonexistent'));
    }

    public function test_count_matches_actual_constants(): void
    {
        $reflection = new \ReflectionClass(WebServiceInstallerMilestones::class);
        $constants = $reflection->getConstants();

        // Filter out the LABELS constant since it's internal
        $milestoneConstants = array_filter($constants, fn ($key) => $key !== 'LABELS', ARRAY_FILTER_USE_KEY);

        // Should have 11 milestone constants
        $this->assertCount(11, $milestoneConstants);
        $this->assertEquals(11, $this->milestones->countLabels());
    }
}
