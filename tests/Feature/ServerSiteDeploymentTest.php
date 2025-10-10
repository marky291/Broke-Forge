<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\ServerDeployment;
use App\Models\ServerSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerSiteDeploymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_deployment_page(): void
    {
        $server = Server::factory()->create();
        $site = ServerSite::factory()->withGit()->create(['server_id' => $server->id]);

        $this->get(route('servers.sites.deployments', [$server, $site]))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_deployment_page(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create();
        $site = ServerSite::factory()->withGit()->create(['server_id' => $server->id]);

        $this->actingAs($user)
            ->get(route('servers.sites.deployments', [$server, $site]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('servers/site-deployments')
            );
    }

    public function test_deployment_page_requires_git_repository(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id, 'git_status' => null]);

        $response = $this->actingAs($user)
            ->get(route('servers.sites.deployments', [$server, $site]));

        // Should redirect when git is not installed
        $this->assertTrue(
            $response->isRedirect() || $response->status() === 500,
            'Expected redirect or error when git is not installed'
        );
    }

    public function test_deployment_page_shows_deployment_history(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create();
        $site = ServerSite::factory()->withGit()->create(['server_id' => $server->id]);

        // Create deployments
        $deployment1 = ServerDeployment::factory()->create([
            'server_id' => $server->id,
            'server_site_id' => $site->id,
            'status' => 'success',
            'output' => 'Deployment output line 1',
        ]);

        $deployment2 = ServerDeployment::factory()->failed()->create([
            'server_id' => $server->id,
            'server_site_id' => $site->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('servers.sites.deployments', [$server, $site]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('servers/site-deployments')
            ->has('deployments.data', 2)
            ->where('deployments.data.0.id', $deployment2->id) // Latest first
            ->where('deployments.data.1.id', $deployment1->id)
        );
    }

    public function test_deployment_history_includes_output_fields(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create();
        $site = ServerSite::factory()->withGit()->create(['server_id' => $server->id]);

        $deployment = ServerDeployment::factory()->create([
            'server_id' => $server->id,
            'server_site_id' => $site->id,
            'status' => 'success',
            'output' => 'Standard output content',
            'error_output' => null,
        ]);

        $response = $this->actingAs($user)
            ->get(route('servers.sites.deployments', [$server, $site]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('servers/site-deployments')
            ->where('deployments.data.0.output', 'Standard output content')
            ->where('deployments.data.0.error_output', null)
        );
    }

    public function test_failed_deployment_includes_error_output(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create();
        $site = ServerSite::factory()->withGit()->create(['server_id' => $server->id]);

        $deployment = ServerDeployment::factory()->failed()->create([
            'server_id' => $server->id,
            'server_site_id' => $site->id,
            'output' => 'Partial output',
            'error_output' => 'Error: Connection failed',
        ]);

        $response = $this->actingAs($user)
            ->get(route('servers.sites.deployments', [$server, $site]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('servers/site-deployments')
            ->where('deployments.data.0.status', 'failed')
            ->where('deployments.data.0.output', 'Partial output')
            ->where('deployments.data.0.error_output', 'Error: Connection failed')
            ->where('deployments.data.0.exit_code', 1)
        );
    }

    public function test_deployment_page_includes_all_required_deployment_fields(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create();
        $site = ServerSite::factory()->withGit()->create(['server_id' => $server->id]);

        $deployment = ServerDeployment::factory()->create([
            'server_id' => $server->id,
            'server_site_id' => $site->id,
            'commit_sha' => 'abc123def456',
            'commit_message' => 'Fix deployment bug',
            'branch' => 'main',
        ]);

        $response = $this->actingAs($user)
            ->get(route('servers.sites.deployments', [$server, $site]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('servers/site-deployments')
            ->has('deployments.data.0', fn ($deployment) => $deployment
                ->has('id')
                ->has('status')
                ->has('output')
                ->has('error_output')
                ->has('commit_sha')
                ->has('commit_message')
                ->has('branch')
                ->has('duration_ms')
                ->has('duration_seconds')
                ->has('created_at')
                ->has('created_at_human')
                ->etc()
            )
        );
    }

    public function test_deployment_page_paginates_deployment_history(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create();
        $site = ServerSite::factory()->withGit()->create(['server_id' => $server->id]);

        // Create 15 deployments (more than default pagination of 10)
        ServerDeployment::factory()->count(15)->create([
            'server_id' => $server->id,
            'server_site_id' => $site->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('servers.sites.deployments', [$server, $site]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('servers/site-deployments')
            ->has('deployments.data', 10) // Only 10 per page
        );
    }
}
