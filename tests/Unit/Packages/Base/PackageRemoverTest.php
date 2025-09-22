<?php

namespace Tests\Unit\Packages\Base;

use App\Models\Server;
use App\Packages\Base\Milestones;
use App\Packages\Base\PackageRemover;
use App\Packages\Credentials\SshCredential;
use PHPUnit\Framework\MockObject\MockObject;
use Spatie\Ssh\Ssh;
use Tests\TestCase;

class PackageRemoverTest extends TestCase
{
    private MockObject $server;

    private TestableRemoverForTests $remover;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = $this->createMock(Server::class);
        $this->server->id = 1;
        $this->server->public_ip = '192.168.1.1';
        $this->server->ssh_port = 22;

        $this->remover = new TestableRemoverForTests($this->server);
    }

    public function test_constructor_sets_server(): void
    {
        $this->assertSame($this->server, $this->remover->getServer());
    }

    public function test_remove_calls_send_commands_to_remote(): void
    {
        $commands = ['rm -rf /test', 'apt remove package', 'systemctl stop service'];

        $this->remover->expectCommands($commands);
        $this->remover->testRemove($commands);

        $this->assertTrue($this->remover->wasSendCommandsCalled());
        $this->assertEquals($commands, $this->remover->getActualCommands());
    }

    public function test_remove_with_empty_commands_array(): void
    {
        $commands = [];

        $this->remover->expectCommands($commands);
        $this->remover->testRemove($commands);

        $this->assertTrue($this->remover->wasSendCommandsCalled());
        $this->assertEquals($commands, $this->remover->getActualCommands());
    }

    public function test_remove_with_mixed_commands_and_closures(): void
    {
        $closure = function () {};
        $commands = ['command1', $closure, 'command2'];

        $this->remover->expectCommands($commands);
        $this->remover->testRemove($commands);

        $this->assertTrue($this->remover->wasSendCommandsCalled());
        $this->assertEquals($commands, $this->remover->getActualCommands());
    }

    public function test_actionable_name_returns_removing(): void
    {
        $this->assertEquals('Removing', $this->remover->getActionableName());
    }

    public function test_remover_implements_correct_interface(): void
    {
        $this->assertInstanceOf(\App\Packages\Contracts\Remover::class, $this->remover);
    }
}

/**
 * Test implementation of PackageRemover
 */
class TestableRemoverForTests extends PackageRemover
{
    private bool $sendCommandsCalled = false;

    private array $expectedCommands = [];

    private array $actualCommands = [];

    private ?MockObject $sshMock = null;

    protected function serviceType(): string
    {
        return 'test-removal-service';
    }

    protected function milestones(): Milestones
    {
        return new TestRemoverMilestones;
    }

    protected function sshCredential(): SshCredential
    {
        return new TestRemoverCredential;
    }

    public function execute(): void
    {
        // Implementation for testing
        $this->remove(['test removal command']);
    }

    public function getServer(): Server
    {
        return $this->server;
    }

    public function testRemove(array $commands): void
    {
        $this->remove($commands);
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
 * Test implementation of Milestones for remover
 */
class TestRemoverMilestones extends Milestones
{
    public function countLabels(): int
    {
        return 4;
    }
}

/**
 * Test implementation of SshCredential for remover
 */
class TestRemoverCredential implements SshCredential
{
    public function user(): string
    {
        return 'remover-user';
    }

    public function publicKey(): string
    {
        return 'remover-public-key';
    }

    public function privateKey(): string
    {
        return 'remover-private-key';
    }
}
