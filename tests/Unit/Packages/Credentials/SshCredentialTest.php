<?php

namespace Tests\Unit\Packages\Credentials;

use App\Packages\Credentials\SshCredential;
use Tests\TestCase;

class SshCredentialTest extends TestCase
{
    public function test_ssh_credential_is_interface(): void
    {
        $reflection = new \ReflectionClass(SshCredential::class);
        $this->assertTrue($reflection->isInterface());
    }

    public function test_ssh_credential_has_required_methods(): void
    {
        $reflection = new \ReflectionClass(SshCredential::class);

        $this->assertTrue($reflection->hasMethod('user'));
        $this->assertTrue($reflection->hasMethod('publicKey'));
        $this->assertTrue($reflection->hasMethod('privateKey'));
    }

    public function test_user_method_has_correct_signature(): void
    {
        $reflection = new \ReflectionClass(SshCredential::class);
        $method = $reflection->getMethod('user');

        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isAbstract());
        $this->assertEquals(0, $method->getNumberOfParameters());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('string', $returnType->getName());
    }

    public function test_public_key_method_has_correct_signature(): void
    {
        $reflection = new \ReflectionClass(SshCredential::class);
        $method = $reflection->getMethod('publicKey');

        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isAbstract());
        $this->assertEquals(0, $method->getNumberOfParameters());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('string', $returnType->getName());
    }

    public function test_private_key_method_has_correct_signature(): void
    {
        $reflection = new \ReflectionClass(SshCredential::class);
        $method = $reflection->getMethod('privateKey');

        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isAbstract());
        $this->assertEquals(0, $method->getNumberOfParameters());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('string', $returnType->getName());
    }

    public function test_ssh_credential_can_be_implemented(): void
    {
        $credential = new TestCredentialImplementation;
        $this->assertInstanceOf(SshCredential::class, $credential);
    }

    public function test_concrete_implementation_methods_return_strings(): void
    {
        $credential = new TestCredentialImplementation;

        $this->assertIsString($credential->user());
        $this->assertIsString($credential->publicKey());
        $this->assertIsString($credential->privateKey());
    }

    public function test_concrete_implementation_returns_expected_values(): void
    {
        $credential = new TestCredentialImplementation;

        $this->assertEquals('test-user', $credential->user());
        $this->assertEquals('/path/to/public.key', $credential->publicKey());
        $this->assertEquals('/path/to/private.key', $credential->privateKey());
    }
}

/**
 * Concrete implementation for testing
 */
class TestCredentialImplementation implements SshCredential
{
    public function user(): string
    {
        return 'test-user';
    }

    public function publicKey(): string
    {
        return '/path/to/public.key';
    }

    public function privateKey(): string
    {
        return '/path/to/private.key';
    }
}
