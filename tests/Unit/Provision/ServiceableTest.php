<?php

namespace Tests\Unit\Provision;

use App\Models\Server;
use App\Provision\Enums\ExecutableUser;
use App\Provision\InstallableService;
use App\Provision\Milestones;
use App\Provision\Server\Access\SshCredential;
use App\Provision\Server\Access\UserCredential;
use Mockery;
use Spatie\Ssh\Ssh;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ServiceableTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_commands_use_app_ssh_access_credentials(): void
    {
        $server = new Server([
            'public_ip' => '203.0.113.10',
            'ssh_port' => 2222,
            'ssh_root_user' => 'root',
            'ssh_app_user' => 'deployer',
        ]);

        $service = new class($server) extends InstallableService
        {
            public array $commands = ['echo "ok"'];

            public function run(): void
            {
                $this->sendCommandsToRemote($this->commands);
            }

            protected function serviceType(): string
            {
                return 'test';
            }

            protected function milestones(): Milestones
            {
                return new class extends Milestones
                {
                    public function countLabels(): int
                    {
                        return 0;
                    }
                };
            }

            protected function sshCredential(): SshCredential
            {
                return new UserCredential;
            }

            protected function executableUser(): ExecutableUser
            {
                return ExecutableUser::AppUser;
            }
        };

        $sshAlias = Mockery::mock('alias:'.Ssh::class);
        $sshBuilder = Mockery::mock();
        $process = Mockery::mock(Process::class);

        $expectedPrivateKey = (new UserCredential)->privateKey();

        $sshAlias->shouldReceive('create')
            ->once()
            ->with('deployer', '203.0.113.10', 2222)
            ->andReturn($sshBuilder);

        $sshBuilder->shouldReceive('disableStrictHostKeyChecking')->once()->andReturnSelf();
        $sshBuilder->shouldReceive('usePrivateKey')->once()->with($expectedPrivateKey)->andReturnSelf();
        $sshBuilder->shouldReceive('execute')->once()->with('echo "ok"')->andReturn($process);

        $process->shouldReceive('isSuccessful')->once()->andReturnTrue();

        $service->run();

        $this->assertTrue(true);
    }

    public function test_using_custom_ssh_access_overrides_default_key(): void
    {
        $server = new Server([
            'public_ip' => '203.0.113.10',
            'ssh_port' => 22,
            'ssh_root_user' => 'root',
            'ssh_app_user' => 'deploy',
        ]);

        $service = new class($server) extends InstallableService
        {
            public array $commands = ['whoami'];

            public function run(): void
            {
                $this->sendCommandsToRemote($this->commands);
            }

            protected function serviceType(): string
            {
                return 'test';
            }

            protected function milestones(): Milestones
            {
                return new class extends Milestones
                {
                    public function countLabels(): int
                    {
                        return 0;
                    }
                };
            }

            protected function sshCredential(): SshCredential
            {
                return new UserCredential;
            }

            protected function executableUser(): ExecutableUser
            {
                return ExecutableUser::AppUser;
            }
        };

        $customAccess = new class implements SshCredential
        {
            public function user(): string
            {
                return 'deploy';
            }

            public function publicKey(): string
            {
                return '/tmp/custom.pub';
            }

            public function privateKey(): string
            {
                return '/tmp/custom';
            }
        };

        $service->usingSshAccess($customAccess);

        $sshAlias = Mockery::mock('alias:'.Ssh::class);
        $sshBuilder = Mockery::mock();
        $process = Mockery::mock(Process::class);

        $sshAlias->shouldReceive('create')
            ->once()
            ->with('deploy', '203.0.113.10', 22)
            ->andReturn($sshBuilder);

        $sshBuilder->shouldReceive('disableStrictHostKeyChecking')->once()->andReturnSelf();
        $sshBuilder->shouldReceive('usePrivateKey')->once()->with('/tmp/custom')->andReturnSelf();
        $sshBuilder->shouldReceive('execute')->once()->with('whoami')->andReturn($process);

        $process->shouldReceive('isSuccessful')->once()->andReturnTrue();

        $service->run();

        $this->assertTrue(true);
    }
}
