<?php

namespace Tests\Unit\Packages\Services\Sites;

use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Credentials\UserCredential;
use App\Packages\Enums\ServiceType;
use App\Packages\Services\Sites\GitRepositoryInstaller;
use App\Packages\Services\Sites\GitRepositoryInstallerMilestones;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GitRepositoryInstallerTest extends TestCase
{
    use RefreshDatabase;

    private GitRepositoryInstaller $installer;

    private Server $server;

    private ServerSite $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = Server::factory()->create();
        $this->site = ServerSite::factory()->create([
            'server_id' => $this->server->id,
            'domain' => 'example.com',
            'document_root' => '/var/www/example.com/public',
        ]);
        $this->installer = new GitRepositoryInstaller($this->server);
    }

    public function test_service_type_returns_site(): void
    {
        $reflection = new \ReflectionClass($this->installer);
        $method = $reflection->getMethod('serviceType');
        $method->setAccessible(true);

        $this->assertEquals(ServiceType::SITE, $method->invoke($this->installer));
    }

    public function test_ssh_credential_returns_user_credential(): void
    {
        $reflection = new \ReflectionClass($this->installer);
        $method = $reflection->getMethod('sshCredential');
        $method->setAccessible(true);

        $credential = $method->invoke($this->installer);
        $this->assertInstanceOf(UserCredential::class, $credential);
    }

    public function test_milestones_returns_git_repository_installer_milestones(): void
    {
        $reflection = new \ReflectionClass($this->installer);
        $method = $reflection->getMethod('milestones');
        $method->setAccessible(true);

        $milestones = $method->invoke($this->installer);
        $this->assertInstanceOf(GitRepositoryInstallerMilestones::class, $milestones);
    }

    public function test_execute_requires_repository(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A repository identifier is required.');

        $this->installer->execute($this->site, []);
    }

    public function test_execute_normalizes_github_repository(): void
    {
        $config = ['repository' => 'owner/repo'];

        // Mock the install method to prevent actual SSH execution
        $installerMock = $this->getMockBuilder(GitRepositoryInstaller::class)
            ->setConstructorArgs([$this->server])
            ->onlyMethods(['install'])
            ->getMock();

        $installerMock->expects($this->once())
            ->method('install')
            ->with($this->callback(function ($commands) {
                // Verify that git@github.com:owner/repo.git is used in commands
                $commandString = implode(' ', array_filter($commands, 'is_string'));
                return str_contains($commandString, 'git@github.com:owner/repo.git');
            }));

        $installerMock->execute($this->site, $config);
    }

    public function test_execute_accepts_ssh_url(): void
    {
        $sshUrl = 'git@github.com:owner/repo.git';
        $config = ['repository' => $sshUrl];

        // Mock the install method to prevent actual SSH execution
        $installerMock = $this->getMockBuilder(GitRepositoryInstaller::class)
            ->setConstructorArgs([$this->server])
            ->onlyMethods(['install'])
            ->getMock();

        $installerMock->expects($this->once())
            ->method('install')
            ->with($this->callback(function ($commands) use ($sshUrl) {
                $commandString = implode(' ', array_filter($commands, 'is_string'));
                return str_contains($commandString, $sshUrl);
            }));

        $installerMock->execute($this->site, $config);
    }

    public function test_execute_sets_default_branch(): void
    {
        $config = ['repository' => 'owner/repo'];

        // Mock the install method to prevent actual SSH execution
        $installerMock = $this->getMockBuilder(GitRepositoryInstaller::class)
            ->setConstructorArgs([$this->server])
            ->onlyMethods(['install'])
            ->getMock();

        $installerMock->expects($this->once())
            ->method('install')
            ->with($this->callback(function ($commands) {
                $commandString = implode(' ', array_filter($commands, 'is_string'));
                return str_contains($commandString, 'git checkout \'main\'');
            }));

        $installerMock->execute($this->site, $config);
    }

    public function test_execute_accepts_custom_branch(): void
    {
        $config = ['repository' => 'owner/repo', 'branch' => 'develop'];

        // Mock the install method to prevent actual SSH execution
        $installerMock = $this->getMockBuilder(GitRepositoryInstaller::class)
            ->setConstructorArgs([$this->server])
            ->onlyMethods(['install'])
            ->getMock();

        $installerMock->expects($this->once())
            ->method('install')
            ->with($this->callback(function ($commands) {
                $commandString = implode(' ', array_filter($commands, 'is_string'));
                return str_contains($commandString, 'git checkout \'develop\'');
            }));

        $installerMock->execute($this->site, $config);
    }

    public function test_execute_validates_repository_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Repository must be an SSH URL or follow the owner/name format.');

        $this->installer->execute($this->site, ['repository' => 'invalid-format']);
    }

    public function test_execute_validates_branch_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Branch may only contain letters, numbers, periods, hyphens, underscores, or slashes.');

        $this->installer->execute($this->site, [
            'repository' => 'owner/repo',
            'branch' => 'invalid branch name!'
        ]);
    }

    public function test_execute_uses_custom_document_root(): void
    {
        $config = [
            'repository' => 'owner/repo',
            'document_root' => '/custom/path',
        ];

        // Mock the install method to prevent actual SSH execution
        $installerMock = $this->getMockBuilder(GitRepositoryInstaller::class)
            ->setConstructorArgs([$this->server])
            ->onlyMethods(['install'])
            ->getMock();

        $installerMock->expects($this->once())
            ->method('install')
            ->with($this->callback(function ($commands) {
                $commandString = implode(' ', array_filter($commands, 'is_string'));
                return str_contains($commandString, '/custom/path');
            }));

        $installerMock->execute($this->site, $config);
    }

    public function test_execute_uses_site_document_root(): void
    {
        $config = ['repository' => 'owner/repo'];

        // Mock the install method to prevent actual SSH execution
        $installerMock = $this->getMockBuilder(GitRepositoryInstaller::class)
            ->setConstructorArgs([$this->server])
            ->onlyMethods(['install'])
            ->getMock();

        $installerMock->expects($this->once())
            ->method('install')
            ->with($this->callback(function ($commands) {
                $commandString = implode(' ', array_filter($commands, 'is_string'));
                return str_contains($commandString, '/var/www/example.com/public');
            }));

        $installerMock->execute($this->site, $config);
    }

    public function test_execute_falls_back_to_domain_path(): void
    {
        $this->site->update(['document_root' => '']);
        $config = ['repository' => 'owner/repo'];

        // Mock the install method to prevent actual SSH execution
        $installerMock = $this->getMockBuilder(GitRepositoryInstaller::class)
            ->setConstructorArgs([$this->server])
            ->onlyMethods(['install'])
            ->getMock();

        $installerMock->expects($this->once())
            ->method('install')
            ->with($this->callback(function ($commands) {
                $commandString = implode(' ', array_filter($commands, 'is_string'));
                return str_contains($commandString, '/var/www/example.com/public');
            }));

        $installerMock->execute($this->site, $config);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
