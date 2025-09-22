<?php

namespace Tests\Unit\Packages\Base;

use App\Models\Server;
use App\Packages\Base\Milestones;
use App\Packages\Base\PackageInstaller;
use App\Packages\Contracts\Installer;
use App\Packages\Credentials\SshCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\MockObject\MockObject;
use Spatie\Ssh\Ssh;
use Tests\TestCase;

class PackageInstallerTest extends TestCase
{
    use RefreshDatabase;

    private Server $server;

    private TestablePackageInstaller $installer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = Server::factory()->create([
            'public_ip' => '192.168.1.1',
            'ssh_port' => 22,
        ]);

        $this->installer = new TestablePackageInstaller($this->server);
    }

    public function test_implements_installer_interface(): void
    {
        $this->assertInstanceOf(Installer::class, $this->installer);
    }

    public function test_constructor_sets_server(): void
    {
        $this->assertSame($this->server, $this->installer->getServer());
    }

    public function test_install_calls_send_commands_to_remote(): void
    {
        $commands = ['command1', 'command2', 'command3'];

        $this->installer->expectCommands($commands);
        $this->installer->testInstall($commands);

        $this->assertTrue($this->installer->wasSendCommandsCalled());
        $this->assertEquals($commands, $this->installer->getActualCommands());
    }

    public function test_install_with_empty_commands_array(): void
    {
        $commands = [];

        $this->installer->expectCommands($commands);
        $this->installer->testInstall($commands);

        $this->assertTrue($this->installer->wasSendCommandsCalled());
        $this->assertEquals($commands, $this->installer->getActualCommands());
    }

    public function test_install_with_mixed_commands_and_closures(): void
    {
        $closure = function () {};
        $commands = ['command1', $closure, 'command2'];

        $this->installer->expectCommands($commands);
        $this->installer->testInstall($commands);

        $this->assertTrue($this->installer->wasSendCommandsCalled());
        $this->assertEquals($commands, $this->installer->getActualCommands());
    }

    public function test_actionable_name_returns_installing(): void
    {
        $this->assertEquals('Installing', $this->installer->getActionableName());
    }
}

/**
 * Test implementation of PackageInstaller
 */
class TestablePackageInstaller extends PackageInstaller
{
    private bool $sendCommandsCalled = false;

    private array $expectedCommands = [];

    private array $actualCommands = [];

    private ?MockObject $sshMock = null;

    protected function serviceType(): string
    {
        return 'test-service';
    }

    protected function milestones(): Milestones
    {
        return new TestInstallerMilestones;
    }

    protected function sshCredential(): SshCredential
    {
        return new TestInstallerCredential;
    }

    public function execute(): void
    {
        // Implementation for testing
        $this->install(['test command']);
    }

    public function getServer(): Server
    {
        return $this->server;
    }

    public function testInstall(array $commands): void
    {
        $this->install($commands);
    }

    public function expectCommands(array $commands): void
    {
        $this->expectedCommands = $commands;
    }

    public function wasSendCommandsCalled(): bool
    {
        return $this->sendCommandsCalled;
    }

    public function getActionableName(): string
    {
        return $this->actionableName();
    }

    public function getActualCommands(): array
    {
        return $this->actualCommands;
    }

    protected function sendCommandsToRemote(array $commandList): void
    {
        $this->sendCommandsCalled = true;
        $this->actualCommands = $commandList;
    }

    public function setSshMock(MockObject $ssh): void
    {
        $this->sshMock = $ssh;
    }

    public function ssh(string $user, string $public_ip, int $port): Ssh
    {
        if ($this->sshMock !== null) {
            return $this->sshMock;
        }

        return $this->createMock(Ssh::class);
    }
}

/**
 * Test implementation of Milestones for installer
 */
class TestInstallerMilestones extends Milestones
{
    public function countLabels(): int
    {
        return 3;
    }
}

/**
 * Test implementation of SshCredential for installer
 */
class TestInstallerCredential implements SshCredential
{
    public function user(): string
    {
        return 'installer-user';
    }

    public function publicKey(): string
    {
        return 'installer-public-key';
    }

    public function privateKey(): string
    {
        return 'installer-private-key';
    }
}
