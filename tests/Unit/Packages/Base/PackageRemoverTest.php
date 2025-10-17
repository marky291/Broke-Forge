<?php

namespace Tests\Unit\Packages\Base;

use App\Models\Server;
use App\Packages\Base\Milestones;
use App\Packages\Base\PackageRemover;
use App\Packages\Base\ServerPackage;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Ssh\Ssh;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PackageRemoverTest extends TestCase
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
        $remover = new PackageRemoverTestStubRemover($server);

        // Assert
        $this->assertSame($server, $remover->getServer());
    }

    /**
     * Test remove() executes commands successfully.
     */
    public function test_remove_executes_commands_successfully(): void
    {
        // Arrange
        $realServer = Server::factory()->create();
        $server = Mockery::mock(Server::class)->makePartial();
        $server->id = $realServer->id;

        $remover = new PackageRemoverTestStubRemover($server);

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
            ->with('test remove command')
            ->andReturn($mockProcess);

        $mockProcess->shouldReceive('isSuccessful')
            ->andReturn(true);

        $mockProcess->shouldReceive('getCommandLine')
            ->andReturn('ssh test@server test remove command');

        // Act
        $remover->executeRemove(['test remove command']);

        // Assert - no exception thrown
        $this->assertTrue(true);
    }

    /**
     * Test remove() calls markResourceAsFailed on exception.
     */
    public function test_remove_calls_mark_resource_as_failed_on_exception(): void
    {
        // Arrange
        $realServer = Server::factory()->create();
        $server = Mockery::mock(Server::class)->makePartial();
        $server->id = $realServer->id;

        $remover = new PackageRemoverTestStubWithFailTracking($server);

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
            ->andReturn('Removal failed');

        $mockProcess->shouldReceive('getCommandLine')
            ->andReturn('ssh test@server failing command');

        // Act & Assert
        $this->expectException(\RuntimeException::class);

        try {
            $remover->executeRemove(['failing command']);
        } catch (\RuntimeException $e) {
            // Verify markResourceAsFailed was called
            $this->assertTrue($remover->wasMarkResourceAsFailedCalled());
            $this->assertStringContainsString('Command failed', $remover->getFailureMessage());

            throw $e;
        }
    }

    /**
     * Test remove() rethrows exception after marking as failed.
     */
    public function test_remove_rethrows_exception_after_marking_failed(): void
    {
        // Arrange
        $realServer = Server::factory()->create();
        $server = Mockery::mock(Server::class)->makePartial();
        $server->id = $realServer->id;

        $remover = new PackageRemoverTestStubRemover($server);

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

        $remover->executeRemove(['error command']);
    }

    /**
     * Test markResourceAsFailed() default implementation is no-op.
     */
    public function test_mark_resource_as_failed_default_implementation_is_no_op(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $remover = new PackageRemoverTestStubRemover($server);

        // Act - calling the default implementation should not throw
        $remover->callMarkResourceAsFailed('Test error message');

        // Assert - no exception thrown
        $this->assertTrue(true);
    }

    /**
     * Test remove() handles multiple commands successfully.
     */
    public function test_remove_handles_multiple_commands_successfully(): void
    {
        // Arrange
        $realServer = Server::factory()->create();
        $server = Mockery::mock(Server::class)->makePartial();
        $server->id = $realServer->id;

        $remover = new PackageRemoverTestStubRemover($server);

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
        $remover->executeRemove(['command 1', 'command 2']);

        // Assert - no exception thrown
        $this->assertTrue(true);
    }

    /**
     * Test remove() stops execution after first failing command.
     */
    public function test_remove_stops_execution_after_first_failing_command(): void
    {
        // Arrange
        $realServer = Server::factory()->create();
        $server = Mockery::mock(Server::class)->makePartial();
        $server->id = $realServer->id;

        $remover = new PackageRemoverTestStubRemover($server);

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
            $remover->executeRemove(['failing command', 'should not execute']);
        } catch (\RuntimeException $e) {
            // Verify only one SSH call was made
            $this->assertTrue(true);
            throw $e;
        }
    }

    /**
     * Test actionableName() returns 'Removing' for remover packages.
     */
    public function test_actionable_name_returns_removing(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $remover = new PackageRemoverTestStubRemover($server);

        // Act
        $name = $remover->getActionableName();

        // Assert
        $this->assertEquals('Removing', $name);
    }

    /**
     * Test remove() handles empty command array.
     */
    public function test_remove_handles_empty_command_array(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $remover = new PackageRemoverTestStubRemover($server);

        // Act
        $remover->executeRemove([]);

        // Assert - no exception thrown
        $this->assertTrue(true);
    }
}

// ==================== Test Stub Classes ====================

/**
 * Concrete test implementation of PackageRemover for testing purposes
 */
class PackageRemoverTestStubRemover extends PackageRemover implements ServerPackage
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
        return new PackageRemoverTestMilestones;
    }

    // Expose protected methods for testing
    public function executeRemove(array $commands): void
    {
        $this->remove($commands);
    }

    public function callMarkResourceAsFailed(string $errorMessage): void
    {
        $this->markResourceAsFailed($errorMessage);
    }

    public function getServer(): Server
    {
        return $this->server;
    }

    public function getActionableName(): string
    {
        return $this->actionableName();
    }
}

/**
 * Test implementation with markResourceAsFailed tracking
 */
class PackageRemoverTestStubWithFailTracking extends PackageRemover implements ServerPackage
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
        return new PackageRemoverTestMilestones;
    }

    protected function markResourceAsFailed(string $errorMessage): void
    {
        $this->markResourceAsFailedCalled = true;
        $this->failureMessage = $errorMessage;
    }

    public function executeRemove(array $commands): void
    {
        $this->remove($commands);
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
class PackageRemoverTestMilestones extends Milestones
{
    public function countLabels(): int
    {
        return 2;
    }
}
