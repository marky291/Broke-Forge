<?php

namespace Tests\Unit\Models;

use App\Models\Server;
use App\Models\ServerCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class ServerCredentialTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that private key is encrypted when stored in database.
     */
    public function test_private_key_is_encrypted_when_stored(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $plainPrivateKey = "-----BEGIN OPENSSH PRIVATE KEY-----\ntest-key\n-----END OPENSSH PRIVATE KEY-----";

        // Act
        $credential = ServerCredential::create([
            'server_id' => $server->id,
            'user' => ServerCredential::ROOT,
            'private_key' => $plainPrivateKey,
            'public_key' => 'ssh-rsa test-public',
        ]);

        // Assert - verify the database contains encrypted value, not plain text
        $this->assertDatabaseHas('server_credentials', [
            'id' => $credential->id,
            'user' => ServerCredential::ROOT,
        ]);

        // Verify the raw database value is NOT the plain text (it's encrypted)
        $rawValue = $credential->getAttributes()['private_key'];
        $this->assertNotEquals($plainPrivateKey, $rawValue);

        // Verify we can decrypt it back to the original
        $this->assertEquals($plainPrivateKey, Crypt::decryptString($rawValue));
    }

    /**
     * Test that private key is decrypted when retrieved from database.
     */
    public function test_private_key_is_decrypted_when_retrieved(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $plainPrivateKey = "-----BEGIN OPENSSH PRIVATE KEY-----\ntest-key\n-----END OPENSSH PRIVATE KEY-----";

        $credential = ServerCredential::create([
            'server_id' => $server->id,
            'user' => ServerCredential::ROOT,
            'private_key' => $plainPrivateKey,
            'public_key' => 'ssh-rsa test-public',
        ]);

        // Act - retrieve from database
        $retrieved = ServerCredential::find($credential->id);

        // Assert - verify we get the decrypted plain text
        $this->assertEquals($plainPrivateKey, $retrieved->private_key);
    }

    /**
     * Test that multiple credentials can be created for same server with different users.
     */
    public function test_multiple_credentials_can_be_created_for_same_server(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Act - create root credential
        $rootCredential = ServerCredential::factory()->root()->create([
            'server_id' => $server->id,
        ]);

        // Create brokeforge credential
        $brokeforgeCredential = ServerCredential::factory()->brokeforge()->create([
            'server_id' => $server->id,
        ]);

        // Assert
        $this->assertEquals(2, ServerCredential::where('server_id', $server->id)->count());
        $this->assertEquals(ServerCredential::ROOT, $rootCredential->user);
        $this->assertEquals(ServerCredential::BROKEFORGE, $brokeforgeCredential->user);
    }

    /**
     * Test that getUsername() returns the correct username for root user.
     */
    public function test_get_username_returns_correct_username_for_root(): void
    {
        // Arrange
        $credential = ServerCredential::factory()->root()->create();

        // Act
        $username = $credential->getUsername();

        // Assert
        $this->assertEquals('root', $username);
        $this->assertEquals(ServerCredential::ROOT, $username);
    }

    /**
     * Test that getUsername() returns the correct username for brokeforge user.
     */
    public function test_get_username_returns_correct_username_for_brokeforge(): void
    {
        // Arrange
        $credential = ServerCredential::factory()->brokeforge()->create();

        // Act
        $username = $credential->getUsername();

        // Assert
        $this->assertEquals('brokeforge', $username);
        $this->assertEquals(ServerCredential::BROKEFORGE, $username);
    }

    /**
     * Test that generateKeyPair() creates a new credential with generated keys.
     */
    public function test_generate_key_pair_creates_new_credential(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Act - let real SSH key generation happen
        $credential = ServerCredential::generateKeyPair($server, ServerCredential::ROOT);

        // Assert
        $this->assertInstanceOf(ServerCredential::class, $credential);
        $this->assertEquals($server->id, $credential->server_id);
        $this->assertEquals(ServerCredential::ROOT, $credential->user);
        $this->assertNotEmpty($credential->private_key);
        $this->assertNotEmpty($credential->public_key);
        $this->assertStringContainsString('BEGIN OPENSSH PRIVATE KEY', $credential->private_key);
        $this->assertStringContainsString('ssh-rsa', $credential->public_key);

        // Verify database record exists
        $this->assertDatabaseHas('server_credentials', [
            'server_id' => $server->id,
            'user' => ServerCredential::ROOT,
        ]);
    }

    /**
     * Test that generateKeyPair() updates an existing credential.
     */
    public function test_generate_key_pair_updates_existing_credential(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Create existing credential
        $existingCredential = ServerCredential::factory()->create([
            'server_id' => $server->id,
            'user' => ServerCredential::ROOT,
        ]);

        $oldPrivateKey = $existingCredential->private_key;

        // Act - regenerate keys
        $credential = ServerCredential::generateKeyPair($server, ServerCredential::ROOT);

        // Assert - same ID, new keys
        $this->assertEquals($existingCredential->id, $credential->id);
        $this->assertNotEquals($oldPrivateKey, $credential->private_key);
        $this->assertNotEmpty($credential->private_key);
        $this->assertNotEmpty($credential->public_key);

        // Verify only one record exists
        $this->assertEquals(1, ServerCredential::where('server_id', $server->id)->count());
    }

    /**
     * Test that generateKeyPair() works for brokeforge user.
     */
    public function test_generate_key_pair_works_for_brokeforge_user(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Act
        $credential = ServerCredential::generateKeyPair($server, ServerCredential::BROKEFORGE);

        // Assert
        $this->assertEquals(ServerCredential::BROKEFORGE, $credential->user);
        $this->assertNotEmpty($credential->private_key);
        $this->assertNotEmpty($credential->public_key);
        $this->assertDatabaseHas('server_credentials', [
            'server_id' => $server->id,
            'user' => ServerCredential::BROKEFORGE,
        ]);
    }

    /**
     * Test that server relationship returns the correct server.
     */
    public function test_server_relationship_returns_correct_server(): void
    {
        // Arrange
        $server = Server::factory()->create(['vanity_name' => 'production-server']);
        $credential = ServerCredential::factory()->create([
            'server_id' => $server->id,
        ]);

        // Act
        $relatedServer = $credential->server;

        // Assert
        $this->assertInstanceOf(Server::class, $relatedServer);
        $this->assertEquals($server->id, $relatedServer->id);
        $this->assertEquals('production-server', $relatedServer->vanity_name);
    }

    /**
     * Test that server relationship uses belongsTo.
     */
    public function test_server_relationship_is_belongs_to(): void
    {
        // Arrange
        $credential = new ServerCredential;

        // Act
        $relation = $credential->server();

        // Assert
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $relation);
    }

    /**
     * Test that ROOT constant has correct value.
     */
    public function test_root_constant_has_correct_value(): void
    {
        $this->assertEquals('root', ServerCredential::ROOT);
    }

    /**
     * Test that BROKEFORGE constant has correct value.
     */
    public function test_brokeforge_constant_has_correct_value(): void
    {
        $this->assertEquals('brokeforge', ServerCredential::BROKEFORGE);
    }

    /**
     * Test that factory creates valid credential with all required fields.
     */
    public function test_factory_creates_valid_credential(): void
    {
        // Act
        $credential = ServerCredential::factory()->create();

        // Assert
        $this->assertInstanceOf(ServerCredential::class, $credential);
        $this->assertNotNull($credential->server_id);
        $this->assertNotNull($credential->user);
        $this->assertNotNull($credential->private_key);
        $this->assertNotNull($credential->public_key);
    }

    /**
     * Test that factory root state creates root user credential.
     */
    public function test_factory_root_state_creates_root_credential(): void
    {
        // Act
        $credential = ServerCredential::factory()->root()->create();

        // Assert
        $this->assertEquals(ServerCredential::ROOT, $credential->user);
    }

    /**
     * Test that factory brokeforge state creates brokeforge user credential.
     */
    public function test_factory_brokeforge_state_creates_brokeforge_credential(): void
    {
        // Act
        $credential = ServerCredential::factory()->brokeforge()->create();

        // Assert
        $this->assertEquals(ServerCredential::BROKEFORGE, $credential->user);
    }
}
