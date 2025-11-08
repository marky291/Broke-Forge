<?php

namespace Tests\Unit\Models;

use App\Enums\TaskStatus;
use App\Events\ServerSiteUpdated;
use App\Models\Server;
use App\Models\ServerDeployment;
use App\Models\ServerSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ServerSiteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that canInstallGitRepository returns true when git_status is null.
     */
    public function test_can_install_git_repository_returns_true_when_git_status_is_null(): void
    {
        // Arrange
        $site = ServerSite::factory()->create(['git_status' => null]);

        // Act & Assert
        $this->assertTrue($site->canInstallGitRepository());
    }

    /**
     * Test that canInstallGitRepository returns true when git_status can retry (Failed).
     */
    public function test_can_install_git_repository_returns_true_when_git_status_is_failed(): void
    {
        // Arrange
        $site = ServerSite::factory()->gitFailed()->create();

        // Act & Assert
        $this->assertTrue($site->canInstallGitRepository());
    }

    /**
     * Test that canInstallGitRepository returns true when git_status is null.
     */
    public function test_can_install_git_repository_returns_true_when_git_status_is_not_installed(): void
    {
        // Arrange
        $site = ServerSite::factory()->create(['git_status' => null]);

        // Act & Assert
        $this->assertTrue($site->canInstallGitRepository());
    }

    /**
     * Test that canInstallGitRepository returns false when git_status is Installing.
     */
    public function test_can_install_git_repository_returns_false_when_git_status_is_installing(): void
    {
        // Arrange
        $site = ServerSite::factory()->gitInstalling()->create();

        // Act & Assert
        $this->assertFalse($site->canInstallGitRepository());
    }

    /**
     * Test that canInstallGitRepository returns false when git_status is Installed.
     */
    public function test_can_install_git_repository_returns_false_when_git_status_is_installed(): void
    {
        // Arrange
        $site = ServerSite::factory()->withGit()->create();

        // Act & Assert
        $this->assertFalse($site->canInstallGitRepository());
    }

    /**
     * Test that isGitProcessing returns true when git_status is Installing.
     */
    public function test_is_git_processing_returns_true_when_git_status_is_installing(): void
    {
        // Arrange
        $site = ServerSite::factory()->gitInstalling()->create();

        // Act & Assert
        $this->assertTrue($site->isGitProcessing());
    }

    /**
     * Test that isGitProcessing returns true when git_status is Updating.
     */
    public function test_is_git_processing_returns_true_when_git_status_is_updating(): void
    {
        // Arrange
        $site = ServerSite::factory()->create(['git_status' => TaskStatus::Updating]);

        // Act & Assert
        $this->assertTrue($site->isGitProcessing());
    }

    /**
     * Test that isGitProcessing returns false when git_status is Installed.
     */
    public function test_is_git_processing_returns_false_when_git_status_is_installed(): void
    {
        // Arrange
        $site = ServerSite::factory()->withGit()->create();

        // Act & Assert
        $this->assertFalse($site->isGitProcessing());
    }

    /**
     * Test that isGitProcessing returns false when git_status is null.
     */
    public function test_is_git_processing_returns_false_when_git_status_is_null(): void
    {
        // Arrange
        $site = ServerSite::factory()->create(['git_status' => null]);

        // Act & Assert
        $this->assertFalse($site->isGitProcessing());
    }

    /**
     * Test that hasGitRepository returns true when git_status is Installed.
     */
    public function test_has_git_repository_returns_true_when_git_status_is_installed(): void
    {
        // Arrange
        $site = ServerSite::factory()->withGit()->create();

        // Act & Assert
        $this->assertTrue($site->hasGitRepository());
    }

    /**
     * Test that hasGitRepository returns false when git_status is not Installed.
     */
    public function test_has_git_repository_returns_false_when_git_status_is_not_installed(): void
    {
        // Arrange
        $site = ServerSite::factory()->create(['git_status' => TaskStatus::Failed]);

        // Act & Assert
        $this->assertFalse($site->hasGitRepository());
    }

    /**
     * Test that hasGitRepository returns false when git_status is null.
     */
    public function test_has_git_repository_returns_false_when_git_status_is_null(): void
    {
        // Arrange
        $site = ServerSite::factory()->create(['git_status' => null]);

        // Act & Assert
        $this->assertFalse($site->hasGitRepository());
    }

    /**
     * Test that getGitConfiguration returns correct configuration.
     */
    public function test_get_git_configuration_returns_correct_configuration(): void
    {
        // Arrange
        $site = ServerSite::factory()->withGit()->create();

        // Act
        $config = $site->getGitConfiguration();

        // Assert
        $this->assertEquals('github', $config['provider']);
        $this->assertEquals('laravel/laravel', $config['repository']);
        $this->assertEquals('main', $config['branch']);
        $this->assertNull($config['deploy_key']);
    }

    /**
     * Test that getGitConfiguration returns nulls when configuration is empty.
     */
    public function test_get_git_configuration_returns_nulls_when_configuration_is_empty(): void
    {
        // Arrange
        $site = ServerSite::factory()->create(['configuration' => []]);

        // Act
        $config = $site->getGitConfiguration();

        // Assert
        $this->assertNull($config['provider']);
        $this->assertNull($config['repository']);
        $this->assertNull($config['branch']);
        $this->assertNull($config['deploy_key']);
    }

    /**
     * Test that getGitConfiguration handles deploy_key fallback.
     */
    public function test_get_git_configuration_handles_deploy_key_fallback(): void
    {
        // Arrange - use old 'deployKey' naming
        $site = ServerSite::factory()->create([
            'configuration' => [
                'git_repository' => [
                    'provider' => 'gitlab',
                    'repository' => 'foo/bar',
                    'branch' => 'develop',
                    'deployKey' => 'old-key-format',
                ],
            ],
        ]);

        // Act
        $config = $site->getGitConfiguration();

        // Assert
        $this->assertEquals('old-key-format', $config['deploy_key']);
    }

    /**
     * Test that getGitConfiguration prefers deploy_key over deployKey.
     */
    public function test_get_git_configuration_prefers_deploy_key_over_deploy_key_camel_case(): void
    {
        // Arrange
        $site = ServerSite::factory()->create([
            'configuration' => [
                'git_repository' => [
                    'provider' => 'gitlab',
                    'repository' => 'foo/bar',
                    'branch' => 'develop',
                    'deploy_key' => 'new-key-format',
                    'deployKey' => 'old-key-format',
                ],
            ],
        ]);

        // Act
        $config = $site->getGitConfiguration();

        // Assert
        $this->assertEquals('new-key-format', $config['deploy_key']);
    }

    /**
     * Test that getDeploymentScript returns default when not configured.
     */
    public function test_get_deployment_script_returns_default_when_not_configured(): void
    {
        // Arrange
        $site = ServerSite::factory()->create(['configuration' => []]);

        // Act
        $script = $site->getDeploymentScript();

        // Assert
        $this->assertEquals("git pull\ncomposer install --no-dev --no-interaction --prefer-dist --optimize-autoloader", $script);
    }

    /**
     * Test that getDeploymentScript returns configured script.
     */
    public function test_get_deployment_script_returns_configured_script(): void
    {
        // Arrange
        $site = ServerSite::factory()->create([
            'configuration' => [
                'deployment' => [
                    'script' => 'custom deploy script',
                ],
            ],
        ]);

        // Act
        $script = $site->getDeploymentScript();

        // Assert
        $this->assertEquals('custom deploy script', $script);
    }

    /**
     * Test that updateDeploymentScript updates the configuration.
     */
    public function test_update_deployment_script_updates_configuration(): void
    {
        // Arrange
        $site = ServerSite::factory()->create(['configuration' => []]);
        $newScript = 'git pull && composer install --no-dev';

        // Act
        $site->updateDeploymentScript($newScript);

        // Assert
        $site->refresh();
        $this->assertEquals($newScript, $site->configuration['deployment']['script']);
        $this->assertEquals($newScript, $site->getDeploymentScript());
    }

    /**
     * Test that updateDeploymentScript preserves existing configuration.
     */
    public function test_update_deployment_script_preserves_existing_configuration(): void
    {
        // Arrange
        $site = ServerSite::factory()->create([
            'configuration' => [
                'git_repository' => [
                    'provider' => 'github',
                    'repository' => 'test/repo',
                ],
            ],
        ]);

        // Act
        $site->updateDeploymentScript('new script');

        // Assert
        $site->refresh();
        $this->assertEquals('new script', $site->configuration['deployment']['script']);
        $this->assertEquals('github', $site->configuration['git_repository']['provider']);
    }

    /**
     * Test that installed_at_human accessor returns human readable date.
     */
    public function test_installed_at_human_accessor_returns_human_readable_date(): void
    {
        // Arrange
        $site = ServerSite::factory()->create([
            'installed_at' => now()->subDays(3),
        ]);

        // Act
        $humanDate = $site->installed_at_human;

        // Assert
        $this->assertNotNull($humanDate);
        $this->assertStringContainsString('ago', $humanDate);
    }

    /**
     * Test that installed_at_human accessor returns null when installed_at is null.
     */
    public function test_installed_at_human_accessor_returns_null_when_installed_at_is_null(): void
    {
        // Arrange
        $site = ServerSite::factory()->create(['installed_at' => null]);

        // Act
        $humanDate = $site->installed_at_human;

        // Assert
        $this->assertNull($humanDate);
    }

    /**
     * Test that last_deployed_at_human accessor returns human readable date.
     */
    public function test_last_deployed_at_human_accessor_returns_human_readable_date(): void
    {
        // Arrange
        $site = ServerSite::factory()->create([
            'last_deployed_at' => now()->subHours(2),
        ]);

        // Act
        $humanDate = $site->last_deployed_at_human;

        // Assert
        $this->assertNotNull($humanDate);
        $this->assertStringContainsString('ago', $humanDate);
    }

    /**
     * Test that last_deployed_at_human accessor returns null when last_deployed_at is null.
     */
    public function test_last_deployed_at_human_accessor_returns_null_when_last_deployed_at_is_null(): void
    {
        // Arrange
        $site = ServerSite::factory()->create(['last_deployed_at' => null]);

        // Act
        $humanDate = $site->last_deployed_at_human;

        // Assert
        $this->assertNull($humanDate);
    }

    /**
     * Test that site belongs to a server.
     */
    public function test_site_belongs_to_server(): void
    {
        // Arrange
        $server = Server::factory()->create(['vanity_name' => 'test-server']);
        $site = ServerSite::factory()->create(['server_id' => $server->id]);

        // Act
        $relatedServer = $site->server;

        // Assert
        $this->assertInstanceOf(Server::class, $relatedServer);
        $this->assertEquals($server->id, $relatedServer->id);
        $this->assertEquals('test-server', $relatedServer->vanity_name);
    }

    /**
     * Test that site has many deployments.
     */
    public function test_site_has_many_deployments(): void
    {
        // Arrange
        Event::fake();
        $site = ServerSite::factory()->create();
        ServerDeployment::factory()->count(3)->create(['server_site_id' => $site->id]);

        // Act
        $deployments = $site->deployments;

        // Assert
        $this->assertCount(3, $deployments);
        $this->assertInstanceOf(ServerDeployment::class, $deployments->first());
    }

    /**
     * Test that latestDeployment returns the most recent deployment.
     */
    public function test_latest_deployment_returns_most_recent_deployment(): void
    {
        // Arrange
        Event::fake();
        $site = ServerSite::factory()->create();
        ServerDeployment::factory()->create([
            'server_site_id' => $site->id,
            'created_at' => now()->subHours(2),
        ]);
        $latestDeployment = ServerDeployment::factory()->create([
            'server_site_id' => $site->id,
            'created_at' => now()->subHour(),
        ]);

        // Act
        $retrieved = $site->latestDeployment;

        // Assert
        $this->assertNotNull($retrieved);
        $this->assertEquals($latestDeployment->id, $retrieved->id);
    }

    /**
     * Test that ssl_enabled is cast to boolean.
     */
    public function test_ssl_enabled_is_cast_to_boolean(): void
    {
        // Arrange
        $site = ServerSite::factory()->create(['ssl_enabled' => '1']);

        // Act
        $sslEnabled = $site->ssl_enabled;

        // Assert
        $this->assertIsBool($sslEnabled);
        $this->assertTrue($sslEnabled);
    }

    /**
     * Test that auto_deploy_enabled is cast to boolean.
     */
    public function test_auto_deploy_enabled_is_cast_to_boolean(): void
    {
        // Arrange
        $site = ServerSite::factory()->create(['auto_deploy_enabled' => false]);

        // Act
        $autoDeployEnabled = $site->auto_deploy_enabled;

        // Assert
        $this->assertIsBool($autoDeployEnabled);
        $this->assertFalse($autoDeployEnabled);
    }

    /**
     * Test that has_dedicated_deploy_key is cast to boolean.
     */
    public function test_has_dedicated_deploy_key_is_cast_to_boolean(): void
    {
        // Arrange
        $site = ServerSite::factory()->create(['has_dedicated_deploy_key' => true]);

        // Act
        $hasDedicatedKey = $site->has_dedicated_deploy_key;

        // Assert
        $this->assertIsBool($hasDedicatedKey);
        $this->assertTrue($hasDedicatedKey);
    }

    /**
     * Test that configuration is cast to array.
     */
    public function test_configuration_is_cast_to_array(): void
    {
        // Arrange
        $site = ServerSite::factory()->create([
            'configuration' => ['key' => 'value'],
        ]);

        // Act
        $config = $site->configuration;

        // Assert
        $this->assertIsArray($config);
        $this->assertEquals('value', $config['key']);
    }

    /**
     * Test that git_status is cast to GitStatus enum.
     */
    public function test_git_status_is_cast_to_git_status_enum(): void
    {
        // Arrange
        $site = ServerSite::factory()->withGit()->create();

        // Act & Assert
        $this->assertInstanceOf(TaskStatus::class, $site->git_status);
        $this->assertEquals(TaskStatus::Success, $site->git_status);
    }

    /**
     * Test that installed_at is cast to datetime.
     */
    public function test_installed_at_is_cast_to_datetime(): void
    {
        // Arrange
        $site = ServerSite::factory()->create(['installed_at' => now()]);

        // Act & Assert
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $site->installed_at);
    }

    /**
     * Test that git_installed_at is cast to datetime.
     */
    public function test_git_installed_at_is_cast_to_datetime(): void
    {
        // Arrange
        $site = ServerSite::factory()->withGit()->create();

        // Act & Assert
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $site->git_installed_at);
    }

    /**
     * Test that last_deployed_at is cast to datetime.
     */
    public function test_last_deployed_at_is_cast_to_datetime(): void
    {
        // Arrange
        $site = ServerSite::factory()->create(['last_deployed_at' => now()]);

        // Act & Assert
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $site->last_deployed_at);
    }

    /**
     * Test that uninstalled_at is cast to datetime.
     */
    public function test_uninstalled_at_is_cast_to_datetime(): void
    {
        // Arrange
        $site = ServerSite::factory()->create(['uninstalled_at' => now()]);

        // Act & Assert
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $site->uninstalled_at);
    }

    /**
     * Test that ServerSiteUpdated event is dispatched when site is updated with meaningful fields.
     */
    public function test_server_site_updated_event_dispatched_when_meaningful_fields_changed(): void
    {
        // Arrange
        $site = ServerSite::factory()->create(['status' => 'installing']);
        Event::fake([ServerSiteUpdated::class]);

        // Act
        $site->update(['status' => 'active']);

        // Assert
        Event::assertDispatched(ServerSiteUpdated::class, function ($event) use ($site) {
            return $event->siteId === $site->id;
        });
    }

    /**
     * Test that ServerSiteUpdated event is NOT dispatched when non-meaningful fields changed.
     */
    public function test_server_site_updated_event_not_dispatched_when_non_meaningful_fields_changed(): void
    {
        // Arrange
        $site = ServerSite::factory()->create();
        Event::fake([ServerSiteUpdated::class]);

        // Act - update a field that's not in the broadcast list
        $site->update(['document_root' => '/new/path']);

        // Assert
        Event::assertNotDispatched(ServerSiteUpdated::class);
    }

    /**
     * Test that ServerSiteUpdated event is dispatched when default_site_status changes.
     */
    public function test_server_site_updated_event_dispatched_when_default_site_status_changes(): void
    {
        // Arrange
        $site = ServerSite::factory()->create([
            'is_default' => true,
            'default_site_status' => TaskStatus::Installing,
        ]);
        Event::fake([ServerSiteUpdated::class]);

        // Act - update default_site_status to active
        $site->update(['default_site_status' => TaskStatus::Active]);

        // Assert
        Event::assertDispatched(ServerSiteUpdated::class, function ($event) use ($site) {
            return $event->siteId === $site->id;
        });
    }

    /**
     * Test that factory creates valid site with all required fields.
     */
    public function test_factory_creates_valid_site(): void
    {
        // Act
        $site = ServerSite::factory()->create();

        // Assert
        $this->assertInstanceOf(ServerSite::class, $site);
        $this->assertNotNull($site->server_id);
        $this->assertNotNull($site->domain);
        $this->assertNotNull($site->document_root);
        $this->assertNotNull($site->php_version);
    }

    /**
     * Test that factory withSSL state creates site with SSL.
     */
    public function test_factory_with_ssl_state_creates_site_with_ssl(): void
    {
        // Act
        $site = ServerSite::factory()->withSSL()->create();

        // Assert
        $this->assertTrue($site->ssl_enabled);
        $this->assertNotNull($site->ssl_cert_path);
        $this->assertNotNull($site->ssl_key_path);
    }

    /**
     * Test that factory active state creates active site.
     */
    public function test_factory_active_state_creates_active_site(): void
    {
        // Act
        $site = ServerSite::factory()->active()->create();

        // Assert
        $this->assertEquals('active', $site->status);
        $this->assertNotNull($site->installed_at);
    }

    /**
     * Test that factory withGit state creates site with Git installed.
     */
    public function test_factory_with_git_state_creates_site_with_git_installed(): void
    {
        // Act
        $site = ServerSite::factory()->withGit()->create();

        // Assert
        $this->assertEquals(TaskStatus::Success, $site->git_status);
        $this->assertNotNull($site->git_installed_at);
        $this->assertNotEmpty($site->configuration['git_repository']);
    }

    /**
     * Test that factory gitInstalling state creates site with Installing status.
     */
    public function test_factory_git_installing_state_creates_site_with_installing_status(): void
    {
        // Act
        $site = ServerSite::factory()->gitInstalling()->create();

        // Assert
        $this->assertEquals(TaskStatus::Installing, $site->git_status);
    }

    /**
     * Test that factory gitFailed state creates site with Failed status.
     */
    public function test_factory_git_failed_state_creates_site_with_failed_status(): void
    {
        // Act
        $site = ServerSite::factory()->gitFailed()->create();

        // Assert
        $this->assertEquals(TaskStatus::Failed, $site->git_status);
    }

    /**
     * Test that isDomain returns true for domain names.
     */
    public function test_is_domain_returns_true_for_domain_names(): void
    {
        // Arrange
        $domains = [
            'example.com',
            'sub.example.com',
            'api.example.co.uk',
        ];

        foreach ($domains as $domain) {
            $site = ServerSite::factory()->create(['domain' => $domain]);

            // Act & Assert
            $this->assertTrue($site->isDomain(), "Domain {$domain} should return true");
        }
    }

    /**
     * Test that isDomain returns false for project names.
     */
    public function test_is_domain_returns_false_for_project_names(): void
    {
        // Arrange
        $projectNames = [
            'myproject',
            'my-project',
            'test-app',
        ];

        foreach ($projectNames as $name) {
            $site = ServerSite::factory()->create(['domain' => $name]);

            // Act & Assert
            $this->assertFalse($site->isDomain(), "Project name {$name} should return false");
        }
    }
}
