<?php

namespace Tests\Unit;

use App\Models\Server;
use App\Models\Site;
use App\Provision\Sites\SiteCommandProvision;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Spatie\Ssh\Ssh;
use Symfony\Component\Process\Process;

class SiteCommandProvisionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_run_throws_when_command_is_empty(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot execute an empty command.');

        [$server, $site] = $this->fixtures();
        $provisioner = new SiteCommandProvision($server, $site);

        $provisioner->run('   ');
    }

    public function test_run_executes_command_and_returns_output(): void
    {
        [$server, $site] = $this->fixtures();
        $provisioner = new SiteCommandProvision($server, $site);

        $processMock = Mockery::mock(Process::class);
        $processMock->shouldReceive('isSuccessful')->andReturn(true);
        $processMock->shouldReceive('getOutput')->andReturn("ok\n");
        $processMock->shouldReceive('getErrorOutput')->andReturn('');
        $processMock->shouldReceive('getExitCode')->andReturn(0);

        $sshInstanceMock = Mockery::mock();
        $sshInstanceMock->shouldReceive('disableStrictHostKeyChecking')->andReturnSelf();
        $sshInstanceMock->shouldReceive('setTimeout')->with(120)->andReturnSelf();
        $sshInstanceMock->shouldReceive('execute')
            ->once()
            ->withArgs(function (string $command): bool {
                return str_contains($command, "cd '/var/www/example.com/current'") && str_contains($command, '&& php artisan tinker');
            })
            ->andReturn($processMock);

        $sshMock = Mockery::mock('alias:'.Ssh::class);
        $sshMock->shouldReceive('create')
            ->once()
            ->with('forge', '203.0.113.10', 65002)
            ->andReturn($sshInstanceMock);

        $result = $provisioner->run('php artisan tinker');

        $this->assertTrue($result['success']);
        $this->assertSame('ok', $result['output']);
        $this->assertSame('', $result['errorOutput']);
        $this->assertSame(0, $result['exitCode']);
    }

    public function test_run_logs_warning_on_non_zero_exit(): void
    {
        [$server, $site] = $this->fixtures();
        $provisioner = new SiteCommandProvision($server, $site);

        $processMock = Mockery::mock(Process::class);
        $processMock->shouldReceive('isSuccessful')->andReturn(false);
        $processMock->shouldReceive('getOutput')->andReturn('');
        $processMock->shouldReceive('getErrorOutput')->andReturn('failed');
        $processMock->shouldReceive('getExitCode')->andReturn(1);

        $sshInstanceMock = Mockery::mock();
        $sshInstanceMock->shouldReceive('disableStrictHostKeyChecking')->andReturnSelf();
        $sshInstanceMock->shouldReceive('setTimeout')->with(120)->andReturnSelf();
        $sshInstanceMock->shouldReceive('execute')->andReturn($processMock);

        $sshMock = Mockery::mock('alias:'.Ssh::class);
        $sshMock->shouldReceive('create')
            ->once()
            ->with('forge', '203.0.113.10', 65002)
            ->andReturn($sshInstanceMock);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $context['exit_code'] === 1 && $context['stderr'] === 'failed';
            });

        $result = $provisioner->run('ls');

        $this->assertFalse($result['success']);
        $this->assertSame('failed', $result['errorOutput']);
        $this->assertSame(1, $result['exitCode']);
    }

    /**
     * @return array{0: Server, 1: Site}
     */
    private function fixtures(): array
    {
        $server = new Server();
        $server->forceFill([
            'id' => 1,
            'ssh_app_user' => 'forge',
            'public_ip' => '203.0.113.10',
            'ssh_port' => 65002,
        ]);

        $site = new Site();
        $site->forceFill([
            'id' => 5,
            'domain' => 'example.com',
            'document_root' => '/var/www/example.com/current',
        ]);

        return [$server, $site];
    }
}
