<?php

namespace Tests\Unit\Packages\Credentials;

use App\Models\Server;
use App\Packages\Credentials\TemporaryCredentialCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TemporaryCredentialCacheTest extends TestCase
{
    use RefreshDatabase;

    private Server $server;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = Server::factory()->create();
    }

    public function test_root_password_generates_new_password_when_not_cached(): void
    {
        Cache::shouldReceive('rememberForever')
            ->once()
            ->withArgs(function ($key, $callback) {
                $this->assertStringContainsString('servers:', $key);
                $this->assertStringContainsString(':root_password', $key);
                $this->assertStringContainsString((string) $this->server->id, $key);

                // Execute the callback to test password generation
                $password = $callback();
                $this->assertIsString($password);
                $this->assertEquals(24, strlen($password));
                $this->assertMatchesRegularExpression('/^[ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789]+$/', $password);

                return true;
            })
            ->andReturn('generated_password_123');

        $password = TemporaryCredentialCache::rootPassword($this->server);

        $this->assertEquals('generated_password_123', $password);
    }

    public function test_root_password_returns_cached_password_when_exists(): void
    {
        $cachedPassword = 'cached_password_456';

        Cache::shouldReceive('rememberForever')
            ->once()
            ->andReturn($cachedPassword);

        $password = TemporaryCredentialCache::rootPassword($this->server);

        $this->assertEquals($cachedPassword, $password);
    }

    public function test_forget_root_password_removes_from_cache(): void
    {
        Cache::shouldReceive('forget')
            ->once()
            ->withArgs(function ($key) {
                $this->assertStringContainsString('servers:', $key);
                $this->assertStringContainsString(':root_password', $key);
                $this->assertStringContainsString((string) $this->server->id, $key);
                return true;
            });

        TemporaryCredentialCache::forgetRootPassword($this->server);
    }

    public function test_cache_key_format(): void
    {
        // Test that cache key follows expected format
        $reflection = new \ReflectionClass(TemporaryCredentialCache::class);
        $method = $reflection->getMethod('cacheKey');
        $method->setAccessible(true);

        $key = $method->invoke(null, $this->server);

        $expectedKey = sprintf('servers:%s:root_password', $this->server->getKey());
        $this->assertEquals($expectedKey, $key);
    }

    public function test_generate_password_creates_url_safe_passwords(): void
    {
        $reflection = new \ReflectionClass(TemporaryCredentialCache::class);
        $method = $reflection->getMethod('generatePassword');
        $method->setAccessible(true);

        // Test default length
        $password = $method->invoke(null);
        $this->assertEquals(24, strlen($password));
        $this->assertMatchesRegularExpression('/^[ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789]+$/', $password);

        // Test custom length
        $password = $method->invoke(null, 32);
        $this->assertEquals(32, strlen($password));
        $this->assertMatchesRegularExpression('/^[ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789]+$/', $password);

        // Test that passwords are different each time
        $password1 = $method->invoke(null);
        $password2 = $method->invoke(null);
        $this->assertNotEquals($password1, $password2);
    }

    public function test_password_excludes_ambiguous_characters(): void
    {
        $reflection = new \ReflectionClass(TemporaryCredentialCache::class);
        $method = $reflection->getMethod('generatePassword');
        $method->setAccessible(true);

        // Generate multiple passwords to increase chance of catching excluded characters
        // Note: The alphabet is 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789'
        // which already excludes: 0, O, o, 1, I, l
        for ($i = 0; $i < 10; $i++) {
            $password = $method->invoke(null, 100);

            // Check that password doesn't contain ambiguous characters
            $this->assertStringNotContainsString('0', $password);
            $this->assertStringNotContainsString('O', $password);
            $this->assertStringNotContainsString('1', $password);
            $this->assertStringNotContainsString('I', $password);
            $this->assertStringNotContainsString('l', $password);
            // Note: 'o' is actually included in the alphabet, so we should not test for it
        }
    }
}