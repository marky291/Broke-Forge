<?php

namespace Tests\Unit\Packages\Services\Sites;

use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Credentials\UserCredential;
use App\Packages\Enums\PackageName;
use App\Packages\Services\Sites\SiteRemover;
use App\Packages\Services\Sites\SiteRemoverMilestones;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SiteRemoverTest extends TestCase
{
    use RefreshDatabase;

    private SiteRemover $remover;

    private Server $server;

    private ServerSite $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = Server::factory()->create();
        $this->site = ServerSite::factory()->create([
            'server_id' => $this->server->id,
            'domain' => 'example.com',
            'status' => 'active',
        ]);
        $this->remover = new SiteRemover($this->server);
    }

    public function test_service_type_returns_site(): void
    {
        $reflection = new \ReflectionClass($this->remover);
        $method = $reflection->getMethod('serviceType');
        $method->setAccessible(true);

        $this->assertEquals(PackageName::SITE, $method->invoke($this->remover));
    }

    public function test_ssh_credential_returns_user_credential(): void
    {
        $reflection = new \ReflectionClass($this->remover);
        $method = $reflection->getMethod('sshCredential');
        $method->setAccessible(true);

        $credential = $method->invoke($this->remover);
        $this->assertInstanceOf(UserCredential::class, $credential);
    }

    public function test_milestones_returns_site_remover_milestones(): void
    {
        $reflection = new \ReflectionClass($this->remover);
        $method = $reflection->getMethod('milestones');
        $method->setAccessible(true);

        $milestones = $method->invoke($this->remover);
        $this->assertInstanceOf(SiteRemoverMilestones::class, $milestones);
    }

    public function test_execute_with_site_object(): void
    {
        $config = ['site' => $this->site];

        // Mock the remove method to prevent actual SSH execution
        $removerMock = $this->getMockBuilder(SiteRemover::class)
            ->setConstructorArgs([$this->server])
            ->onlyMethods(['remove'])
            ->getMock();

        $removerMock->expects($this->once())->method('remove');

        $removerMock->execute($config);
    }

    public function test_execute_with_domain_string(): void
    {
        $config = ['domain' => 'test.com'];

        // Mock the remove method to prevent actual SSH execution
        $removerMock = $this->getMockBuilder(SiteRemover::class)
            ->setConstructorArgs([$this->server])
            ->onlyMethods(['remove'])
            ->getMock();

        $removerMock->expects($this->once())->method('remove');

        $removerMock->execute($config);
    }

    public function test_execute_throws_exception_without_domain(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Site domain must be provided for deprovisioning.');

        $this->remover->execute([]);
    }

    public function test_execute_generates_proper_commands(): void
    {
        $config = ['domain' => 'test.com'];

        // Mock the remove method to prevent actual SSH execution
        $removerMock = $this->getMockBuilder(SiteRemover::class)
            ->setConstructorArgs([$this->server])
            ->onlyMethods(['remove'])
            ->getMock();

        $removerMock->expects($this->once())
            ->method('remove')
            ->with($this->callback(function ($commands) {
                $commandString = implode(' ', array_filter($commands, 'is_string'));
                return str_contains($commandString, 'rm -f /etc/nginx/sites-enabled/test.com') &&
                       str_contains($commandString, 'nginx -t') &&
                       str_contains($commandString, 'nginx -s reload');
            }));

        $removerMock->execute($config);
    }

    public function test_execute_includes_closure_for_site_update(): void
    {
        $config = ['site' => $this->site];

        // Mock the remove method to check for closure
        $removerMock = $this->getMockBuilder(SiteRemover::class)
            ->setConstructorArgs([$this->server])
            ->onlyMethods(['remove'])
            ->getMock();

        $removerMock->expects($this->once())
            ->method('remove')
            ->with($this->callback(function ($commands) {
                // Check that we have a closure in the commands
                foreach ($commands as $command) {
                    if ($command instanceof \Closure) {
                        return true;
                    }
                }
                return false;
            }));

        $removerMock->execute($config);
    }

    public function test_site_status_updated_when_closure_executed(): void
    {
        // Ensure site starts with a predictable status
        $this->site->update(['status' => 'active']);
        $config = ['site' => $this->site];

        $reflection = new \ReflectionClass($this->remover);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        $commands = $method->invoke($this->remover, 'example.com', $this->site);

        // Execute all closures to find the site status update one
        foreach ($commands as $command) {
            if ($command instanceof \Closure) {
                $command();
            }
        }

        // Verify site status was updated
        $this->site->refresh();
        $this->assertEquals('disabled', $this->site->status);
        $this->assertNotNull($this->site->deprovisioned_at);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}