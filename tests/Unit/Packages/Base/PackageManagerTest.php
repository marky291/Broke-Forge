<?php

namespace Tests\Unit\Packages\Base;

use App\Models\Server;
use App\Packages\Base\Milestones;
use App\Packages\Base\PackageManager;
use App\Packages\Base\PackageRemover;
use App\Packages\Credentials\SshCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\MockObject\MockObject;
use Spatie\Ssh\Ssh;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PackageManagerTest extends TestCase
{
    use RefreshDatabase;

    private Server $server;

    private TestablePackageManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = Server::factory()->create([
            'public_ip' => '192.168.1.1',
            'ssh_port' => 22,
            'vanity_name' => 'test-server',
        ]);

        $this->manager = new TestablePackageManager($this->server);
    }

    public function test_actionable_name_returns_installing_by_default(): void
    {
        $this->assertEquals('Installing', $this->manager->getActionableName());
    }

    public function test_actionable_name_returns_removing_for_remover(): void
    {
        $remover = new TestablePackageRemover($this->server);
        $this->assertEquals('Removing', $remover->getActionableName());
    }

    public function test_count_milestones_returns_total_from_milestones_class(): void
    {
        $this->assertEquals(5, $this->manager->getCountMilestones());
    }

    public function test_track_creates_server_package_event_with_correct_data(): void
    {
        $milestone = 'TEST_MILESTONE';
        $trackClosure = $this->manager->getTrack($milestone);

        Log::shouldReceive('info')
            ->once()
            ->with(
                'Installing milestone: TEST_MILESTONE (step 1/5) for service test-service',
                ['server_id' => $this->server->id, 'service' => 'test-service']
            );

        $this->assertDatabaseMissing('server_package_events', [
            'server_id' => $this->server->id,
            'service_type' => 'test-service',
        ]);

        $trackClosure();

        $this->assertDatabaseHas('server_package_events', [
            'server_id' => $this->server->id,
            'service_type' => 'test-service',
            'provision_type' => 'install',
            'milestone' => 'TEST_MILESTONE',
            'current_step' => 1,
            'total_steps' => 5,
        ]);
    }

    public function test_track_increments_step_counter(): void
    {
        $track1 = $this->manager->getTrack('MILESTONE_1');
        $track2 = $this->manager->getTrack('MILESTONE_2');

        Log::shouldReceive('info')->twice();

        $track1();
        $track2();

        $this->assertDatabaseHas('server_package_events', [
            'milestone' => 'MILESTONE_1',
            'current_step' => 1,
        ]);

        $this->assertDatabaseHas('server_package_events', [
            'milestone' => 'MILESTONE_2',
            'current_step' => 2,
        ]);
    }

    public function test_ssh_creates_connection_with_correct_parameters(): void
    {
        $ssh = $this->manager->ssh('testuser', '192.168.1.100', 2222);
        $this->assertInstanceOf(Ssh::class, $ssh);
    }

    public function test_send_commands_to_remote_executes_closures(): void
    {
        $closureExecuted = false;
        $closure = function () use (&$closureExecuted) {
            $closureExecuted = true;
        };

        $commands = [$closure];
        $this->manager->testSendCommandsToRemote($commands);

        $this->assertTrue($closureExecuted);
    }

    public function test_send_commands_to_remote_executes_commands(): void
    {
        $process = $this->createMock(Process::class);
        $process->expects($this->once())
            ->method('isSuccessful')
            ->willReturn(true);

        $ssh = $this->createMock(Ssh::class);
        $ssh->expects($this->once())
            ->method('disableStrictHostKeyChecking')
            ->willReturn($ssh);
        $ssh->expects($this->once())
            ->method('execute')
            ->with('test command')
            ->willReturn($process);

        $this->manager->setSshMock($ssh);

        $commands = ['test command'];
        $this->manager->testSendCommandsToRemote($commands);
    }

    public function test_send_commands_to_remote_throws_exception_on_failure(): void
    {
        $process = $this->createMock(Process::class);
        $process->expects($this->once())
            ->method('isSuccessful')
            ->willReturn(false);

        $ssh = $this->createMock(Ssh::class);
        $ssh->expects($this->once())
            ->method('disableStrictHostKeyChecking')
            ->willReturn($ssh);
        $ssh->expects($this->once())
            ->method('execute')
            ->with('failing command')
            ->willReturn($process);

        $this->manager->setSshMock($ssh);

        Log::shouldReceive('error')
            ->once()
            ->with(
                'Failed to execute command failing command with test-user',
                \Mockery::any()
            );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Command failed: failing command');

        $commands = ['failing command'];
        $this->manager->testSendCommandsToRemote($commands);
    }

    public function test_send_commands_to_remote_uses_server_ssh_port(): void
    {
        $this->server->update(['ssh_port' => 2222]);

        $process = $this->createMock(Process::class);
        $process->method('isSuccessful')->willReturn(true);

        $ssh = $this->createMock(Ssh::class);
        $ssh->method('disableStrictHostKeyChecking')->willReturn($ssh);
        $ssh->method('execute')->willReturn($process);

        $this->manager->setSshMock($ssh);
        $this->manager->expectSshPort(2222);

        $commands = ['test command'];
        // Should not throw exception when port matches
        $this->manager->testSendCommandsToRemote($commands);
        $this->assertTrue(true); // Test passed if no exception thrown
    }

    public function test_send_commands_to_remote_uses_default_port_when_not_set(): void
    {
        // Test with default port 22 (already set in factory)
        $this->assertEquals(22, $this->server->ssh_port);

        $process = $this->createMock(Process::class);
        $process->method('isSuccessful')->willReturn(true);

        $ssh = $this->createMock(Ssh::class);
        $ssh->method('disableStrictHostKeyChecking')->willReturn($ssh);
        $ssh->method('execute')->willReturn($process);

        $this->manager->setSshMock($ssh);
        $this->manager->expectSshPort(22);

        $commands = ['test command'];
        // Should not throw exception when port matches default
        $this->manager->testSendCommandsToRemote($commands);
        $this->assertTrue(true); // Test passed if no exception thrown
    }
}

/**
 * Test implementation of PackageManager for testing abstract methods
 */
class TestablePackageManager extends PackageManager
{
    private ?MockObject $sshMock = null;

    private ?int $expectedPort = null;

    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    protected function serviceType(): string
    {
        return 'test-service';
    }

    protected function milestones(): Milestones
    {
        return new TestMilestones;
    }

    protected function sshCredential(): SshCredential
    {
        return new TestCredential;
    }

    public function getActionableName(): string
    {
        return $this->actionableName();
    }

    public function getCountMilestones(): int
    {
        return $this->countMilestones();
    }

    public function getTrack(string $milestone): \Closure
    {
        return $this->track($milestone);
    }

    public function testSendCommandsToRemote(array $commands): void
    {
        $this->sendCommandsToRemote($commands);
    }

    public function setSshMock(MockObject $ssh): void
    {
        $this->sshMock = $ssh;
    }

    public function expectSshPort(int $port): void
    {
        $this->expectedPort = $port;
    }

    public function ssh(string $user, string $public_ip, int $port): Ssh
    {
        if ($this->expectedPort !== null && $this->expectedPort !== $port) {
            throw new \InvalidArgumentException("Expected port {$this->expectedPort} but got {$port}");
        }

        return $this->sshMock ?: parent::ssh($user, $public_ip, $port);
    }
}

/**
 * Test implementation of PackageRemover for testing actionable name
 */
class TestablePackageRemover extends PackageRemover
{
    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    protected function serviceType(): string
    {
        return 'test-service';
    }

    protected function milestones(): Milestones
    {
        return new TestMilestones;
    }

    protected function sshCredential(): SshCredential
    {
        return new TestCredential;
    }

    public function execute(): void
    {
        // Test implementation
    }

    public function getActionableName(): string
    {
        return $this->actionableName();
    }
}

/**
 * Test implementation of Milestones
 */
class TestMilestones extends Milestones
{
    public function countLabels(): int
    {
        return 5;
    }
}

/**
 * Test implementation of SshCredential
 */
class TestCredential implements SshCredential
{
    public function user(): string
    {
        return 'test-user';
    }

    public function publicKey(): string
    {
        return 'test-public-key';
    }

    public function privateKey(): string
    {
        return 'test-private-key';
    }
}
