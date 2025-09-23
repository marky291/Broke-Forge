<?php

namespace Tests\Unit\Packages\Services\Nginx;

use App\Packages\Services\Nginx\NginxInstallerMilestones;
use PHPUnit\Framework\TestCase;

class NginxInstallerMilestonesTest extends TestCase
{
    private NginxInstallerMilestones $milestones;

    protected function setUp(): void
    {
        parent::setUp();
        $this->milestones = new NginxInstallerMilestones;
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
        $this->assertEquals('prepare_system', NginxInstallerMilestones::PREPARE_SYSTEM);
        $this->assertEquals('setup_repository', NginxInstallerMilestones::SETUP_REPOSITORY);
        $this->assertEquals('remove_conflicts', NginxInstallerMilestones::REMOVE_CONFLICTS);
        $this->assertEquals('install_software', NginxInstallerMilestones::INSTALL_SOFTWARE);
        $this->assertEquals('enable_services', NginxInstallerMilestones::ENABLE_SERVICES);
        $this->assertEquals('configure_firewall', NginxInstallerMilestones::CONFIGURE_FIREWALL);
        $this->assertEquals('setup_default_site', NginxInstallerMilestones::SETUP_DEFAULT_SITE);
        $this->assertEquals('set_permissions', NginxInstallerMilestones::SET_PERMISSIONS);
        $this->assertEquals('configure_nginx', NginxInstallerMilestones::CONFIGURE_NGINX);
        $this->assertEquals('verify_install', NginxInstallerMilestones::VERIFY_INSTALL);
        $this->assertEquals('complete', NginxInstallerMilestones::COMPLETE);
    }

    public function test_all_constants_are_strings(): void
    {
        $this->assertIsString(NginxInstallerMilestones::PREPARE_SYSTEM);
        $this->assertIsString(NginxInstallerMilestones::SETUP_REPOSITORY);
        $this->assertIsString(NginxInstallerMilestones::REMOVE_CONFLICTS);
        $this->assertIsString(NginxInstallerMilestones::INSTALL_SOFTWARE);
        $this->assertIsString(NginxInstallerMilestones::ENABLE_SERVICES);
        $this->assertIsString(NginxInstallerMilestones::CONFIGURE_FIREWALL);
        $this->assertIsString(NginxInstallerMilestones::SETUP_DEFAULT_SITE);
        $this->assertIsString(NginxInstallerMilestones::SET_PERMISSIONS);
        $this->assertIsString(NginxInstallerMilestones::CONFIGURE_NGINX);
        $this->assertIsString(NginxInstallerMilestones::VERIFY_INSTALL);
        $this->assertIsString(NginxInstallerMilestones::COMPLETE);
    }

    public function test_constants_have_unique_values(): void
    {
        $constants = [
            NginxInstallerMilestones::PREPARE_SYSTEM,
            NginxInstallerMilestones::SETUP_REPOSITORY,
            NginxInstallerMilestones::REMOVE_CONFLICTS,
            NginxInstallerMilestones::INSTALL_SOFTWARE,
            NginxInstallerMilestones::ENABLE_SERVICES,
            NginxInstallerMilestones::CONFIGURE_FIREWALL,
            NginxInstallerMilestones::SETUP_DEFAULT_SITE,
            NginxInstallerMilestones::SET_PERMISSIONS,
            NginxInstallerMilestones::CONFIGURE_NGINX,
            NginxInstallerMilestones::VERIFY_INSTALL,
            NginxInstallerMilestones::COMPLETE,
        ];

        $this->assertEquals(count($constants), count(array_unique($constants)));
    }

    public function test_labels_method_returns_array(): void
    {
        $labels = NginxInstallerMilestones::labels();
        $this->assertIsArray($labels);
        $this->assertCount(11, $labels);
    }

    public function test_label_method_returns_correct_label(): void
    {
        $this->assertEquals('Preparing system', NginxInstallerMilestones::label('prepare_system'));
        $this->assertEquals('Setup complete', NginxInstallerMilestones::label('complete'));
        $this->assertNull(NginxInstallerMilestones::label('nonexistent'));
    }

    public function test_count_matches_actual_constants(): void
    {
        $reflection = new \ReflectionClass(NginxInstallerMilestones::class);
        $constants = $reflection->getConstants();

        // Filter out the LABELS constant since it's internal
        $milestoneConstants = array_filter($constants, fn ($key) => $key !== 'LABELS', ARRAY_FILTER_USE_KEY);

        // Should have 11 milestone constants
        $this->assertCount(11, $milestoneConstants);
        $this->assertEquals(11, $this->milestones->countLabels());
    }
}
