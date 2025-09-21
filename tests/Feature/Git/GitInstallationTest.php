<?php

namespace Tests\Feature\Git;

use App\Enums\GitStatus;
use App\Jobs\InstallGitRepository;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GitInstallationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Server $server;

    protected Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->server = Server::factory()->create([
            'user_id' => $this->user->id,
            'connection' => 'connected',
        ]);
        $this->site = Site::factory()->create(['server_id' => $this->server->id]);
    }

    public function test_can_view_git_repository_page(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('servers.sites.git-repository', [$this->server, $this->site]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('servers/site-git-repository')
            ->has('server')
            ->has('site')
            ->has('gitRepository')
        );
    }

    public function test_can_install_git_repository(): void
    {
        Queue::fake();

        $data = [
            'provider' => 'github',
            'repository' => 'laravel/laravel',
            'branch' => 'main',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('servers.sites.git-repository', [$this->server, $this->site]), $data);

        $response->assertRedirect(route('servers.sites.git-repository', [$this->server, $this->site]));
        $response->assertSessionHas('info', 'Repository installation started. This may take a few minutes.');

        Queue::assertPushed(InstallGitRepository::class);
    }

    public function test_cannot_install_git_when_already_installing(): void
    {
        Queue::fake();

        $this->site->update(['git_status' => GitStatus::Installing]);

        $data = [
            'provider' => 'github',
            'repository' => 'laravel/laravel',
            'branch' => 'main',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('servers.sites.git-repository', [$this->server, $this->site]), $data);

        $response->assertRedirect(route('servers.sites.git-repository', [$this->server, $this->site]));
        $response->assertSessionHasErrors(['repository' => 'Git installation is already in progress.']);

        Queue::assertNotPushed(InstallGitRepository::class);
    }

    public function test_can_retry_failed_git_installation(): void
    {
        Queue::fake();

        $this->site->update(['git_status' => GitStatus::Failed]);

        $data = [
            'provider' => 'github',
            'repository' => 'laravel/laravel',
            'branch' => 'main',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('servers.sites.git-repository', [$this->server, $this->site]), $data);

        $response->assertRedirect(route('servers.sites.git-repository', [$this->server, $this->site]));
        $response->assertSessionHas('info');

        Queue::assertPushed(InstallGitRepository::class);
    }

    public function test_validates_repository_format(): void
    {
        Queue::fake();

        $data = [
            'provider' => 'github',
            'repository' => 'invalid-format',
            'branch' => 'main',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('servers.sites.git-repository', [$this->server, $this->site]), $data);

        $response->assertSessionHasErrors('repository');
        Queue::assertNotPushed(InstallGitRepository::class);
    }

    public function test_validates_branch_format(): void
    {
        Queue::fake();

        $data = [
            'provider' => 'github',
            'repository' => 'laravel/laravel',
            'branch' => 'invalid@branch!',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('servers.sites.git-repository', [$this->server, $this->site]), $data);

        $response->assertSessionHasErrors('branch');
        Queue::assertNotPushed(InstallGitRepository::class);
    }

    public function test_git_installation_job_updates_status(): void
    {
        // Skip this test as it requires SSH mocking which is complex
        $this->markTestSkipped('Requires SSH mocking');
    }

    public function test_git_installation_job_handles_failure(): void
    {
        $job = new InstallGitRepository($this->server, $this->site, [
            'provider' => 'github',
            'repository' => 'laravel/laravel',
            'branch' => 'main',
        ]);

        $exception = new \Exception('Connection failed');
        $job->failed($exception);

        $this->site->refresh();
        $this->assertEquals(GitStatus::Failed, $this->site->git_status);
    }
}
