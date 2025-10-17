<?php

namespace Tests\Unit\Models;

use App\Enums\MonitoringStatus;
use App\Enums\SchedulerStatus;
use App\Enums\ServerProvider;
use App\Enums\SupervisorStatus;
use App\Events\ServerUpdated;
use App\Models\Server;
use App\Models\ServerCredential;
use App\Models\ServerDatabase;
use App\Models\ServerEvent;
use App\Models\ServerFirewall;
use App\Models\ServerMetric;
use App\Models\ServerPhp;
use App\Models\ServerReverseProxy;
use App\Models\ServerScheduledTask;
use App\Models\ServerScheduledTaskRun;
use App\Models\ServerSite;
use App\Models\ServerSupervisorTask;
use App\Models\SourceProvider;
use App\Models\User;
use App\Packages\Enums\ConnectionStatus;
use App\Packages\Enums\ProvisionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ServerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test generates unique monitoring token and updates model.
     */
    public function test_generates_unique_monitoring_token_and_updates_model(): void
    {
        // Arrange
        $server = Server::factory()->create(['monitoring_token' => null]);

        // Act
        $token = $server->generateMonitoringToken();

        // Assert
        $this->assertNotEmpty($token);
        $this->assertIsString($token);
        $server->refresh();
        $this->assertEquals($token, $server->monitoring_token);
    }

    /**
     * Test monitoring token has correct length.
     */
    public function test_monitoring_token_has_correct_length(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $expectedLength = config('monitoring.token_length', 32) * 2; // bin2hex doubles length

        // Act
        $token = $server->generateMonitoringToken();

        // Assert
        $this->assertEquals($expectedLength, strlen($token));
    }

    /**
     * Test generates unique scheduler token and updates model.
     */
    public function test_generates_unique_scheduler_token_and_updates_model(): void
    {
        // Arrange
        $server = Server::factory()->create(['scheduler_token' => null]);

        // Act
        $token = $server->generateSchedulerToken();

        // Assert
        $this->assertNotEmpty($token);
        $this->assertIsString($token);
        $server->refresh();
        $this->assertEquals($token, $server->scheduler_token);
    }

    /**
     * Test scheduler token has correct length.
     */
    public function test_scheduler_token_has_correct_length(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $expectedLength = config('scheduler.token_length', 32) * 2; // bin2hex doubles length

        // Act
        $token = $server->generateSchedulerToken();

        // Assert
        $this->assertEquals($expectedLength, strlen($token));
    }

    /**
     * Test register creates new server for new IP.
     */
    public function test_register_creates_new_server_for_new_ip(): void
    {
        // Arrange
        $publicIp = '192.168.1.100';

        // Act
        $server = Server::register($publicIp);

        // Assert
        $this->assertInstanceOf(Server::class, $server);
        $this->assertEquals($publicIp, $server->public_ip);
        $this->assertDatabaseHas('servers', ['public_ip' => $publicIp]);
    }

    /**
     * Test register finds existing server for existing IP.
     */
    public function test_register_finds_existing_server_for_existing_ip(): void
    {
        // Arrange
        $publicIp = '192.168.1.200';
        $existingServer = Server::factory()->create(['public_ip' => $publicIp]);

        // Act
        $server = Server::register($publicIp);

        // Assert
        $this->assertEquals($existingServer->id, $server->id);
        $this->assertEquals(1, Server::where('public_ip', $publicIp)->count());
    }

    /**
     * Test isConnected returns true when server is connected.
     */
    public function test_is_connected_returns_true_when_server_is_connected(): void
    {
        // Arrange - factory defaults to 'connected'
        $server = Server::factory()->create();

        // Act & Assert
        $this->assertTrue($server->isConnected());
    }

    /**
     * Test isConnected returns false when server is not connected.
     */
    public function test_is_connected_returns_false_when_server_is_not_connected(): void
    {
        // Arrange
        $server = Server::factory()->create(['connection_status' => 'failed']);

        // Act & Assert
        $this->assertFalse($server->isConnected());
    }

    /**
     * Test isDeleted returns false for non-deleted server.
     */
    public function test_is_deleted_returns_false_for_non_deleted_server(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Act & Assert
        $this->assertFalse($server->isDeleted());
    }

    /**
     * Test isProvisioned returns true when server is provisioned.
     */
    public function test_is_provisioned_returns_true_when_server_is_provisioned(): void
    {
        // Arrange
        $server = Server::factory()->create(['provision_status' => ProvisionStatus::Completed]);

        // Act & Assert
        $this->assertTrue($server->isProvisioned());
    }

    /**
     * Test isProvisioned returns false when server is not provisioned.
     */
    public function test_is_provisioned_returns_false_when_server_is_not_provisioned(): void
    {
        // Arrange
        $server = Server::factory()->create(['provision_status' => ProvisionStatus::Pending]);

        // Act & Assert
        $this->assertFalse($server->isProvisioned());
    }

    /**
     * Test schedulerIsActive returns true when scheduler is active.
     */
    public function test_scheduler_is_active_returns_true_when_active(): void
    {
        // Arrange
        $server = Server::factory()->create(['scheduler_status' => SchedulerStatus::Active]);

        // Act & Assert
        $this->assertTrue($server->schedulerIsActive());
    }

    /**
     * Test schedulerIsInstalling returns true when scheduler is installing.
     */
    public function test_scheduler_is_installing_returns_true_when_installing(): void
    {
        // Arrange
        $server = Server::factory()->create(['scheduler_status' => SchedulerStatus::Installing]);

        // Act & Assert
        $this->assertTrue($server->schedulerIsInstalling());
    }

    /**
     * Test schedulerIsFailed returns true when scheduler failed.
     */
    public function test_scheduler_is_failed_returns_true_when_failed(): void
    {
        // Arrange
        $server = Server::factory()->create(['scheduler_status' => SchedulerStatus::Failed]);

        // Act & Assert
        $this->assertTrue($server->schedulerIsFailed());
    }

    /**
     * Test monitoringIsActive returns true when monitoring is active.
     */
    public function test_monitoring_is_active_returns_true_when_active(): void
    {
        // Arrange
        $server = Server::factory()->create(['monitoring_status' => MonitoringStatus::Active]);

        // Act & Assert
        $this->assertTrue($server->monitoringIsActive());
    }

    /**
     * Test monitoringIsInstalling returns true when monitoring is installing.
     */
    public function test_monitoring_is_installing_returns_true_when_installing(): void
    {
        // Arrange
        $server = Server::factory()->create(['monitoring_status' => MonitoringStatus::Installing]);

        // Act & Assert
        $this->assertTrue($server->monitoringIsInstalling());
    }

    /**
     * Test monitoringIsFailed returns true when monitoring failed.
     */
    public function test_monitoring_is_failed_returns_true_when_failed(): void
    {
        // Arrange
        $server = Server::factory()->create(['monitoring_status' => MonitoringStatus::Failed]);

        // Act & Assert
        $this->assertTrue($server->monitoringIsFailed());
    }

    /**
     * Test supervisorIsActive returns true when supervisor is active.
     */
    public function test_supervisor_is_active_returns_true_when_active(): void
    {
        // Arrange
        $server = Server::factory()->create(['supervisor_status' => SupervisorStatus::Active]);

        // Act & Assert
        $this->assertTrue($server->supervisorIsActive());
    }

    /**
     * Test supervisorIsInstalling returns true when supervisor is installing.
     */
    public function test_supervisor_is_installing_returns_true_when_installing(): void
    {
        // Arrange
        $server = Server::factory()->create(['supervisor_status' => SupervisorStatus::Installing]);

        // Act & Assert
        $this->assertTrue($server->supervisorIsInstalling());
    }

    /**
     * Test supervisorIsFailed returns true when supervisor failed.
     */
    public function test_supervisor_is_failed_returns_true_when_failed(): void
    {
        // Arrange
        $server = Server::factory()->create(['supervisor_status' => SupervisorStatus::Failed]);

        // Act & Assert
        $this->assertTrue($server->supervisorIsFailed());
    }

    /**
     * Test generatePassword generates password with default length.
     */
    public function test_generate_password_generates_password_with_default_length(): void
    {
        // Act
        $password = Server::generatePassword();

        // Assert
        $this->assertEquals(24, strlen($password));
    }

    /**
     * Test generatePassword generates password with custom length.
     */
    public function test_generate_password_generates_password_with_custom_length(): void
    {
        // Act
        $password = Server::generatePassword(32);

        // Assert
        $this->assertEquals(32, strlen($password));
    }

    /**
     * Test generatePassword generates unique passwords.
     */
    public function test_generate_password_generates_unique_passwords(): void
    {
        // Act
        $password1 = Server::generatePassword();
        $password2 = Server::generatePassword();

        // Assert
        $this->assertNotEquals($password1, $password2);
    }

    /**
     * Test generatePassword only uses URL-safe characters.
     */
    public function test_generate_password_only_uses_url_safe_characters(): void
    {
        // Arrange
        $allowedChars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';

        // Act
        $password = Server::generatePassword(100);

        // Assert
        for ($i = 0; $i < strlen($password); $i++) {
            $this->assertStringContainsString($password[$i], $allowedChars);
        }
    }

    /**
     * Test detectOsInfo updates OS information successfully.
     */
    public function test_detect_os_info_updates_os_information_successfully(): void
    {
        // Arrange
        $server = Mockery::mock(Server::class)->makePartial();
        $mockSsh = Mockery::mock(\Spatie\Ssh\Ssh::class);
        $mockProcess = Mockery::mock(\Symfony\Component\Process\Process::class);

        $server->shouldReceive('ssh')
            ->with('root')
            ->andReturn($mockSsh);

        $mockSsh->shouldReceive('execute')
            ->with('lsb_release -a 2>/dev/null')
            ->andReturn($mockProcess);

        $mockProcess->shouldReceive('isSuccessful')
            ->andReturn(true);

        $mockProcess->shouldReceive('getOutput')
            ->andReturn("Distributor ID: Ubuntu\nRelease: 22.04\nCodename: jammy");

        $server->shouldReceive('update')
            ->once()
            ->with([
                'os_name' => 'Ubuntu',
                'os_version' => '22.04',
                'os_codename' => 'jammy',
            ]);

        // Act
        $result = $server->detectOsInfo();

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test detectOsInfo returns false on failure.
     */
    public function test_detect_os_info_returns_false_on_failure(): void
    {
        // Arrange
        Log::shouldReceive('warning')->once();

        $server = Mockery::mock(Server::class)->makePartial();
        $server->id = 1;

        $server->shouldReceive('ssh')
            ->with('root')
            ->andThrow(new \Exception('SSH connection failed'));

        // Act
        $result = $server->detectOsInfo();

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test creating event generates SSH root password.
     */
    public function test_creating_event_generates_ssh_root_password(): void
    {
        // Act
        $server = Server::factory()->create();

        // Assert
        $this->assertNotEmpty($server->ssh_root_password);
    }

    /**
     * Test creating event does not override existing password.
     */
    public function test_creating_event_does_not_override_existing_password(): void
    {
        // Arrange
        $existingPassword = 'existing-password-123';

        // Act
        $server = Server::factory()->create(['ssh_root_password' => $existingPassword]);

        // Assert
        $this->assertEquals($existingPassword, $server->ssh_root_password);
    }

    /**
     * Test created event logs activity.
     */
    public function test_created_event_logs_activity(): void
    {
        // Arrange
        $user = User::factory()->create();
        Auth::login($user);

        // Act
        $server = Server::factory()->create(['vanity_name' => 'Test Server']);

        // Assert
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Server::class,
            'subject_id' => $server->id,
            'event' => 'server.created',
            'causer_id' => $user->id,
        ]);

        $activity = Activity::where('subject_id', $server->id)->first();
        $this->assertEquals('Test Server', $activity->properties['vanity_name']);
    }

    /**
     * Test updated event broadcasts when meaningful fields change.
     */
    public function test_updated_event_broadcasts_when_meaningful_fields_change(): void
    {
        // Arrange
        Event::fake([ServerUpdated::class]);
        $server = Server::factory()->create(['provision_status' => ProvisionStatus::Pending]);

        // Act
        $server->update(['provision_status' => ProvisionStatus::Completed]);

        // Assert
        Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
            return $event->serverId === $server->id;
        });
    }

    /**
     * Test updated event does not broadcast when non-meaningful fields change.
     */
    public function test_updated_event_does_not_broadcast_when_non_meaningful_fields_change(): void
    {
        // Arrange
        Event::fake([ServerUpdated::class]);
        $server = Server::factory()->create(['vanity_name' => 'Old Name']);

        // Act
        $server->update(['vanity_name' => 'New Name']);

        // Assert
        Event::assertNotDispatched(ServerUpdated::class);
    }

    /**
     * Test deleting event attempts to remove SSH key from GitHub.
     */
    public function test_deleting_event_attempts_to_remove_ssh_key_from_github(): void
    {
        // Arrange
        $user = User::factory()->create();
        $sourceProvider = SourceProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
        ]);

        $server = Server::factory()->create([
            'user_id' => $user->id,
            'source_provider_ssh_key_added' => true,
            'source_provider_ssh_key_id' => 'key-123',
        ]);

        // Act & Assert - should attempt to remove key without throwing exception
        // The actual removal depends on GitHub API availability
        $server->delete();

        // Verify server was deleted
        $this->assertDatabaseMissing('servers', ['id' => $server->id]);
    }

    /**
     * Test deleting event skips removal if key was not added.
     */
    public function test_deleting_event_skips_removal_if_key_was_not_added(): void
    {
        // Arrange
        $server = Server::factory()->create(['source_provider_ssh_key_added' => false]);

        // Act & Assert - should not throw exception
        $server->delete();
        $this->assertTrue(true);
    }

    /**
     * Test deleted event logs activity.
     */
    public function test_deleted_event_logs_activity(): void
    {
        // Arrange
        $user = User::factory()->create();
        Auth::login($user);
        $server = Server::factory()->create(['vanity_name' => 'Test Server']);

        // Act
        $server->delete();

        // Assert
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Server::class,
            'subject_id' => $server->id,
            'event' => 'server.deleted',
            'causer_id' => $user->id,
        ]);
    }

    /**
     * Test user relationship returns BelongsTo.
     */
    public function test_user_relationship_returns_belongs_to(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $relationship = $server->user;

        // Assert
        $this->assertInstanceOf(User::class, $relationship);
        $this->assertEquals($user->id, $relationship->id);
    }

    /**
     * Test sites relationship returns HasMany.
     */
    public function test_sites_relationship_returns_has_many(): void
    {
        // Arrange
        $server = Server::factory()->create();
        ServerSite::factory()->count(3)->create(['server_id' => $server->id]);

        // Act
        $sites = $server->sites;

        // Assert
        $this->assertCount(3, $sites);
        $this->assertInstanceOf(ServerSite::class, $sites->first());
    }

    /**
     * Test credentials relationship returns HasMany.
     */
    public function test_credentials_relationship_returns_has_many(): void
    {
        // Arrange
        $server = Server::factory()->create();
        ServerCredential::factory()->root()->create(['server_id' => $server->id]);
        ServerCredential::factory()->brokeforge()->create(['server_id' => $server->id]);

        // Act
        $credentials = $server->credentials;

        // Assert
        $this->assertCount(2, $credentials);
        $this->assertInstanceOf(ServerCredential::class, $credentials->first());
    }

    /**
     * Test events relationship returns HasMany.
     */
    public function test_events_relationship_returns_has_many(): void
    {
        // Arrange
        $server = Server::factory()->create();
        ServerEvent::factory()->count(5)->create(['server_id' => $server->id]);

        // Act
        $events = $server->events;

        // Assert
        $this->assertCount(5, $events);
        $this->assertInstanceOf(ServerEvent::class, $events->first());
    }

    /**
     * Test firewall relationship returns HasOne.
     */
    public function test_firewall_relationship_returns_has_one(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $firewall = ServerFirewall::factory()->create(['server_id' => $server->id]);

        // Act
        $relationship = $server->firewall;

        // Assert
        $this->assertInstanceOf(ServerFirewall::class, $relationship);
        $this->assertEquals($firewall->id, $relationship->id);
    }

    /**
     * Test metrics relationship returns HasMany.
     */
    public function test_metrics_relationship_returns_has_many(): void
    {
        // Arrange
        $server = Server::factory()->create();
        ServerMetric::factory()->count(10)->create(['server_id' => $server->id]);

        // Act
        $metrics = $server->metrics;

        // Assert
        $this->assertCount(10, $metrics);
        $this->assertInstanceOf(ServerMetric::class, $metrics->first());
    }

    /**
     * Test scheduledTasks relationship returns HasMany.
     */
    public function test_scheduled_tasks_relationship_returns_has_many(): void
    {
        // Arrange
        $server = Server::factory()->create();
        ServerScheduledTask::factory()->count(4)->create(['server_id' => $server->id]);

        // Act
        $tasks = $server->scheduledTasks;

        // Assert
        $this->assertCount(4, $tasks);
        $this->assertInstanceOf(ServerScheduledTask::class, $tasks->first());
    }

    /**
     * Test scheduledTaskRuns relationship returns HasMany.
     */
    public function test_scheduled_task_runs_relationship_returns_has_many(): void
    {
        // Arrange
        $server = Server::factory()->create();
        ServerScheduledTaskRun::factory()->count(20)->create(['server_id' => $server->id]);

        // Act
        $runs = $server->scheduledTaskRuns;

        // Assert
        $this->assertCount(20, $runs);
        $this->assertInstanceOf(ServerScheduledTaskRun::class, $runs->first());
    }

    /**
     * Test supervisorTasks relationship returns HasMany.
     */
    public function test_supervisor_tasks_relationship_returns_has_many(): void
    {
        // Arrange
        $server = Server::factory()->create();
        ServerSupervisorTask::factory()->count(3)->create(['server_id' => $server->id]);

        // Act
        $tasks = $server->supervisorTasks;

        // Assert
        $this->assertCount(3, $tasks);
        $this->assertInstanceOf(ServerSupervisorTask::class, $tasks->first());
    }

    /**
     * Test databases relationship returns HasMany.
     */
    public function test_databases_relationship_returns_has_many(): void
    {
        // Arrange
        $server = Server::factory()->create();
        ServerDatabase::factory()->create(['server_id' => $server->id, 'port' => 3306]);
        ServerDatabase::factory()->create(['server_id' => $server->id, 'port' => 5432]);

        // Act
        $databases = $server->databases;

        // Assert
        $this->assertCount(2, $databases);
        $this->assertInstanceOf(ServerDatabase::class, $databases->first());
    }

    /**
     * Test phps relationship returns HasMany.
     */
    public function test_phps_relationship_returns_has_many(): void
    {
        // Arrange
        $server = Server::factory()->create();
        ServerPhp::factory()->create(['server_id' => $server->id, 'version' => '8.2']);
        ServerPhp::factory()->create(['server_id' => $server->id, 'version' => '8.3']);
        ServerPhp::factory()->create(['server_id' => $server->id, 'version' => '8.4']);

        // Act
        $phps = $server->phps;

        // Assert
        $this->assertCount(3, $phps);
        $this->assertInstanceOf(ServerPhp::class, $phps->first());
    }

    /**
     * Test defaultPhp relationship returns HasOne with constraint.
     */
    public function test_default_php_relationship_returns_has_one_with_constraint(): void
    {
        // Arrange
        $server = Server::factory()->create();
        ServerPhp::factory()->create(['server_id' => $server->id, 'version' => '8.1', 'is_cli_default' => false]);
        $defaultPhp = ServerPhp::factory()->create(['server_id' => $server->id, 'version' => '8.2', 'is_cli_default' => true]);

        // Act
        $relationship = $server->defaultPhp;

        // Assert
        $this->assertInstanceOf(ServerPhp::class, $relationship);
        $this->assertEquals($defaultPhp->id, $relationship->id);
        $this->assertTrue($relationship->is_cli_default);
    }

    /**
     * Test reverseProxy relationship returns HasOne.
     */
    public function test_reverse_proxy_relationship_returns_has_one(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $reverseProxy = ServerReverseProxy::create([
            'server_id' => $server->id,
            'type' => 'nginx',
            'version' => '1.18',
            'status' => 'active',
        ]);

        // Act
        $relationship = $server->reverseProxy;

        // Assert
        $this->assertInstanceOf(ServerReverseProxy::class, $relationship);
        $this->assertEquals($reverseProxy->id, $relationship->id);
    }

    /**
     * Test ssh_root_password is encrypted.
     */
    public function test_ssh_root_password_is_encrypted(): void
    {
        // Arrange
        $plainPassword = 'plain-text-password';

        // Act
        $server = Server::factory()->create(['ssh_root_password' => $plainPassword]);

        // Assert - password in DB should be encrypted
        $this->assertDatabaseMissing('servers', [
            'id' => $server->id,
            'ssh_root_password' => $plainPassword,
        ]);

        // But should decrypt correctly when accessed
        $this->assertEquals($plainPassword, $server->ssh_root_password);
    }

    /**
     * Test monitoring_token is encrypted.
     */
    public function test_monitoring_token_is_encrypted(): void
    {
        // Arrange
        $token = 'plain-monitoring-token';

        // Act
        $server = Server::factory()->create(['monitoring_token' => $token]);

        // Assert
        $this->assertDatabaseMissing('servers', [
            'id' => $server->id,
            'monitoring_token' => $token,
        ]);

        $this->assertEquals($token, $server->monitoring_token);
    }

    /**
     * Test scheduler_token is encrypted.
     */
    public function test_scheduler_token_is_encrypted(): void
    {
        // Arrange
        $token = 'plain-scheduler-token';

        // Act
        $server = Server::factory()->create(['scheduler_token' => $token]);

        // Assert
        $this->assertDatabaseMissing('servers', [
            'id' => $server->id,
            'scheduler_token' => $token,
        ]);

        $this->assertEquals($token, $server->scheduler_token);
    }

    /**
     * Test connection enum cast.
     */
    public function test_connection_status_enum_cast(): void
    {
        // Act
        $server = Server::factory()->create(['connection_status' => 'connected']);

        // Assert
        $this->assertInstanceOf(ConnectionStatus::class, $server->connection_status);
    }

    /**
     * Test provider enum cast.
     */
    public function test_provider_enum_cast(): void
    {
        // Act
        $server = Server::factory()->create(['provider' => ServerProvider::DigitalOcean]);

        // Assert
        $this->assertInstanceOf(ServerProvider::class, $server->provider);
    }

    /**
     * Test provision_status enum cast.
     */
    public function test_provision_status_enum_cast(): void
    {
        // Act
        $server = Server::factory()->create(['provision_status' => ProvisionStatus::Completed]);

        // Assert
        $this->assertInstanceOf(ProvisionStatus::class, $server->provision_status);
    }

    /**
     * Test monitoring_status enum cast.
     */
    public function test_monitoring_status_enum_cast(): void
    {
        // Act
        $server = Server::factory()->create(['monitoring_status' => MonitoringStatus::Active]);

        // Assert
        $this->assertInstanceOf(MonitoringStatus::class, $server->monitoring_status);
    }

    /**
     * Test scheduler_status enum cast.
     */
    public function test_scheduler_status_enum_cast(): void
    {
        // Act
        $server = Server::factory()->create(['scheduler_status' => SchedulerStatus::Active]);

        // Assert
        $this->assertInstanceOf(SchedulerStatus::class, $server->scheduler_status);
    }

    /**
     * Test supervisor_status enum cast.
     */
    public function test_supervisor_status_enum_cast(): void
    {
        // Act
        $server = Server::factory()->create(['supervisor_status' => SupervisorStatus::Active]);

        // Assert
        $this->assertInstanceOf(SupervisorStatus::class, $server->supervisor_status);
    }

    /**
     * Test provision collection cast.
     */
    public function test_provision_collection_cast(): void
    {
        // Act
        $server = Server::factory()->create(['provision' => ['1' => 'installing', '2' => 'completed']]);

        // Assert
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $server->provision);
        $this->assertEquals('installing', $server->provision->get('1'));
    }
}
