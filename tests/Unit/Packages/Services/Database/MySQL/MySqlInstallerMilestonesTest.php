<?php

namespace Tests\Unit\Packages\Services\Database\MySQL;

use App\Packages\Base\Milestones;
use App\Packages\Services\Database\MySQL\MySqlInstallerMilestones;
use Tests\TestCase;

class MySqlInstallerMilestonesTest extends TestCase
{
    private MySqlInstallerMilestones $milestones;

    protected function setUp(): void
    {
        parent::setUp();
        $this->milestones = new MySqlInstallerMilestones;
    }

    public function test_extends_milestones_class(): void
    {
        $this->assertInstanceOf(Milestones::class, $this->milestones);
    }

    public function test_count_labels_returns_correct_count(): void
    {
        $this->assertEquals(11, $this->milestones->countLabels());
    }

    public function test_all_milestone_constants_exist(): void
    {
        $this->assertEquals('update_packages', MySqlInstallerMilestones::UPDATE_PACKAGES);
        $this->assertEquals('install_prerequisites', MySqlInstallerMilestones::INSTALL_PREREQUISITES);
        $this->assertEquals('configure_root_password', MySqlInstallerMilestones::CONFIGURE_ROOT_PASSWORD);
        $this->assertEquals('install_mysql', MySqlInstallerMilestones::INSTALL_MYSQL);
        $this->assertEquals('start_service', MySqlInstallerMilestones::START_SERVICE);
        $this->assertEquals('secure_installation', MySqlInstallerMilestones::SECURE_INSTALLATION);
        $this->assertEquals('create_backup_directory', MySqlInstallerMilestones::CREATE_BACKUP_DIRECTORY);
        $this->assertEquals('configure_remote_access', MySqlInstallerMilestones::CONFIGURE_REMOTE_ACCESS);
        $this->assertEquals('restart_service', MySqlInstallerMilestones::RESTART_SERVICE);
        $this->assertEquals('configure_firewall', MySqlInstallerMilestones::CONFIGURE_FIREWALL);
        $this->assertEquals('installation_complete', MySqlInstallerMilestones::INSTALLATION_COMPLETE);
    }

    public function test_milestone_constants_are_strings(): void
    {
        $this->assertIsString(MySqlInstallerMilestones::UPDATE_PACKAGES);
        $this->assertIsString(MySqlInstallerMilestones::INSTALL_PREREQUISITES);
        $this->assertIsString(MySqlInstallerMilestones::CONFIGURE_ROOT_PASSWORD);
        $this->assertIsString(MySqlInstallerMilestones::INSTALL_MYSQL);
        $this->assertIsString(MySqlInstallerMilestones::START_SERVICE);
        $this->assertIsString(MySqlInstallerMilestones::SECURE_INSTALLATION);
        $this->assertIsString(MySqlInstallerMilestones::CREATE_BACKUP_DIRECTORY);
        $this->assertIsString(MySqlInstallerMilestones::CONFIGURE_REMOTE_ACCESS);
        $this->assertIsString(MySqlInstallerMilestones::RESTART_SERVICE);
        $this->assertIsString(MySqlInstallerMilestones::CONFIGURE_FIREWALL);
        $this->assertIsString(MySqlInstallerMilestones::INSTALLATION_COMPLETE);
    }

    public function test_milestone_constants_have_unique_values(): void
    {
        $constants = [
            MySqlInstallerMilestones::UPDATE_PACKAGES,
            MySqlInstallerMilestones::INSTALL_PREREQUISITES,
            MySqlInstallerMilestones::CONFIGURE_ROOT_PASSWORD,
            MySqlInstallerMilestones::INSTALL_MYSQL,
            MySqlInstallerMilestones::START_SERVICE,
            MySqlInstallerMilestones::SECURE_INSTALLATION,
            MySqlInstallerMilestones::CREATE_BACKUP_DIRECTORY,
            MySqlInstallerMilestones::CONFIGURE_REMOTE_ACCESS,
            MySqlInstallerMilestones::RESTART_SERVICE,
            MySqlInstallerMilestones::CONFIGURE_FIREWALL,
            MySqlInstallerMilestones::INSTALLATION_COMPLETE,
        ];

        $this->assertEquals(count($constants), count(array_unique($constants)));
    }

    public function test_milestone_constants_follow_naming_convention(): void
    {
        $constants = [
            MySqlInstallerMilestones::UPDATE_PACKAGES,
            MySqlInstallerMilestones::INSTALL_PREREQUISITES,
            MySqlInstallerMilestones::CONFIGURE_ROOT_PASSWORD,
            MySqlInstallerMilestones::INSTALL_MYSQL,
            MySqlInstallerMilestones::START_SERVICE,
            MySqlInstallerMilestones::SECURE_INSTALLATION,
            MySqlInstallerMilestones::CREATE_BACKUP_DIRECTORY,
            MySqlInstallerMilestones::CONFIGURE_REMOTE_ACCESS,
            MySqlInstallerMilestones::RESTART_SERVICE,
            MySqlInstallerMilestones::CONFIGURE_FIREWALL,
            MySqlInstallerMilestones::INSTALLATION_COMPLETE,
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
        $reflection = new \ReflectionClass(MySqlInstallerMilestones::class);
        $constants = $reflection->getConstants();

        // Filter out the LABELS constant as it's not a milestone
        $milestoneConstants = array_filter($constants, function ($key) {
            return $key !== 'LABELS';
        }, ARRAY_FILTER_USE_KEY);

        $this->assertEquals(count($milestoneConstants), $this->milestones->countLabels());
    }

    public function test_labels_constant_exists(): void
    {
        $reflection = new \ReflectionClass(MySqlInstallerMilestones::class);
        $this->assertTrue($reflection->hasConstant('LABELS'));
    }
}
