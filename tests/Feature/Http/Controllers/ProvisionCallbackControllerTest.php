<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerDatabase;
use App\Models\ServerFirewall;
use App\Models\ServerPhp;
use App\Models\ServerReverseProxy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Mockery;
use Tests\TestCase;

class ProvisionCallbackControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test provisioning callback requires signed URL.
     */
    public function test_provision_callback_requires_signed_url(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act - POST without signed URL
        $response = $this->postJson("/servers/{$server->id}/provision/step", [
            'step' => 1,
            'status' => 'pending',
        ]);

        // Assert - should fail because URL is not signed
        $response->assertStatus(403);
    }

    /**
     * Test valid step 1 pending status update.
     */
    public function test_updates_step_1_pending_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision' => collect(),
        ]);

        $signedUrl = URL::signedRoute('servers.provision.step', [
            'server' => $server->id,
            'step' => 1,
            'status' => 'pending',
        ]);

        // Act
        $response = $this->postJson($signedUrl);

        // Assert
        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);

        $server->refresh();
        $this->assertEquals('pending', $server->provision->get(1));
    }

    /**
     * Test valid step 2 installing status update.
     */
    public function test_updates_step_2_installing_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision' => collect([1 => 'success']),
        ]);

        $signedUrl = URL::signedRoute('servers.provision.step', [
            'server' => $server->id,
            'step' => 2,
            'status' => 'installing',
        ]);

        // Act
        $response = $this->postJson($signedUrl);

        // Assert
        $response->assertStatus(200);
        $server->refresh();
        $this->assertEquals('installing', $server->provision->get(2));
    }

    /**
     * Test valid step 3 installing status update.
     */
    public function test_updates_step_3_installing_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision' => collect([1 => 'success', 2 => 'success']),
        ]);

        $signedUrl = URL::signedRoute('servers.provision.step', [
            'server' => $server->id,
            'step' => 3,
            'status' => 'installing',
        ]);

        // Act
        $response = $this->postJson($signedUrl);

        // Assert
        $response->assertStatus(200);
        $server->refresh();
        $this->assertEquals('installing', $server->provision->get(3));
    }

    /**
     * Test invalid step number is rejected.
     */
    public function test_rejects_invalid_step_number(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $signedUrl = URL::signedRoute('servers.provision.step', [
            'server' => $server->id,
            'step' => 99,
            'status' => 'pending',
        ]);

        // Act
        $response = $this->postJson($signedUrl);

        // Assert - should return 400 bad request
        $response->assertStatus(400);
    }

    /**
     * Test step number 0 is rejected.
     */
    public function test_rejects_step_zero(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $signedUrl = URL::signedRoute('servers.provision.step', [
            'server' => $server->id,
            'step' => 0,
            'status' => 'pending',
        ]);

        // Act
        $response = $this->postJson($signedUrl);

        // Assert
        $response->assertStatus(400);
    }

    /**
     * Test step number 4 is rejected.
     */
    public function test_rejects_step_four(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $signedUrl = URL::signedRoute('servers.provision.step', [
            'server' => $server->id,
            'step' => 4,
            'status' => 'pending',
        ]);

        // Act
        $response = $this->postJson($signedUrl);

        // Assert
        $response->assertStatus(400);
    }

    /**
     * Test invalid status is rejected.
     */
    public function test_rejects_invalid_status(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $signedUrl = URL::signedRoute('servers.provision.step', [
            'server' => $server->id,
            'step' => 1,
            'status' => 'invalid-status',
        ]);

        // Act
        $response = $this->postJson($signedUrl);

        // Assert
        $response->assertStatus(400);
    }

    /**
     * Test failed status marks provisioning as failed.
     */
    public function test_failed_status_marks_provisioning_as_failed(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision_status' => TaskStatus::Installing,
        ]);

        $signedUrl = URL::signedRoute('servers.provision.step', [
            'server' => $server->id,
            'step' => 2,
            'status' => 'failed',
        ]);

        // Act
        $response = $this->postJson($signedUrl);

        // Assert
        $response->assertStatus(200);
        $server->refresh();
        $this->assertEquals(TaskStatus::Failed, $server->provision_status);
        $this->assertEquals('failed', $server->provision->get(2));
    }

    /**
     * Test step 1 completion clears old server data.
     */
    public function test_step_1_completion_clears_old_server_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Create old data that should be cleared
        ServerDatabase::factory()->create(['server_id' => $server->id]);
        ServerPhp::factory()->create(['server_id' => $server->id]);
        ServerReverseProxy::factory()->create(['server_id' => $server->id]);

        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        $signedUrl = URL::signedRoute('servers.provision.step', [
            'server' => $server->id,
            'step' => 1,
            'status' => 'success',
        ]);

        // Act
        $response = $this->postJson($signedUrl);

        // Assert
        $response->assertStatus(200);

        $server->refresh();

        // Verify old data was cleared
        $this->assertEquals(0, $server->databases()->count());
        $this->assertEquals(0, $server->phps()->count());
        $this->assertNull($server->reverseProxy);
        $this->assertNull($server->firewall);

        // Verify server status updated
        $this->assertEquals(TaskStatus::Success, $server->connection_status);
        $this->assertEquals(TaskStatus::Installing, $server->provision_status);
        $this->assertEquals('success', $server->provision->get(1));
    }

    /**
     * Test step 1 completion resets provision data.
     */
    public function test_step_1_completion_resets_provision_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision' => collect([
                1 => 'failed',
                2 => 'pending',
                3 => 'pending',
            ]),
        ]);

        $signedUrl = URL::signedRoute('servers.provision.step', [
            'server' => $server->id,
            'step' => 1,
            'status' => 'success',
        ]);

        // Act
        $response = $this->postJson($signedUrl);

        // Assert
        $response->assertStatus(200);
        $server->refresh();

        // Provision should only have step 1 completed
        $this->assertEquals(1, $server->provision->count());
        $this->assertEquals('success', $server->provision->get(1));
    }

    /**
     * Test provision updates are logged.
     */
    public function test_provision_updates_are_logged(): void
    {
        // Arrange
        Log::shouldReceive('info')
            ->once()
            ->with(Mockery::pattern('/Provision step 1 updated to pending for server #\d+/'));

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $signedUrl = URL::signedRoute('servers.provision.step', [
            'server' => $server->id,
            'step' => 1,
            'status' => 'pending',
        ]);

        // Act
        $response = $this->postJson($signedUrl);

        // Assert
        $response->assertStatus(200);
    }

    /**
     * Test failed provision is logged as error.
     */
    public function test_failed_provision_is_logged_as_error(): void
    {
        // Arrange
        Log::shouldReceive('info')
            ->once()
            ->with(Mockery::pattern('/Provision step 1 updated to failed/'));

        Log::shouldReceive('error')
            ->once()
            ->with(Mockery::pattern('/Provision step 1 failed for server #\d+/'));

        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $signedUrl = URL::signedRoute('servers.provision.step', [
            'server' => $server->id,
            'step' => 1,
            'status' => 'failed',
        ]);

        // Act
        $response = $this->postJson($signedUrl);

        // Assert
        $response->assertStatus(200);
    }

    /**
     * Test all valid status values are accepted.
     */
    public function test_accepts_all_valid_status_values(): void
    {
        // Arrange
        $user = User::factory()->create();
        // Use step 2 to avoid step 1 and 3 completion side effects
        $validStatuses = ['pending', 'installing', 'failed'];

        foreach ($validStatuses as $status) {
            $server = Server::factory()->create(['user_id' => $user->id]);

            $signedUrl = URL::signedRoute('servers.provision.step', [
                'server' => $server->id,
                'step' => 2,
                'status' => $status,
            ]);

            // Act
            $response = $this->postJson($signedUrl);

            // Assert
            $response->assertStatus(200);
            $server->refresh();
            $this->assertEquals($status, $server->provision->get(2));
        }
    }

    /**
     * Test step parameters can come from query string.
     */
    public function test_accepts_step_parameters_from_query_string(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $signedUrl = URL::signedRoute('servers.provision.step', [
            'server' => $server->id,
            'step' => 1,
            'status' => 'pending',
        ]);

        // Act
        $response = $this->postJson($signedUrl);

        // Assert
        $response->assertStatus(200);
        $server->refresh();
        $this->assertEquals('pending', $server->provision->get(1));
    }

    /**
     * Test step parameters can come from POST body.
     */
    public function test_accepts_step_parameters_from_post_body(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $signedUrl = URL::signedRoute('servers.provision.step', [
            'server' => $server->id,
        ]);

        // Act - send step and status in POST body
        $response = $this->postJson($signedUrl, [
            'step' => 1,
            'status' => 'pending',
        ]);

        // Assert
        $response->assertStatus(200);
        $server->refresh();
        $this->assertEquals('pending', $server->provision->get(1));
    }

    /**
     * Test JSON response structure.
     */
    public function test_returns_correct_json_response_structure(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        $signedUrl = URL::signedRoute('servers.provision.step', [
            'server' => $server->id,
            'step' => 1,
            'status' => 'pending',
        ]);

        // Act
        $response = $this->postJson($signedUrl);

        // Assert
        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
        $response->assertJsonStructure(['ok']);
    }

    /**
     * Test step 3 success completion triggers next provision steps.
     */
    public function test_step_3_success_completion_triggers_next_provision_steps(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision' => collect([
                1 => 'success',
                2 => 'success',
                3 => 'installing',
            ]),
            'provision_status' => TaskStatus::Installing,
        ]);

        // Create credentials for SSH
        \App\Models\ServerCredential::factory()->create([
            'server_id' => $server->id,
            'user' => 'root',
        ]);
        \App\Models\ServerCredential::factory()->create([
            'server_id' => $server->id,
            'user' => 'brokeforge',
        ]);

        // Create a partial mock of the server
        $mockServer = Mockery::mock($server)->makePartial()->shouldAllowMockingProtectedMethods();
        $mockServer->id = $server->id;

        // Mock SSH to return successful results
        $mockSshRoot = Mockery::mock(\Spatie\Ssh\Ssh::class);
        $mockProcessRoot = Mockery::mock(\Symfony\Component\Process\Process::class);
        $mockProcessRoot->shouldReceive('getOutput')->andReturn('root');
        $mockProcessRoot->shouldReceive('getErrorOutput')->andReturn('');
        $mockProcessRoot->shouldReceive('getExitCode')->andReturn(0);
        $mockSshRoot->shouldReceive('execute')->with('whoami')->andReturn($mockProcessRoot);

        $mockSshBrokeforge = Mockery::mock(\Spatie\Ssh\Ssh::class);
        $mockProcessBrokeforge = Mockery::mock(\Symfony\Component\Process\Process::class);
        $mockProcessBrokeforge->shouldReceive('getOutput')->andReturn('brokeforge');
        $mockProcessBrokeforge->shouldReceive('getErrorOutput')->andReturn('');
        $mockProcessBrokeforge->shouldReceive('getExitCode')->andReturn(0);
        $mockSshBrokeforge->shouldReceive('execute')->with('whoami')->andReturn($mockProcessBrokeforge);

        $mockServer->shouldReceive('ssh')->with('root')->andReturn($mockSshRoot);
        $mockServer->shouldReceive('ssh')->with('brokeforge')->andReturn($mockSshBrokeforge);

        $mockServer->shouldReceive('detectOsInfo')->once()->andReturn(true);

        // Override route model binding to use our mock
        \Illuminate\Support\Facades\Route::bind('server', function ($value) use ($mockServer, $server) {
            if ($value == $server->id) {
                return $mockServer;
            }

            return Server::findOrFail($value);
        });

        $signedUrl = URL::signedRoute('servers.provision.step', [
            'server' => $server->id,
            'step' => 3,
            'status' => 'success',
        ]);

        // Act
        $response = $this->postJson($signedUrl);

        // Assert
        $response->assertStatus(200);

        $server->refresh();
        $this->assertEquals('success', $server->provision->get(4));
        $this->assertEquals('installing', $server->provision->get(5));
        $this->assertEquals(TaskStatus::Installing, $server->provision_status);
    }

    /**
     * Test step 3 success updates status to installing.
     */
    public function test_step_3_success_updates_status_to_installing(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision' => collect([1 => 'success', 2 => 'success']),
            'provision_status' => TaskStatus::Pending,
        ]);

        // Create credentials
        \App\Models\ServerCredential::factory()->create([
            'server_id' => $server->id,
            'user' => 'root',
        ]);
        \App\Models\ServerCredential::factory()->create([
            'server_id' => $server->id,
            'user' => 'brokeforge',
        ]);

        // Mock successful SSH
        $mockServer = Mockery::mock($server)->makePartial()->shouldAllowMockingProtectedMethods();
        $mockServer->id = $server->id;

        $mockSshRoot = Mockery::mock(\Spatie\Ssh\Ssh::class);
        $mockProcessRoot = Mockery::mock(\Symfony\Component\Process\Process::class);
        $mockProcessRoot->shouldReceive('getOutput')->andReturn('root');
        $mockProcessRoot->shouldReceive('getErrorOutput')->andReturn('');
        $mockProcessRoot->shouldReceive('getExitCode')->andReturn(0);
        $mockSshRoot->shouldReceive('execute')->with('whoami')->andReturn($mockProcessRoot);

        $mockSshBrokeforge = Mockery::mock(\Spatie\Ssh\Ssh::class);
        $mockProcessBrokeforge = Mockery::mock(\Symfony\Component\Process\Process::class);
        $mockProcessBrokeforge->shouldReceive('getOutput')->andReturn('brokeforge');
        $mockProcessBrokeforge->shouldReceive('getErrorOutput')->andReturn('');
        $mockProcessBrokeforge->shouldReceive('getExitCode')->andReturn(0);
        $mockSshBrokeforge->shouldReceive('execute')->with('whoami')->andReturn($mockProcessBrokeforge);

        $mockServer->shouldReceive('ssh')->with('root')->andReturn($mockSshRoot);
        $mockServer->shouldReceive('ssh')->with('brokeforge')->andReturn($mockSshBrokeforge);
        $mockServer->shouldReceive('detectOsInfo')->once()->andReturn(true);

        // Override route model binding
        \Illuminate\Support\Facades\Route::bind('server', function ($value) use ($mockServer, $server) {
            if ($value == $server->id) {
                return $mockServer;
            }

            return Server::findOrFail($value);
        });

        $signedUrl = URL::signedRoute('servers.provision.step', [
            'server' => $server->id,
            'step' => 3,
            'status' => 'success',
        ]);

        // Act
        $response = $this->postJson($signedUrl);

        // Assert
        $response->assertStatus(200);
        $server->refresh();
        $this->assertEquals(TaskStatus::Installing, $server->provision_status);
    }

    /**
     * Test step 3 success with success status value.
     */
    public function test_step_3_accepts_success_status(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision' => collect([1 => 'success', 2 => 'success']),
        ]);

        // Create credentials
        \App\Models\ServerCredential::factory()->create([
            'server_id' => $server->id,
            'user' => 'root',
        ]);
        \App\Models\ServerCredential::factory()->create([
            'server_id' => $server->id,
            'user' => 'brokeforge',
        ]);

        // Mock successful SSH
        $mockServer = Mockery::mock($server)->makePartial()->shouldAllowMockingProtectedMethods();
        $mockServer->id = $server->id;

        $mockSshRoot = Mockery::mock(\Spatie\Ssh\Ssh::class);
        $mockProcessRoot = Mockery::mock(\Symfony\Component\Process\Process::class);
        $mockProcessRoot->shouldReceive('getOutput')->andReturn('root');
        $mockProcessRoot->shouldReceive('getErrorOutput')->andReturn('');
        $mockProcessRoot->shouldReceive('getExitCode')->andReturn(0);
        $mockSshRoot->shouldReceive('execute')->with('whoami')->andReturn($mockProcessRoot);

        $mockSshBrokeforge = Mockery::mock(\Spatie\Ssh\Ssh::class);
        $mockProcessBrokeforge = Mockery::mock(\Symfony\Component\Process\Process::class);
        $mockProcessBrokeforge->shouldReceive('getOutput')->andReturn('brokeforge');
        $mockProcessBrokeforge->shouldReceive('getErrorOutput')->andReturn('');
        $mockProcessBrokeforge->shouldReceive('getExitCode')->andReturn(0);
        $mockSshBrokeforge->shouldReceive('execute')->with('whoami')->andReturn($mockProcessBrokeforge);

        $mockServer->shouldReceive('ssh')->with('root')->andReturn($mockSshRoot);
        $mockServer->shouldReceive('ssh')->with('brokeforge')->andReturn($mockSshBrokeforge);
        $mockServer->shouldReceive('detectOsInfo')->once()->andReturn(true);

        // Override route model binding
        \Illuminate\Support\Facades\Route::bind('server', function ($value) use ($mockServer, $server) {
            if ($value == $server->id) {
                return $mockServer;
            }

            return Server::findOrFail($value);
        });

        $signedUrl = URL::signedRoute('servers.provision.step', [
            'server' => $server->id,
            'step' => 3,
            'status' => 'success',
        ]);

        // Act
        $response = $this->postJson($signedUrl);

        // Assert - Should accept 'success' (not 'completed')
        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
    }

    /**
     * Test step 1 success clears provision and sets connection status.
     */
    public function test_step_1_success_sets_connection_status_to_success(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'connection_status' => TaskStatus::Pending,
            'provision_status' => TaskStatus::Pending,
        ]);

        $signedUrl = URL::signedRoute('servers.provision.step', [
            'server' => $server->id,
            'step' => 1,
            'status' => 'success',
        ]);

        // Act
        $response = $this->postJson($signedUrl);

        // Assert
        $response->assertStatus(200);
        $server->refresh();
        $this->assertEquals(TaskStatus::Success, $server->connection_status);
        $this->assertEquals(TaskStatus::Installing, $server->provision_status);
    }

    /**
     * Test step 4 status updates via callback.
     */
    public function test_step_4_installing_status_is_set_by_step_3_success(): void
    {
        // Arrange
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'provision' => collect([1 => 'success', 2 => 'success']),
        ]);

        // Create credentials
        \App\Models\ServerCredential::factory()->create([
            'server_id' => $server->id,
            'user' => 'root',
        ]);
        \App\Models\ServerCredential::factory()->create([
            'server_id' => $server->id,
            'user' => 'brokeforge',
        ]);

        // Mock successful SSH
        $mockServer = Mockery::mock($server)->makePartial()->shouldAllowMockingProtectedMethods();
        $mockServer->id = $server->id;

        $mockSshRoot = Mockery::mock(\Spatie\Ssh\Ssh::class);
        $mockProcessRoot = Mockery::mock(\Symfony\Component\Process\Process::class);
        $mockProcessRoot->shouldReceive('getOutput')->andReturn('root');
        $mockProcessRoot->shouldReceive('getErrorOutput')->andReturn('');
        $mockProcessRoot->shouldReceive('getExitCode')->andReturn(0);
        $mockSshRoot->shouldReceive('execute')->with('whoami')->andReturn($mockProcessRoot);

        $mockSshBrokeforge = Mockery::mock(\Spatie\Ssh\Ssh::class);
        $mockProcessBrokeforge = Mockery::mock(\Symfony\Component\Process\Process::class);
        $mockProcessBrokeforge->shouldReceive('getOutput')->andReturn('brokeforge');
        $mockProcessBrokeforge->shouldReceive('getErrorOutput')->andReturn('');
        $mockProcessBrokeforge->shouldReceive('getExitCode')->andReturn(0);
        $mockSshBrokeforge->shouldReceive('execute')->with('whoami')->andReturn($mockProcessBrokeforge);

        $mockServer->shouldReceive('ssh')->with('root')->andReturn($mockSshRoot);
        $mockServer->shouldReceive('ssh')->with('brokeforge')->andReturn($mockSshBrokeforge);
        $mockServer->shouldReceive('detectOsInfo')->once()->andReturn(true);

        // Override route model binding
        \Illuminate\Support\Facades\Route::bind('server', function ($value) use ($mockServer, $server) {
            if ($value == $server->id) {
                return $mockServer;
            }

            return Server::findOrFail($value);
        });

        $signedUrl = URL::signedRoute('servers.provision.step', [
            'server' => $server->id,
            'step' => 3,
            'status' => 'success',
        ]);

        // Act
        $response = $this->postJson($signedUrl);

        // Assert
        $response->assertStatus(200);
        $server->refresh();

        // Step 4 should be set to 'success' and step 5 to 'installing' after step 3 completes
        $this->assertEquals('success', $server->provision->get(4));
        $this->assertEquals('installing', $server->provision->get(5));
    }
}
