<?php

namespace Tests\Unit\Models;

use App\Models\SourceProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SourceProviderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test source provider belongs to a user.
     */
    public function test_belongs_to_user(): void
    {
        // Arrange
        $user = User::factory()->create();
        $provider = SourceProvider::factory()->create([
            'user_id' => $user->id,
        ]);

        // Act
        $result = $provider->user;

        // Assert
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($user->id, $result->id);
    }

    /**
     * Test access token is cast to encrypted.
     */
    public function test_access_token_is_encrypted(): void
    {
        // Arrange
        $plainToken = 'ghp_test_token_1234567890';

        // Act
        $provider = SourceProvider::factory()->create([
            'access_token' => $plainToken,
        ]);

        // Assert - fresh read from database should decrypt
        $this->assertEquals($plainToken, $provider->fresh()->access_token);

        // Assert - raw database value should be encrypted (different from plain)
        $rawValue = \DB::table('source_providers')
            ->where('id', $provider->id)
            ->value('access_token');
        $this->assertNotEquals($plainToken, $rawValue);
    }

    /**
     * Test is github returns true for github provider.
     */
    public function test_is_github_returns_true_for_github_provider(): void
    {
        // Arrange
        $provider = SourceProvider::factory()->github()->create();

        // Act & Assert
        $this->assertTrue($provider->isGitHub());
        $this->assertFalse($provider->isGitLab());
        $this->assertFalse($provider->isBitbucket());
    }

    /**
     * Test is gitlab returns true for gitlab provider.
     */
    public function test_is_gitlab_returns_true_for_gitlab_provider(): void
    {
        // Arrange
        $provider = SourceProvider::factory()->create([
            'provider' => 'gitlab',
        ]);

        // Act & Assert
        $this->assertTrue($provider->isGitLab());
        $this->assertFalse($provider->isGitHub());
        $this->assertFalse($provider->isBitbucket());
    }

    /**
     * Test is bitbucket returns true for bitbucket provider.
     */
    public function test_is_bitbucket_returns_true_for_bitbucket_provider(): void
    {
        // Arrange
        $provider = SourceProvider::factory()->create([
            'provider' => 'bitbucket',
        ]);

        // Act & Assert
        $this->assertTrue($provider->isBitbucket());
        $this->assertFalse($provider->isGitHub());
        $this->assertFalse($provider->isGitLab());
    }

    /**
     * Test factory creates provider with correct attributes.
     */
    public function test_factory_creates_provider_with_correct_attributes(): void
    {
        // Act
        $provider = SourceProvider::factory()->create();

        // Assert
        $this->assertNotNull($provider->user_id);
        $this->assertNotNull($provider->provider);
        $this->assertNotNull($provider->provider_user_id);
        $this->assertNotNull($provider->username);
        $this->assertNotNull($provider->email);
        $this->assertNotNull($provider->access_token);
        $this->assertEquals('github', $provider->provider);
    }

    /**
     * Test factory github state sets github provider.
     */
    public function test_factory_github_state_sets_github_provider(): void
    {
        // Act
        $provider = SourceProvider::factory()->github()->create();

        // Assert
        $this->assertEquals('github', $provider->provider);
        $this->assertTrue($provider->isGitHub());
        $this->assertStringStartsWith('ghp_', $provider->access_token);
    }

    /**
     * Test fillable attributes are mass assignable.
     */
    public function test_fillable_attributes_are_mass_assignable(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $provider = SourceProvider::create([
            'user_id' => $user->id,
            'provider' => 'github',
            'provider_user_id' => '12345678',
            'username' => 'octocat',
            'email' => 'octocat@github.com',
            'access_token' => 'ghp_test_token',
        ]);

        // Assert
        $this->assertDatabaseHas('source_providers', [
            'user_id' => $user->id,
            'provider' => 'github',
            'provider_user_id' => '12345678',
            'username' => 'octocat',
            'email' => 'octocat@github.com',
        ]);
    }

    /**
     * Test provider can store different provider types.
     */
    public function test_provider_can_store_different_provider_types(): void
    {
        // Arrange & Act
        $github = SourceProvider::factory()->create(['provider' => 'github']);
        $gitlab = SourceProvider::factory()->create(['provider' => 'gitlab']);
        $bitbucket = SourceProvider::factory()->create(['provider' => 'bitbucket']);

        // Assert
        $this->assertEquals('github', $github->provider);
        $this->assertEquals('gitlab', $gitlab->provider);
        $this->assertEquals('bitbucket', $bitbucket->provider);
    }

    /**
     * Test multiple providers can belong to same user.
     */
    public function test_multiple_providers_can_belong_to_same_user(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $github = SourceProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'github',
        ]);
        $gitlab = SourceProvider::factory()->create([
            'user_id' => $user->id,
            'provider' => 'gitlab',
        ]);

        // Assert
        $this->assertEquals($user->id, $github->user_id);
        $this->assertEquals($user->id, $gitlab->user_id);
        $this->assertCount(2, SourceProvider::where('user_id', $user->id)->get());
    }

    /**
     * Test provider can be deleted.
     */
    public function test_provider_can_be_deleted(): void
    {
        // Arrange
        $provider = SourceProvider::factory()->create();
        $providerId = $provider->id;

        // Act
        $provider->delete();

        // Assert
        $this->assertDatabaseMissing('source_providers', [
            'id' => $providerId,
        ]);
    }

    /**
     * Test provider relationship can be eagerly loaded.
     */
    public function test_provider_relationship_can_be_eagerly_loaded(): void
    {
        // Arrange
        $user = User::factory()->create();
        SourceProvider::factory()->create(['user_id' => $user->id]);

        // Act
        $provider = SourceProvider::with('user')->first();

        // Assert
        $this->assertTrue($provider->relationLoaded('user'));
        $this->assertInstanceOf(User::class, $provider->user);
    }

    /**
     * Test provider stores provider user id.
     */
    public function test_provider_stores_provider_user_id(): void
    {
        // Arrange & Act
        $provider = SourceProvider::factory()->create([
            'provider_user_id' => '99999999',
        ]);

        // Assert
        $this->assertEquals('99999999', $provider->provider_user_id);
    }

    /**
     * Test provider stores username.
     */
    public function test_provider_stores_username(): void
    {
        // Arrange & Act
        $provider = SourceProvider::factory()->create([
            'username' => 'testuser',
        ]);

        // Assert
        $this->assertEquals('testuser', $provider->username);
    }

    /**
     * Test provider stores email.
     */
    public function test_provider_stores_email(): void
    {
        // Arrange & Act
        $provider = SourceProvider::factory()->create([
            'email' => 'test@example.com',
        ]);

        // Assert
        $this->assertEquals('test@example.com', $provider->email);
    }

    /**
     * Test github token format is validated by factory.
     */
    public function test_github_token_format_is_validated_by_factory(): void
    {
        // Act
        $provider = SourceProvider::factory()->github()->create();

        // Assert
        $this->assertStringStartsWith('ghp_', $provider->access_token);
        $this->assertEquals(40, strlen($provider->access_token));
    }

    /**
     * Test provider can be updated.
     */
    public function test_provider_can_be_updated(): void
    {
        // Arrange
        $provider = SourceProvider::factory()->create([
            'username' => 'oldusername',
        ]);

        // Act
        $provider->update(['username' => 'newusername']);

        // Assert
        $this->assertEquals('newusername', $provider->fresh()->username);
    }

    /**
     * Test access token can be updated.
     */
    public function test_access_token_can_be_updated(): void
    {
        // Arrange
        $provider = SourceProvider::factory()->create([
            'access_token' => 'old_token',
        ]);

        // Act
        $provider->update(['access_token' => 'new_token']);

        // Assert
        $this->assertEquals('new_token', $provider->fresh()->access_token);
    }

    /**
     * Test only specified provider returns true for helper method.
     */
    public function test_only_specified_provider_returns_true_for_helper_method(): void
    {
        // Arrange
        $provider = SourceProvider::factory()->create(['provider' => 'github']);

        // Act & Assert
        $this->assertTrue($provider->isGitHub());
        $this->assertFalse($provider->isGitLab());
        $this->assertFalse($provider->isBitbucket());

        // Change to gitlab
        $provider->update(['provider' => 'gitlab']);

        // Assert again
        $this->assertTrue($provider->fresh()->isGitLab());
        $this->assertFalse($provider->fresh()->isGitHub());
        $this->assertFalse($provider->fresh()->isBitbucket());
    }
}
