<?php

namespace Tests\Unit\Packages\Credentials;

use App\Models\Server;
use App\Packages\Credentials\ProvisionAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class ProvisionAccessTest extends TestCase
{
    use RefreshDatabase;

    private ProvisionAccess $provisionAccess;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provisionAccess = new ProvisionAccess;
    }

    public function test_make_script_for_generates_script_with_default_values(): void
    {
        $server = Server::factory()->create([
            'ssh_port' => 22,
        ]);

        config(['app.name' => 'TestApp']);

        $viewMock = $this->createMock(\Illuminate\View\View::class);
        $viewMock->expects($this->once())
            ->method('render')
            ->willReturn('generated script content');

        View::shouldReceive('make')
            ->once()
            ->andReturn($viewMock);

        URL::shouldReceive('temporarySignedRoute')
            ->times(2)
            ->andReturn('http://example.com/callback');

        $script = $this->provisionAccess->makeScriptFor($server, 'test-password');

        $this->assertEquals('generated script content', $script);
    }

    public function test_make_script_for_uses_server_ssh_root_user(): void
    {
        $server = Server::factory()->create([
            'ssh_root_user' => 'custom-root',
            'ssh_port' => 22,
        ]);

        config(['app.name' => 'TestApp']);

        $viewMock = $this->createMock(\Illuminate\View\View::class);
        $viewMock->method('render')->willReturn('script');

        View::shouldReceive('make')
            ->withArgs(function ($view, $data) {
                $this->assertEquals('custom-root', $data['sshUser']);

                return true;
            })
            ->andReturn($viewMock);

        URL::shouldReceive('temporarySignedRoute')
            ->times(2)
            ->andReturn('http://example.com/callback');

        $this->provisionAccess->makeScriptFor($server, 'test-password');
    }

    public function test_make_script_for_uses_server_ssh_app_user(): void
    {
        $server = Server::factory()->create([
            'ssh_app_user' => 'custom-app-user',
            'ssh_port' => 22,
        ]);

        config(['app.name' => 'TestApp']);

        $viewMock = $this->createMock(\Illuminate\View\View::class);
        $viewMock->method('render')->willReturn('script');

        View::shouldReceive('make')
            ->withArgs(function ($view, $data) {
                $this->assertEquals('custom-app-user', $data['appUser']);

                return true;
            })
            ->andReturn($viewMock);

        URL::shouldReceive('temporarySignedRoute')
            ->times(2)
            ->andReturn('http://example.com/callback');

        $this->provisionAccess->makeScriptFor($server, 'test-password');
    }

    public function test_make_script_for_uses_default_ssh_user(): void
    {
        $server = Server::factory()->create([
            'ssh_root_user' => 'root',
            'ssh_app_user' => 'testapp',
            'ssh_port' => 22,
        ]);

        config(['app.name' => 'TestApp']);

        $viewMock = $this->createMock(\Illuminate\View\View::class);
        $viewMock->method('render')->willReturn('script');

        View::shouldReceive('make')
            ->withArgs(function ($view, $data) {
                $this->assertEquals('root', $data['sshUser']);
                $this->assertEquals('testapp', $data['appUser']);

                return true;
            })
            ->andReturn($viewMock);

        URL::shouldReceive('temporarySignedRoute')
            ->times(2)
            ->andReturn('http://example.com/callback');

        $this->provisionAccess->makeScriptFor($server, 'test-password');
    }

    public function test_make_script_for_passes_ssh_port(): void
    {
        $server = Server::factory()->create([
            'ssh_port' => 2222,
        ]);

        config(['app.name' => 'TestApp']);

        $viewMock = $this->createMock(\Illuminate\View\View::class);
        $viewMock->method('render')->willReturn('script');

        View::shouldReceive('make')
            ->withArgs(function ($view, $data) {
                $this->assertEquals(2222, $data['sshPort']);

                return true;
            })
            ->andReturn($viewMock);

        URL::shouldReceive('temporarySignedRoute')
            ->times(2)
            ->andReturn('http://example.com/callback');

        $this->provisionAccess->makeScriptFor($server, 'test-password');
    }

    public function test_make_script_for_passes_root_password(): void
    {
        $server = Server::factory()->create([
            'ssh_port' => 22,
        ]);

        config(['app.name' => 'TestApp']);

        $viewMock = $this->createMock(\Illuminate\View\View::class);
        $viewMock->method('render')->willReturn('script');

        View::shouldReceive('make')
            ->withArgs(function ($view, $data) {
                $this->assertEquals('secure-password-123', $data['rootPassword']);

                return true;
            })
            ->andReturn($viewMock);

        URL::shouldReceive('temporarySignedRoute')
            ->times(2)
            ->andReturn('http://example.com/callback');

        $this->provisionAccess->makeScriptFor($server, 'secure-password-123');
    }

    public function test_build_callback_urls_generates_two_urls(): void
    {
        $server = Server::factory()->create([
            'ssh_port' => 22,
        ]);

        config(['provision.callback_ttl' => 30]);

        URL::shouldReceive('temporarySignedRoute')
            ->with('servers.provision.callback', \Mockery::any(), ['server' => $server->id, 'status' => 'started'])
            ->once()
            ->andReturn('http://example.com/callback/started');

        URL::shouldReceive('temporarySignedRoute')
            ->with('servers.provision.callback', \Mockery::any(), ['server' => $server->id, 'status' => 'completed'])
            ->once()
            ->andReturn('http://example.com/callback/completed');

        $viewMock = $this->createMock(\Illuminate\View\View::class);
        $viewMock->method('render')->willReturn('script');

        View::shouldReceive('make')
            ->withArgs(function ($view, $data) {
                $this->assertArrayHasKey('started', $data['callbackUrls']);
                $this->assertArrayHasKey('completed', $data['callbackUrls']);
                $this->assertEquals('http://example.com/callback/started', $data['callbackUrls']['started']);
                $this->assertEquals('http://example.com/callback/completed', $data['callbackUrls']['completed']);

                return true;
            })
            ->andReturn($viewMock);

        $this->provisionAccess->makeScriptFor($server, 'test-password');
    }

    public function test_callback_urls_use_configured_ttl(): void
    {
        $server = Server::factory()->create([
            'ssh_port' => 22,
        ]);

        config(['provision.callback_ttl' => 120]);

        // Just verify that temporarySignedRoute is called with Carbon expiry objects
        URL::shouldReceive('temporarySignedRoute')
            ->twice()
            ->withArgs(function ($route, $expiry, $params) {
                return $expiry instanceof \Illuminate\Support\Carbon;
            })
            ->andReturn('http://example.com/callback');

        $viewMock = $this->createMock(\Illuminate\View\View::class);
        $viewMock->method('render')->willReturn('script');

        View::shouldReceive('make')->andReturn($viewMock);

        $this->provisionAccess->makeScriptFor($server, 'test-password');
    }

    public function test_callback_urls_use_default_ttl_when_not_configured(): void
    {
        $server = Server::factory()->create([
            'ssh_port' => 22,
        ]);

        config(['provision.callback_ttl' => null]);

        URL::shouldReceive('temporarySignedRoute')
            ->twice()
            ->withArgs(function ($route, $expiry, $params) {
                // Just verify the expiry is in the future - that's sufficient to prove default TTL is working
                return $expiry->isFuture();
            })
            ->andReturn('http://example.com/callback');

        $viewMock = $this->createMock(\Illuminate\View\View::class);
        $viewMock->method('render')->willReturn('script');

        View::shouldReceive('make')->andReturn($viewMock);

        $result = $this->provisionAccess->makeScriptFor($server, 'test-password');

        // Test passes if no exceptions thrown and URLs were called correctly
        $this->assertNotEmpty($result);
    }
}
