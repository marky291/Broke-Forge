<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerDeployment;
use App\Models\ServerSite;
use App\Models\User;
use App\Packages\Services\Sites\Deployment\SiteGitDeploymentJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServerSiteDeploymentsControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test guest cannot access deployments page.
     */
    public function test_guest_cannot_access_deployments_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->withGit()->create(['server_id' => $server->id]);

        // Act
        $response = $this->get("/servers/{$server->id}/sites/{$site->id}/deployments");

        // Assert
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test authenticated user can access their site deployments page.
     */
    public function test_user_can_access_their_site_deployments_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->withGit()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites/{$site->id}/deployments");

        // Assert
        $response->assertStatus(200);
    }

    /**
     * Test user cannot access other users site deployments page.
     */
    public function test_user_cannot_access_other_users_site_deployments_page(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);
        $site = ServerSite::factory()->withGit()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites/{$site->id}/deployments");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test deployments page redirects if Git repository is not installed.
     */
    public function test_deployments_page_redirects_if_git_not_installed(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites/{$site->id}/deployments");

        // Assert
        $response->assertRedirect("/servers/{$server->id}/sites/{$site->id}/settings/git/setup");
        $response->assertSessionHas('error', 'Git repository must be installed before deploying.');
    }

    /**
     * Test deployments page renders correct Inertia component.
     */
    public function test_deployments_page_renders_correct_inertia_component(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->withGit()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites/{$site->id}/deployments");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('servers/site-deployments')
            ->has('site')
        );
    }

    /**
     * Test user can update deployment script for their site.
     */
    public function test_user_can_update_deployment_script(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->withGit()->create(['server_id' => $server->id]);

        $newScript = "git fetch && git pull\ncomposer install\nnpm install && npm run build";

        // Act
        $response = $this->actingAs($user)
            ->put("/servers/{$server->id}/sites/{$site->id}/deployments", [
                'deployment_script' => $newScript,
            ]);

        // Assert
        $response->assertRedirect("/servers/{$server->id}/sites/{$site->id}/deployments");
        $response->assertSessionHas('success', 'Deployment script updated successfully.');

        $site->refresh();
        $this->assertEquals($newScript, $site->getDeploymentScript());
    }

    /**
     * Test deployment script update requires valid data.
     */
    public function test_deployment_script_update_requires_valid_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->withGit()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->put("/servers/{$server->id}/sites/{$site->id}/deployments", [
                'deployment_script' => '', // Empty script
            ]);

        // Assert
        $response->assertSessionHasErrors(['deployment_script']);
    }

    /**
     * Test user can deploy their site.
     */
    public function test_user_can_deploy_site(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->withGit()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/sites/{$site->id}/deployments");

        // Assert
        $response->assertRedirect("/servers/{$server->id}/sites/{$site->id}/deployments");
        $response->assertSessionHas('success', 'Deployment started. Refresh the page to see progress.');

        // Verify deployment record was created with pending status
        $this->assertDatabaseHas('server_deployments', [
            'server_id' => $server->id,
            'server_site_id' => $site->id,
            'status' => TaskStatus::Pending->value,
        ]);

        // Verify job was dispatched
        Queue::assertPushed(SiteGitDeploymentJob::class, function ($job) use ($server) {
            return $job->server->id === $server->id;
        });
    }

    /**
     * Test deployment creates record with correct deployment script.
     */
    public function test_deployment_creates_record_with_correct_script(): void
    {
        // Arrange
        Queue::fake();
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->withGit()->create(['server_id' => $server->id]);

        $customScript = "git fetch && git pull\ncomposer install --no-dev";
        $site->updateDeploymentScript($customScript);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/sites/{$site->id}/deployments");

        // Assert
        $response->assertRedirect("/servers/{$server->id}/sites/{$site->id}/deployments");

        // Verify deployment record has correct script
        $deployment = ServerDeployment::where('server_site_id', $site->id)->first();
        $this->assertNotNull($deployment);
        $this->assertEquals($customScript, $deployment->deployment_script);
    }

    /**
     * Test deployment requires Git repository to be installed.
     */
    public function test_deployment_requires_git_repository(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/sites/{$site->id}/deployments");

        // Assert
        $response->assertRedirect("/servers/{$server->id}/sites/{$site->id}/settings/git/setup");
        $response->assertSessionHas('error', 'Git repository must be installed before deploying.');

        // Verify no deployment record was created
        $this->assertDatabaseMissing('server_deployments', [
            'server_site_id' => $site->id,
        ]);
    }

    /**
     * Test user cannot deploy other users site.
     */
    public function test_user_cannot_deploy_other_users_site(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);
        $site = ServerSite::factory()->withGit()->create(['server_id' => $server->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/sites/{$site->id}/deployments");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test deployment status endpoint returns correct data.
     */
    public function test_deployment_status_endpoint_returns_correct_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->withGit()->create(['server_id' => $server->id]);

        $deployment = ServerDeployment::create([
            'server_id' => $server->id,
            'server_site_id' => $site->id,
            'status' => TaskStatus::Success,
            'deployment_script' => 'git pull',
            'output' => 'Deployment successful',
            'exit_code' => 0,
            'commit_sha' => 'abc123',
            'branch' => 'main',
            'duration_ms' => 5000,
            'started_at' => now()->subSeconds(5),
            'completed_at' => now(),
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites/{$site->id}/deployments/{$deployment->id}/status");

        // Assert
        $response->assertStatus(200);
        $response->assertJson([
            'id' => $deployment->id,
            'status' => TaskStatus::Success->value,
            'exit_code' => 0,
            'commit_sha' => 'abc123',
            'branch' => 'main',
            'duration_ms' => 5000,
            'duration_seconds' => 5.0,
            'is_running' => false,
            'is_success' => true,
            'is_failed' => false,
        ]);
    }

    /**
     * Test user cannot access deployment status for other users deployments.
     */
    public function test_user_cannot_access_other_users_deployment_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);
        $site = ServerSite::factory()->withGit()->create(['server_id' => $server->id]);

        $deployment = ServerDeployment::create([
            'server_id' => $server->id,
            'server_site_id' => $site->id,
            'status' => TaskStatus::Pending,
            'deployment_script' => 'git pull',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites/{$site->id}/deployments/{$deployment->id}/status");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test stream log endpoint returns error when no log file path configured.
     */
    public function test_stream_log_returns_error_when_no_log_file_path(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $site = ServerSite::factory()->withGit()->create(['server_id' => $server->id]);

        $deployment = ServerDeployment::create([
            'server_id' => $server->id,
            'server_site_id' => $site->id,
            'status' => TaskStatus::Pending,
            'deployment_script' => 'git pull',
            'log_file_path' => null,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites/{$site->id}/deployments/{$deployment->id}/stream");

        // Assert
        $response->assertStatus(200);
        $response->assertJson([
            'output' => null,
            'file_size' => 0,
            'status' => TaskStatus::Pending->value,
            'is_running' => true,
            'error' => 'Log file path not configured for this deployment',
        ]);
    }

    /**
     * Test user cannot access log stream for other users deployments.
     */
    public function test_user_cannot_access_log_stream_for_other_users_deployments(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);
        $site = ServerSite::factory()->withGit()->create(['server_id' => $server->id]);

        $deployment = ServerDeployment::create([
            'server_id' => $server->id,
            'server_site_id' => $site->id,
            'status' => TaskStatus::Pending,
            'deployment_script' => 'git pull',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/sites/{$site->id}/deployments/{$deployment->id}/stream");

        // Assert
        $response->assertStatus(403);
    }
}
