<?php

namespace Tests\Feature;

use App\Enums\PhpStatus;
use App\Models\Server;
use App\Models\ServerPhp;
use App\Models\User;
use App\Packages\Services\PHP\PhpRemoverJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServerPhpControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_remove_php_versions(): void
    {
        $server = Server::factory()->create();
        $php = ServerPhp::factory()->create(['server_id' => $server->id]);

        $response = $this->delete("/servers/{$server->id}/php/{$php->id}");

        $response->assertRedirect('/login');
    }

    public function test_authenticated_users_can_remove_non_default_php_versions(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create();
        $php = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.2',
            'is_cli_default' => false,
            'is_site_default' => false,
            'status' => PhpStatus::Active,
        ]);

        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/php/{$php->id}");

        $response->assertRedirect("/servers/{$server->id}/php");
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('server_phps', [
            'id' => $php->id,
            'status' => PhpStatus::Removing->value,
        ]);

        Queue::assertPushed(PhpRemoverJob::class);
    }

    public function test_cannot_remove_cli_default_php_version(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create();
        $php = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'is_cli_default' => true,
            'is_site_default' => false,
            'status' => PhpStatus::Active,
        ]);

        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/php/{$php->id}");

        $response->assertRedirect("/servers/{$server->id}/php");
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('server_phps', [
            'id' => $php->id,
            'status' => PhpStatus::Active->value,
        ]);

        Queue::assertNotPushed(PhpRemoverJob::class);
    }

    public function test_cannot_remove_site_default_php_version(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create();
        $php = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'is_cli_default' => false,
            'is_site_default' => true,
            'status' => PhpStatus::Active,
        ]);

        $response = $this->actingAs($user)
            ->delete("/servers/{$server->id}/php/{$php->id}");

        $response->assertRedirect("/servers/{$server->id}/php");
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('server_phps', [
            'id' => $php->id,
            'status' => PhpStatus::Active->value,
        ]);

        Queue::assertNotPushed(PhpRemoverJob::class);
    }

    public function test_cannot_remove_php_version_from_different_server(): void
    {
        $user = User::factory()->create();
        $server1 = Server::factory()->create();
        $server2 = Server::factory()->create();
        $php = ServerPhp::factory()->create([
            'server_id' => $server2->id,
            'version' => '8.2',
            'is_cli_default' => false,
            'is_site_default' => false,
        ]);

        $response = $this->actingAs($user)
            ->delete("/servers/{$server1->id}/php/{$php->id}");

        $response->assertNotFound();
    }

    public function test_authenticated_users_can_set_cli_default(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create();

        $currentDefault = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.2',
            'is_cli_default' => true,
            'is_site_default' => false,
        ]);

        $newDefault = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'is_cli_default' => false,
            'is_site_default' => false,
        ]);

        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/php/{$newDefault->id}/set-cli-default");

        $response->assertRedirect("/servers/{$server->id}/php");
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('server_phps', [
            'id' => $currentDefault->id,
            'is_cli_default' => false,
        ]);

        $this->assertDatabaseHas('server_phps', [
            'id' => $newDefault->id,
            'is_cli_default' => true,
        ]);
    }

    public function test_authenticated_users_can_set_site_default(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create();

        $currentDefault = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.2',
            'is_cli_default' => false,
            'is_site_default' => true,
        ]);

        $newDefault = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.3',
            'is_cli_default' => false,
            'is_site_default' => false,
        ]);

        $response = $this->actingAs($user)
            ->patch("/servers/{$server->id}/php/{$newDefault->id}/set-site-default");

        $response->assertRedirect("/servers/{$server->id}/php");
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('server_phps', [
            'id' => $currentDefault->id,
            'is_site_default' => false,
        ]);

        $this->assertDatabaseHas('server_phps', [
            'id' => $newDefault->id,
            'is_site_default' => true,
        ]);
    }

    public function test_cannot_set_cli_default_for_different_server(): void
    {
        $user = User::factory()->create();
        $server1 = Server::factory()->create();
        $server2 = Server::factory()->create();
        $php = ServerPhp::factory()->create([
            'server_id' => $server2->id,
            'version' => '8.2',
            'is_cli_default' => false,
        ]);

        $response = $this->actingAs($user)
            ->patch("/servers/{$server1->id}/php/{$php->id}/set-cli-default");

        $response->assertNotFound();
    }

    public function test_cannot_set_site_default_for_different_server(): void
    {
        $user = User::factory()->create();
        $server1 = Server::factory()->create();
        $server2 = Server::factory()->create();
        $php = ServerPhp::factory()->create([
            'server_id' => $server2->id,
            'version' => '8.2',
            'is_site_default' => false,
        ]);

        $response = $this->actingAs($user)
            ->patch("/servers/{$server1->id}/php/{$php->id}/set-site-default");

        $response->assertNotFound();
    }

    public function test_guests_cannot_set_cli_default(): void
    {
        $server = Server::factory()->create();
        $php = ServerPhp::factory()->create(['server_id' => $server->id]);

        $response = $this->patch("/servers/{$server->id}/php/{$php->id}/set-cli-default");

        $response->assertRedirect('/login');
    }

    public function test_guests_cannot_set_site_default(): void
    {
        $server = Server::factory()->create();
        $php = ServerPhp::factory()->create(['server_id' => $server->id]);

        $response = $this->patch("/servers/{$server->id}/php/{$php->id}/set-site-default");

        $response->assertRedirect('/login');
    }

    public function test_authenticated_users_can_install_php_version(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create();

        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php/install", [
                'version' => '8.1',
            ]);

        $response->assertRedirect("/servers/{$server->id}/php");
        $response->assertSessionHas('success');
        $response->assertSessionMissing('errors');

        $this->assertDatabaseHas('server_phps', [
            'server_id' => $server->id,
            'version' => '8.1',
            'status' => PhpStatus::Installing->value,
        ]);

        Queue::assertPushed(\App\Packages\Services\PHP\PhpInstallerJob::class);
    }

    public function test_can_install_all_available_php_versions(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create();

        $versions = ['8.1', '8.2', '8.3', '8.4'];

        foreach ($versions as $version) {
            $response = $this->actingAs($user)
                ->post("/servers/{$server->id}/php/install", [
                    'version' => $version,
                ]);

            $response->assertRedirect("/servers/{$server->id}/php");
            $response->assertSessionMissing('errors');

            $this->assertDatabaseHas('server_phps', [
                'server_id' => $server->id,
                'version' => $version,
            ]);
        }
    }

    public function test_cannot_install_invalid_php_version(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create();

        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php/install", [
                'version' => '7.4',
            ]);

        $response->assertSessionHasErrors('version');

        $this->assertDatabaseMissing('server_phps', [
            'server_id' => $server->id,
            'version' => '7.4',
        ]);

        Queue::assertNotPushed(\App\Packages\Services\PHP\PhpInstallerJob::class);
    }

    public function test_cannot_install_already_installed_php_version(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->create();

        ServerPhp::factory()->create([
            'server_id' => $server->id,
            'version' => '8.2',
            'status' => PhpStatus::Active,
        ]);

        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/php/install", [
                'version' => '8.2',
            ]);

        $response->assertRedirect("/servers/{$server->id}/php");
        $response->assertSessionHas('error');

        Queue::assertNotPushed(\App\Packages\Services\PHP\PhpInstallerJob::class);
    }
}
