<?php

namespace Tests\Concerns;

use Mockery;
use Spatie\Ssh\Ssh;

trait MocksSshConnections
{
    /**
     * Mock SSH connections to return successful responses.
     */
    protected function mockSuccessfulSshConnections(array $outputLines = []): void
    {
        $output = implode("\n", $outputLines);

        $mockProcess = Mockery::mock(\Symfony\Component\Process\Process::class);
        $mockProcess->shouldReceive('isSuccessful')->andReturn(true);
        $mockProcess->shouldReceive('getOutput')->andReturn($output);
        $mockProcess->shouldReceive('getErrorOutput')->andReturn('');
        $mockProcess->shouldReceive('getExitCode')->andReturn(0);

        $mockSsh = Mockery::mock(Ssh::class);
        $mockSsh->shouldReceive('disableStrictHostKeyChecking')->andReturnSelf();
        $mockSsh->shouldReceive('setTimeout')->andReturnSelf();
        $mockSsh->shouldReceive('execute')->andReturn($mockProcess);

        $this->app->bind(Ssh::class, fn () => $mockSsh);
    }

    /**
     * Mock SSH connections to return failed responses.
     */
    protected function mockFailedSshConnections(string $errorOutput = 'Connection failed', int $exitCode = 1): void
    {
        $mockProcess = Mockery::mock(\Symfony\Component\Process\Process::class);
        $mockProcess->shouldReceive('isSuccessful')->andReturn(false);
        $mockProcess->shouldReceive('getOutput')->andReturn('');
        $mockProcess->shouldReceive('getErrorOutput')->andReturn($errorOutput);
        $mockProcess->shouldReceive('getExitCode')->andReturn($exitCode);

        $mockSsh = Mockery::mock(Ssh::class);
        $mockSsh->shouldReceive('disableStrictHostKeyChecking')->andReturnSelf();
        $mockSsh->shouldReceive('setTimeout')->andReturnSelf();
        $mockSsh->shouldReceive('execute')->andReturn($mockProcess);

        $this->app->bind(Ssh::class, fn () => $mockSsh);
    }
}
