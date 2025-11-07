<?php

namespace Tests\Concerns;

use App\Models\Server;
use App\Packages\Credential\Ssh as CredentialSsh;
use Mockery;
use Spatie\Ssh\Ssh;
use Symfony\Component\Process\Process;

trait MocksSshConnections
{
    /**
     * Mock SSH connection by mocking the Credential\Ssh service in the container.
     * Each test creates its own mock which is cleaned up automatically by Mockery.
     */
    protected function mockSshConnection(Server $server, array $commands): void
    {
        // Create a fresh mock SSH instance for this test
        $mockSsh = Mockery::mock(Ssh::class);

        // Allow method chaining
        $mockSsh->shouldReceive('disableStrictHostKeyChecking')
            ->andReturnSelf();

        // Setup command mocks
        foreach ($commands as $commandPattern => $response) {
            $mockProcess = Mockery::mock(Process::class);

            $mockProcess->shouldReceive('isSuccessful')
                ->andReturn($response['success'] ?? true);

            $mockProcess->shouldReceive('getOutput')
                ->andReturn($response['output'] ?? '');

            $mockSsh->shouldReceive('execute')
                ->with($commandPattern)
                ->andReturn($mockProcess);
        }

        // Create a mock of the Credential\Ssh service
        $mockCredentialSsh = Mockery::mock(CredentialSsh::class);
        $mockCredentialSsh->shouldReceive('connect')
            ->with(Mockery::type(Server::class), Mockery::any())
            ->andReturn($mockSsh);

        // Bind the mock in the container so app(Ssh::class) returns our mock
        app()->instance(CredentialSsh::class, $mockCredentialSsh);
    }

    /**
     * Mock a single SSH command execution on a server.
     */
    protected function mockServerSshCommand(
        Server $server,
        string $user,
        string $command,
        string $output,
        bool $successful = true,
        bool $disableStrictHostKeyChecking = true
    ): void {
        $mockSsh = Mockery::mock(Ssh::class);
        $mockProcess = Mockery::mock(Process::class);

        $server->shouldReceive('ssh')
            ->with($user)
            ->andReturn($mockSsh);

        if ($disableStrictHostKeyChecking) {
            $mockSsh->shouldReceive('disableStrictHostKeyChecking')
                ->andReturnSelf();
        }

        $mockSsh->shouldReceive('execute')
            ->with($command)
            ->andReturn($mockProcess);

        $mockProcess->shouldReceive('isSuccessful')
            ->andReturn($successful);

        $mockProcess->shouldReceive('getOutput')
            ->andReturn($output);
    }

    /**
     * Mock SSH log file retrieval (stdout and stderr).
     */
    protected function mockServerSshLogs(
        Server $server,
        string $stdoutLogPath,
        string $stderrLogPath,
        array $stdoutLines = [],
        array $stderrLines = [],
        bool $stdoutSuccess = true,
        bool $stderrSuccess = true
    ): void {
        $this->mockSshConnection($server, [
            "tail -n 500 {$stdoutLogPath} 2>&1 || echo 'Log file not found'" => [
                'success' => $stdoutSuccess,
                'output' => implode("\n", $stdoutLines),
            ],
            "tail -n 500 {$stderrLogPath} 2>&1 || echo 'Log file not found'" => [
                'success' => $stderrSuccess,
                'output' => implode("\n", $stderrLines),
            ],
        ]);
    }

    /**
     * Mock supervisorctl status command output.
     */
    protected function mockServerSshStatus(
        Server $server,
        string $taskName,
        string $state = 'RUNNING',
        string $pid = '1234',
        string $uptime = '2:30:45'
    ): void {
        $statusOutput = "{$taskName}:{$taskName}_00   {$state}   pid {$pid}, uptime {$uptime}";

        $this->mockSshConnection($server, [
            "supervisorctl status {$taskName} 2>&1" => [
                'success' => true,
                'output' => $statusOutput,
            ],
        ]);
    }

    /**
     * Mock multiple SSH commands in sequence.
     */
    protected function mockServerSshCommands(
        Server $server,
        string $user,
        array $commands,
        bool $disableStrictHostKeyChecking = true
    ): void {
        $mockSsh = Mockery::mock(Ssh::class);

        $server->shouldReceive('ssh')
            ->with($user)
            ->andReturn($mockSsh);

        if ($disableStrictHostKeyChecking) {
            $mockSsh->shouldReceive('disableStrictHostKeyChecking')
                ->andReturnSelf();
        }

        foreach ($commands as $command => $response) {
            $mockProcess = Mockery::mock(Process::class);

            $mockSsh->shouldReceive('execute')
                ->with($command)
                ->andReturn($mockProcess);

            $mockProcess->shouldReceive('isSuccessful')
                ->andReturn($response['success'] ?? true);

            $mockProcess->shouldReceive('getOutput')
                ->andReturn($response['output'] ?? '');
        }
    }
}
