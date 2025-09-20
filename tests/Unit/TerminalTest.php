<?php

namespace Tests\Unit;

use App\Provision\Serviceable;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\TestCase;
use Spatie\Ssh\Ssh;
use Symfony\Component\Process\Process;

class TerminalTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_constructor_sets_properties(): void
    {
        $terminal = new Serviceable('user', 'host');

        $this->assertSame('user', $terminal->sshUser);
        $this->assertSame('host', $terminal->ssHost);
        $this->assertSame([], $terminal->commands);
    }

    public function test_execute_with_empty_commands_returns_early(): void
    {
        $terminal = new Serviceable('user', 'host');

        // Mock Ssh facade
        $sshMock = Mockery::mock('alias:'.Ssh::class);
        $sshMock->shouldNotReceive('create');

        $terminal->execute();

        $this->assertTrue(true); // If we get here, no SSH call was made
    }

    public function test_execute_calls_ssh_with_commands_array(): void
    {
        $terminal = new Serviceable('user', 'host');
        $terminal->commands = ['ls -la', 'pwd', 'whoami'];

        // Mock process
        $processMock = Mockery::mock(Process::class);
        $processMock->shouldReceive('isSuccessful')->once()->andReturn(true);
        $processMock->shouldReceive('getOutput')->once()->andReturn('command output');

        // Mock SSH instance
        $sshInstanceMock = Mockery::mock();
        $sshInstanceMock->shouldReceive('execute')
            ->once()
            ->with(['ls -la', 'pwd', 'whoami'])
            ->andReturn($processMock);

        // Mock Ssh facade
        $sshMock = Mockery::mock('alias:'.Ssh::class);
        $sshMock->shouldReceive('create')
            ->once()
            ->with('user', 'host')
            ->andReturn($sshInstanceMock);

        // Mock Log facade
        Log::shouldReceive('info')
            ->once()
            ->with('command output');

        $terminal->execute();

        // Assert that commands were passed as array (verified by mock expectations)
        $this->assertCount(3, $terminal->commands);
    }

    public function test_execute_logs_error_on_failure(): void
    {
        $terminal = new Serviceable('user', 'host');
        $terminal->commands = ['failing-command'];

        // Mock process
        $processMock = Mockery::mock(Process::class);
        $processMock->shouldReceive('isSuccessful')->once()->andReturn(false);
        $processMock->shouldReceive('getErrorOutput')->once()->andReturn('error output');

        // Mock SSH instance
        $sshInstanceMock = Mockery::mock();
        $sshInstanceMock->shouldReceive('execute')
            ->once()
            ->with(['failing-command'])
            ->andReturn($processMock);

        // Mock Ssh facade
        $sshMock = Mockery::mock('alias:'.Ssh::class);
        $sshMock->shouldReceive('create')
            ->once()
            ->with('user', 'host')
            ->andReturn($sshInstanceMock);

        // Mock Log facade
        Log::shouldReceive('error')
            ->once()
            ->with('error output');

        $terminal->execute();

        // Assert that commands array was properly set
        $this->assertCount(1, $terminal->commands);
        $this->assertSame('failing-command', $terminal->commands[0]);
    }
}
