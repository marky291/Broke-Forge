<?php

namespace Tests\Unit\Packages\Base;

use App\Models\Server;
use App\Models\ServerEvent;
use App\Models\ServerSite;
use App\Packages\Base\Milestones;
use App\Packages\Base\PackageManager;
use App\Packages\Base\PackageRemover;
use App\Packages\Base\ServerPackage;
use App\Packages\Base\SitePackage;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Ssh\Ssh;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PackageManagerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test setSite() method sets the site and returns self for chaining.
     */
    public function test_set_site_sets_site_and_returns_self(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id]);
        $package = new TestServerPackageInstaller($server);

        // Act
        $result = $package->setSite($site);

        // Assert
        $this->assertSame($package, $result);
        $this->assertSame($site, $package->getSite());
    }

    /**
     * Test user() returns 'root' for ServerPackage implementations.
     */
    public function test_user_returns_root_for_server_package(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $package = new TestServerPackageInstaller($server);

        // Act
        $user = $package->getUser();

        // Assert
        $this->assertEquals('root', $user);
    }

    /**
     * Test user() returns 'brokeforge' for SitePackage implementations.
     */
    public function test_user_returns_brokeforge_for_site_package(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $package = new TestSitePackageInstaller($server);

        // Act
        $user = $package->getUser();

        // Assert
        $this->assertEquals('brokeforge', $user);
    }

    /**
     * Test actionableName() returns 'Installing' for installer packages.
     */
    public function test_actionable_name_returns_installing_for_installer(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $package = new TestServerPackageInstaller($server);

        // Act
        $name = $package->getActionableName();

        // Assert
        $this->assertEquals('Installing', $name);
    }

    /**
     * Test actionableName() returns 'Removing' for remover packages.
     */
    public function test_actionable_name_returns_removing_for_remover(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $package = new TestPackageRemover($server);

        // Act
        $name = $package->getActionableName();

        // Assert
        $this->assertEquals('Removing', $name);
    }

    /**
     * Test countMilestones() returns correct count from milestones.
     */
    public function test_count_milestones_returns_correct_count(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $package = new TestServerPackageInstaller($server);

        // Act
        $count = $package->getCountMilestones();

        // Assert
        $this->assertEquals(3, $count);
    }

    /**
     * Test track() creates a closure that logs and persists event.
     */
    public function test_track_creates_closure_that_persists_event(): void
    {
        // Arrange
        $server = Server::factory()->create([
            'public_ip' => '192.168.1.100',
            'vanity_name' => 'Test Server',
        ]);
        $package = new TestServerPackageInstaller($server);

        // Act
        $trackClosure = $package->getTrack('Step 1');
        $trackClosure();

        // Assert
        $this->assertDatabaseHas('server_events', [
            'server_id' => $server->id,
            'service_type' => 'database',
            'provision_type' => 'install',
            'milestone' => 'Step 1',
            'current_step' => 1,
            'total_steps' => 3,
            'status' => 'pending',
        ]);
    }

    /**
     * Test track() closure updates previous event to success.
     */
    public function test_track_updates_previous_event_to_success(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $package = new TestServerPackageInstaller($server);

        // Act - create first event
        $track1 = $package->getTrack('Step 1');
        $track1();

        // Create second event (should mark first as success)
        $track2 = $package->getTrack('Step 2');
        $track2();

        // Assert
        $events = ServerEvent::where('server_id', $server->id)
            ->orderBy('current_step')
            ->get();

        $this->assertCount(2, $events);
        $this->assertEquals('success', $events[0]->status);
        $this->assertEquals('pending', $events[1]->status);
    }

    /**
     * Test track() includes site_id for SitePackage implementations.
     */
    public function test_track_includes_site_id_for_site_package(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id]);
        $package = new TestSitePackageInstaller($server);
        $package->setSite($site);

        // Act
        $trackClosure = $package->getTrack('Site Step 1');
        $trackClosure();

        // Assert
        $this->assertDatabaseHas('server_events', [
            'server_id' => $server->id,
            'server_site_id' => $site->id,
            'service_type' => 'site',
            'milestone' => 'Site Step 1',
        ]);
    }

    /**
     * Test sendCommandsToRemote() executes string commands successfully.
     */
    public function test_send_commands_executes_string_commands_successfully(): void
    {
        // Arrange
        $realServer = Server::factory()->create();
        $server = Mockery::mock(Server::class)->makePartial();
        $server->id = $realServer->id;

        $package = new TestServerPackageInstaller($server);

        // Mock SSH connection
        $mockSsh = Mockery::mock(Ssh::class);
        $mockProcess = Mockery::mock(Process::class);

        $server->shouldReceive('ssh')
            ->with('root')
            ->andReturn($mockSsh);

        $mockSsh->shouldReceive('setTimeout')
            ->with(570)
            ->andReturnSelf();

        $mockSsh->shouldReceive('execute')
            ->with('echo "test command"')
            ->andReturn($mockProcess);

        $mockProcess->shouldReceive('isSuccessful')
            ->andReturn(true);

        $mockProcess->shouldReceive('getCommandLine')
            ->andReturn('ssh test@server echo "test command"');

        // Act
        $package->executeSendCommandsToRemote(['echo "test command"']);

        // Assert - no exception thrown
        $this->assertTrue(true);
    }

    /**
     * Test sendCommandsToRemote() executes closures that return strings.
     */
    public function test_send_commands_executes_closures_returning_strings(): void
    {
        // Arrange
        $realServer = Server::factory()->create();
        $server = Mockery::mock(Server::class)->makePartial();
        $server->id = $realServer->id;

        $package = new TestServerPackageInstaller($server);

        // Mock SSH connection
        $mockSsh = Mockery::mock(Ssh::class);
        $mockProcess = Mockery::mock(Process::class);

        $server->shouldReceive('ssh')
            ->with('root')
            ->andReturn($mockSsh);

        $mockSsh->shouldReceive('setTimeout')
            ->with(570)
            ->andReturnSelf();

        $mockSsh->shouldReceive('execute')
            ->with('dynamic command')
            ->andReturn($mockProcess);

        $mockProcess->shouldReceive('isSuccessful')
            ->andReturn(true);

        $mockProcess->shouldReceive('getCommandLine')
            ->andReturn('ssh test@server dynamic command');

        // Act
        $closure = fn () => 'dynamic command';
        $package->executeSendCommandsToRemote([$closure]);

        // Assert - no exception thrown
        $this->assertTrue(true);
    }

    /**
     * Test sendCommandsToRemote() skips closures returning non-strings.
     */
    public function test_send_commands_skips_closures_returning_non_strings(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $package = new TestServerPackageInstaller($server);

        // Act - closure returns an object, should be skipped
        $closure = fn () => new \stdClass;
        $package->executeSendCommandsToRemote([$closure]);

        // Assert - no SSH calls should be made, no exception thrown
        $this->assertTrue(true);
    }

    /**
     * Test sendCommandsToRemote() marks event as success after all commands complete.
     */
    public function test_send_commands_marks_event_as_success_after_completion(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $package = new TestServerPackageInstaller($server);

        // Create an event
        $track = $package->getTrack('Test Step');
        $track();

        // Mock SSH connection
        $mockSsh = Mockery::mock(Ssh::class);
        $mockProcess = Mockery::mock(Process::class);

        $mockServer = Mockery::mock($server)->makePartial();
        $mockServer->shouldReceive('ssh')
            ->with('root')
            ->andReturn($mockSsh);

        // Replace the server in the package with the mock
        $package->setServer($mockServer);

        $mockSsh->shouldReceive('setTimeout')
            ->with(570)
            ->andReturnSelf();

        $mockSsh->shouldReceive('execute')
            ->with('test command')
            ->andReturn($mockProcess);

        $mockProcess->shouldReceive('isSuccessful')
            ->andReturn(true);

        $mockProcess->shouldReceive('getCommandLine')
            ->andReturn('ssh test@server test command');

        // Act
        $package->executeSendCommandsToRemote(['test command']);

        // Assert
        $event = ServerEvent::where('server_id', $server->id)->first();
        $this->assertEquals('success', $event->status);
    }

    /**
     * Test sendCommandsToRemote() throws exception and marks event as failed on SSH failure.
     */
    public function test_send_commands_throws_exception_and_marks_event_failed_on_ssh_failure(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $package = new TestServerPackageInstaller($server);

        // Create an event
        $track = $package->getTrack('Test Step');
        $track();

        // Mock SSH connection to fail
        $mockSsh = Mockery::mock(Ssh::class);
        $mockProcess = Mockery::mock(Process::class);

        $mockServer = Mockery::mock($server)->makePartial();
        $mockServer->shouldReceive('ssh')
            ->with('root')
            ->andReturn($mockSsh);

        // Replace the server in the package with the mock
        $package->setServer($mockServer);

        $mockSsh->shouldReceive('setTimeout')
            ->with(570)
            ->andReturnSelf();

        $mockSsh->shouldReceive('execute')
            ->with('failing command')
            ->andReturn($mockProcess);

        $mockProcess->shouldReceive('isSuccessful')
            ->andReturn(false);

        $mockProcess->shouldReceive('getErrorOutput')
            ->andReturn('Command execution failed');

        $mockProcess->shouldReceive('getCommandLine')
            ->andReturn('ssh test@server failing command');

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Command failed: failing command - Command execution failed');

        try {
            $package->executeSendCommandsToRemote(['failing command']);
        } catch (\RuntimeException $e) {
            // Verify event was marked as failed
            $event = ServerEvent::where('server_id', $server->id)->first();
            $this->assertEquals('failed', $event->status);
            $this->assertStringContainsString('Command execution failed', $event->error_log);

            throw $e;
        }
    }

    /**
     * Test sendCommandsToRemote() handles ProcessTimedOutException correctly.
     */
    public function test_send_commands_handles_timeout_exception(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $package = new TestServerPackageInstaller($server);

        // Create an event
        $track = $package->getTrack('Test Step');
        $track();

        // Mock SSH connection to timeout
        $mockSsh = Mockery::mock(Ssh::class);

        $mockServer = Mockery::mock($server)->makePartial();
        $mockServer->shouldReceive('ssh')
            ->with('root')
            ->andReturn($mockSsh);

        // Replace the server in the package with the mock
        $package->setServer($mockServer);

        $mockSsh->shouldReceive('setTimeout')
            ->with(570)
            ->andReturnSelf();

        $mockProcessForTimeout = Mockery::mock(Process::class);
        $mockProcessForTimeout->shouldReceive('getCommandLine')
            ->andReturn('ssh test@server slow command');
        $mockProcessForTimeout->shouldReceive('getTimeout')
            ->andReturn(570.0);

        $mockSsh->shouldReceive('execute')
            ->with('slow command')
            ->andThrow(new ProcessTimedOutException($mockProcessForTimeout, ProcessTimedOutException::TYPE_GENERAL));

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SSH command timed out after 570 seconds');

        try {
            $package->executeSendCommandsToRemote(['slow command']);
        } catch (\RuntimeException $e) {
            // Verify event was marked as failed
            $event = ServerEvent::where('server_id', $server->id)->first();
            $this->assertEquals('failed', $event->status);
            $this->assertStringContainsString('SSH command timed out', $event->error_log);

            throw $e;
        }
    }

    /**
     * Test sendCommandsToRemote() uses 'brokeforge' user for SitePackage.
     */
    public function test_send_commands_uses_brokeforge_user_for_site_package(): void
    {
        // Arrange
        $realServer = Server::factory()->create();
        $server = Mockery::mock(Server::class)->makePartial();
        $server->id = $realServer->id;

        $package = new TestSitePackageInstaller($server);

        // Mock SSH connection
        $mockSsh = Mockery::mock(Ssh::class);
        $mockProcess = Mockery::mock(Process::class);

        $server->shouldReceive('ssh')
            ->with('brokeforge')
            ->andReturn($mockSsh);

        $mockSsh->shouldReceive('setTimeout')
            ->with(570)
            ->andReturnSelf();

        $mockSsh->shouldReceive('execute')
            ->with('site command')
            ->andReturn($mockProcess);

        $mockProcess->shouldReceive('isSuccessful')
            ->andReturn(true);

        $mockProcess->shouldReceive('getCommandLine')
            ->andReturn('ssh brokeforge@server site command');

        // Act
        $package->executeSendCommandsToRemote(['site command']);

        // Assert - no exception thrown, verified 'brokeforge' user was used via mock
        $this->assertTrue(true);
    }

    /**
     * Test sendCommandsToRemote() handles closure throwing exception.
     */
    public function test_send_commands_handles_closure_throwing_exception(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $package = new TestServerPackageInstaller($server);

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Closure command threw an exception at index 0: Test error');

        $closure = function () {
            throw new \Exception('Test error');
        };

        $package->executeSendCommandsToRemote([$closure]);
    }

    /**
     * Test sendCommandsToRemote() marks previous pending events as success on failure.
     */
    public function test_send_commands_marks_previous_pending_events_as_success_on_failure(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $package = new TestServerPackageInstaller($server);

        // Create multiple events
        $track1 = $package->getTrack('Step 1');
        $track1();

        $track2 = $package->getTrack('Step 2');
        $track2(); // This marks step 1 as success and creates step 2 as pending

        $track3 = $package->getTrack('Step 3');
        $track3(); // This marks step 2 as success and creates step 3 as pending

        // Mock SSH connection to fail
        $mockSsh = Mockery::mock(Ssh::class);
        $mockProcess = Mockery::mock(Process::class);

        $mockServer = Mockery::mock($server)->makePartial();
        $mockServer->shouldReceive('ssh')
            ->with('root')
            ->andReturn($mockSsh);

        // Replace the server in the package with the mock
        $package->setServer($mockServer);

        $mockSsh->shouldReceive('setTimeout')
            ->with(570)
            ->andReturnSelf();

        $mockSsh->shouldReceive('execute')
            ->andReturn($mockProcess);

        $mockProcess->shouldReceive('isSuccessful')
            ->andReturn(false);

        $mockProcess->shouldReceive('getErrorOutput')
            ->andReturn('Error');

        $mockProcess->shouldReceive('getCommandLine')
            ->andReturn('ssh command');

        // Act
        try {
            $package->executeSendCommandsToRemote(['failing command']);
        } catch (\RuntimeException $e) {
            // Expected exception
        }

        // Assert - check that step 3 is marked as failed
        $events = ServerEvent::where('server_id', $server->id)
            ->orderBy('current_step')
            ->get();

        $this->assertCount(3, $events);
        $this->assertEquals('success', $events[0]->status); // Step 1
        $this->assertEquals('success', $events[1]->status); // Step 2
        $this->assertEquals('failed', $events[2]->status);   // Step 3 (current)
    }

    /**
     * Test milestone step counter increments correctly.
     */
    public function test_milestone_step_counter_increments_correctly(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $package = new TestServerPackageInstaller($server);

        // Act
        $track1 = $package->getTrack('Step 1');
        $track1();

        $track2 = $package->getTrack('Step 2');
        $track2();

        $track3 = $package->getTrack('Step 3');
        $track3();

        // Assert
        $events = ServerEvent::where('server_id', $server->id)
            ->orderBy('current_step')
            ->get();

        $this->assertEquals(1, $events[0]->current_step);
        $this->assertEquals(2, $events[1]->current_step);
        $this->assertEquals(3, $events[2]->current_step);
    }

    /**
     * Test track() throws exception for unknown package type.
     */
    public function test_track_throws_exception_for_unknown_package_type(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $package = new TestInvalidPackage($server);

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unknown package type');

        $track = $package->getTrack('Test Step');
        $track();
    }
}

// ==================== Test Stub Classes ====================

/**
 * Concrete test implementation of ServerPackage for testing purposes
 */
class TestServerPackageInstaller extends PackageManager implements ServerPackage
{
    public function __construct(
        protected Server $server
    ) {}

    public function packageName(): PackageName
    {
        return PackageName::MySql80;
    }

    public function packageType(): PackageType
    {
        return PackageType::Database;
    }

    public function milestones(): Milestones
    {
        return new TestMilestones;
    }

    // Expose protected methods for testing
    public function getSite(): ?ServerSite
    {
        return $this->site;
    }

    public function getUser(): string
    {
        return $this->user();
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

    public function executeSendCommandsToRemote(array $commands): void
    {
        $this->sendCommandsToRemote($commands);
    }

    public function setServer(Server $server): void
    {
        $this->server = $server;
    }
}

/**
 * Concrete test implementation of SitePackage for testing purposes
 */
class TestSitePackageInstaller extends PackageManager implements SitePackage
{
    public function __construct(
        protected Server $server
    ) {}

    public function packageName(): PackageName
    {
        return PackageName::Site;
    }

    public function packageType(): PackageType
    {
        return PackageType::Site;
    }

    public function milestones(): Milestones
    {
        return new TestMilestones;
    }

    // Expose protected methods for testing
    public function getUser(): string
    {
        return $this->user();
    }

    public function getTrack(string $milestone): \Closure
    {
        return $this->track($milestone);
    }

    public function executeSendCommandsToRemote(array $commands): void
    {
        $this->sendCommandsToRemote($commands);
    }
}

/**
 * Concrete test implementation of PackageRemover for testing purposes
 */
class TestPackageRemover extends PackageRemover implements ServerPackage
{
    public function packageName(): PackageName
    {
        return PackageName::MySql80;
    }

    public function packageType(): PackageType
    {
        return PackageType::Database;
    }

    public function milestones(): Milestones
    {
        return new TestMilestones;
    }

    // Expose protected methods for testing
    public function getActionableName(): string
    {
        return $this->actionableName();
    }
}

/**
 * Test implementation of invalid package (neither ServerPackage nor SitePackage)
 */
class TestInvalidPackage extends PackageManager
{
    public function __construct(
        protected Server $server
    ) {}

    public function packageName(): PackageName
    {
        return PackageName::MySql80;
    }

    public function packageType(): PackageType
    {
        return PackageType::Database;
    }

    public function milestones(): Milestones
    {
        return new TestMilestones;
    }

    public function getTrack(string $milestone): \Closure
    {
        return $this->track($milestone);
    }
}

/**
 * Test implementation of Milestones
 */
class TestMilestones extends Milestones
{
    public function countLabels(): int
    {
        return 3;
    }
}
