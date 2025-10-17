<?php

namespace Tests\Unit\Packages\Base;

use App\Models\Server;
use App\Packages\Base\Milestones;
use App\Packages\Base\PackageInstaller;
use App\Packages\Base\ServerPackage;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Ssh\Ssh;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PackageInstallerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test constructor sets server correctly.
     */
    public function test_constructor_sets_server_correctly(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Act
        $installer = new PackageInstallerTestStubInstaller($server);

        // Assert
        $this->assertSame($server, $installer->getServer());
    }

    /**
     * Test install() executes commands successfully.
     */
    public function test_install_executes_commands_successfully(): void
    {
        // Arrange
        $realServer = Server::factory()->create();
        $server = Mockery::mock(Server::class)->makePartial();
        $server->id = $realServer->id;

        $installer = new PackageInstallerTestStubInstaller($server);

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
            ->with('test install command')
            ->andReturn($mockProcess);

        $mockProcess->shouldReceive('isSuccessful')
            ->andReturn(true);

        $mockProcess->shouldReceive('getCommandLine')
            ->andReturn('ssh test@server test install command');

        // Act
        $installer->executeInstall(['test install command']);

        // Assert - no exception thrown
        $this->assertTrue(true);
    }

    /**
     * Test install() calls markResourceAsFailed on exception.
     */
    public function test_install_calls_mark_resource_as_failed_on_exception(): void
    {
        // Arrange
        $realServer = Server::factory()->create();
        $server = Mockery::mock(Server::class)->makePartial();
        $server->id = $realServer->id;

        $installer = new PackageInstallerTestStubWithFailTracking($server);

        // Mock SSH connection to fail
        $mockSsh = Mockery::mock(Ssh::class);
        $mockProcess = Mockery::mock(Process::class);

        $server->shouldReceive('ssh')
            ->with('root')
            ->andReturn($mockSsh);

        $mockSsh->shouldReceive('setTimeout')
            ->with(570)
            ->andReturnSelf();

        $mockSsh->shouldReceive('execute')
            ->with('failing command')
            ->andReturn($mockProcess);

        $mockProcess->shouldReceive('isSuccessful')
            ->andReturn(false);

        $mockProcess->shouldReceive('getErrorOutput')
            ->andReturn('Installation failed');

        $mockProcess->shouldReceive('getCommandLine')
            ->andReturn('ssh test@server failing command');

        // Act & Assert
        $this->expectException(\RuntimeException::class);

        try {
            $installer->executeInstall(['failing command']);
        } catch (\RuntimeException $e) {
            // Verify markResourceAsFailed was called
            $this->assertTrue($installer->wasMarkResourceAsFailedCalled());
            $this->assertStringContainsString('Command failed', $installer->getFailureMessage());

            throw $e;
        }
    }

    /**
     * Test install() rethrows exception after marking as failed.
     */
    public function test_install_rethrows_exception_after_marking_failed(): void
    {
        // Arrange
        $realServer = Server::factory()->create();
        $server = Mockery::mock(Server::class)->makePartial();
        $server->id = $realServer->id;

        $installer = new PackageInstallerTestStubInstaller($server);

        // Mock SSH connection to throw exception
        $mockSsh = Mockery::mock(Ssh::class);
        $mockProcess = Mockery::mock(Process::class);

        $server->shouldReceive('ssh')
            ->with('root')
            ->andReturn($mockSsh);

        $mockSsh->shouldReceive('setTimeout')
            ->with(570)
            ->andReturnSelf();

        $mockSsh->shouldReceive('execute')
            ->with('error command')
            ->andReturn($mockProcess);

        $mockProcess->shouldReceive('isSuccessful')
            ->andReturn(false);

        $mockProcess->shouldReceive('getErrorOutput')
            ->andReturn('Command error');

        $mockProcess->shouldReceive('getCommandLine')
            ->andReturn('ssh test@server error command');

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Command failed: error command - Command error');

        $installer->executeInstall(['error command']);
    }

    /**
     * Test markResourceAsFailed() default implementation is no-op.
     */
    public function test_mark_resource_as_failed_default_implementation_is_no_op(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $installer = new PackageInstallerTestStubInstaller($server);

        // Act - calling the default implementation should not throw
        $installer->callMarkResourceAsFailed('Test error message');

        // Assert - no exception thrown
        $this->assertTrue(true);
    }

    /**
     * Test install() handles multiple commands successfully.
     */
    public function test_install_handles_multiple_commands_successfully(): void
    {
        // Arrange
        $realServer = Server::factory()->create();
        $server = Mockery::mock(Server::class)->makePartial();
        $server->id = $realServer->id;

        $installer = new PackageInstallerTestStubInstaller($server);

        // Mock SSH connection
        $mockSsh = Mockery::mock(Ssh::class);
        $mockProcess1 = Mockery::mock(Process::class);
        $mockProcess2 = Mockery::mock(Process::class);

        $server->shouldReceive('ssh')
            ->with('root')
            ->twice()
            ->andReturn($mockSsh);

        $mockSsh->shouldReceive('setTimeout')
            ->with(570)
            ->twice()
            ->andReturnSelf();

        $mockSsh->shouldReceive('execute')
            ->with('command 1')
            ->andReturn($mockProcess1);

        $mockSsh->shouldReceive('execute')
            ->with('command 2')
            ->andReturn($mockProcess2);

        $mockProcess1->shouldReceive('isSuccessful')
            ->andReturn(true);

        $mockProcess1->shouldReceive('getCommandLine')
            ->andReturn('ssh test@server command 1');

        $mockProcess2->shouldReceive('isSuccessful')
            ->andReturn(true);

        $mockProcess2->shouldReceive('getCommandLine')
            ->andReturn('ssh test@server command 2');

        // Act
        $installer->executeInstall(['command 1', 'command 2']);

        // Assert - no exception thrown
        $this->assertTrue(true);
    }

    /**
     * Test install() stops execution after first failing command.
     */
    public function test_install_stops_execution_after_first_failing_command(): void
    {
        // Arrange
        $realServer = Server::factory()->create();
        $server = Mockery::mock(Server::class)->makePartial();
        $server->id = $realServer->id;

        $installer = new PackageInstallerTestStubInstaller($server);

        // Mock SSH connection
        $mockSsh = Mockery::mock(Ssh::class);
        $mockProcess1 = Mockery::mock(Process::class);

        $server->shouldReceive('ssh')
            ->with('root')
            ->once() // Only first command should execute
            ->andReturn($mockSsh);

        $mockSsh->shouldReceive('setTimeout')
            ->with(570)
            ->once()
            ->andReturnSelf();

        $mockSsh->shouldReceive('execute')
            ->with('failing command')
            ->once()
            ->andReturn($mockProcess1);

        $mockProcess1->shouldReceive('isSuccessful')
            ->andReturn(false);

        $mockProcess1->shouldReceive('getErrorOutput')
            ->andReturn('First command failed');

        $mockProcess1->shouldReceive('getCommandLine')
            ->andReturn('ssh test@server failing command');

        // Act & Assert
        $this->expectException(\RuntimeException::class);

        try {
            // Second command should never execute
            $installer->executeInstall(['failing command', 'should not execute']);
        } catch (\RuntimeException $e) {
            // Verify only one SSH call was made
            $this->assertTrue(true);
            throw $e;
        }
    }
}

// ==================== Test Stub Classes ====================

/**
 * Concrete test implementation of PackageInstaller for testing purposes
 */
class PackageInstallerTestStubInstaller extends PackageInstaller implements ServerPackage
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
        return new PackageInstallerTestMilestones;
    }

    // Expose protected methods for testing
    public function executeInstall(array $commands): void
    {
        $this->install($commands);
    }

    public function callMarkResourceAsFailed(string $errorMessage): void
    {
        $this->markResourceAsFailed($errorMessage);
    }

    public function getServer(): Server
    {
        return $this->server;
    }
}

/**
 * Test implementation with markResourceAsFailed tracking
 */
class PackageInstallerTestStubWithFailTracking extends PackageInstaller implements ServerPackage
{
    private bool $markResourceAsFailedCalled = false;

    private string $failureMessage = '';

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
        return new PackageInstallerTestMilestones;
    }

    protected function markResourceAsFailed(string $errorMessage): void
    {
        $this->markResourceAsFailedCalled = true;
        $this->failureMessage = $errorMessage;
    }

    public function executeInstall(array $commands): void
    {
        $this->install($commands);
    }

    public function wasMarkResourceAsFailedCalled(): bool
    {
        return $this->markResourceAsFailedCalled;
    }

    public function getFailureMessage(): string
    {
        return $this->failureMessage;
    }
}

/**
 * Test implementation of Milestones
 */
class PackageInstallerTestMilestones extends Milestones
{
    public function countLabels(): int
    {
        return 2;
    }
}
