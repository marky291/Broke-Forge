<?php

namespace Tests\Unit\Packages\Credentials;

use App\Packages\Credentials\RootCredential;
use App\Packages\Credentials\SshCredential;
use Tests\TestCase;

class RootCredentialTest extends TestCase
{
    private RootCredential $credential;

    protected function setUp(): void
    {
        parent::setUp();
        $this->credential = new RootCredential;
    }

    public function test_implements_ssh_credential_interface(): void
    {
        $this->assertInstanceOf(SshCredential::class, $this->credential);
    }

    public function test_user_returns_root(): void
    {
        $this->assertEquals('root', $this->credential->user());
    }

    public function test_public_key_returns_correct_path(): void
    {
        $expectedPath = dirname((new \ReflectionClass(RootCredential::class))->getFileName()).'/ssh_key.pub';
        $this->assertEquals($expectedPath, $this->credential->publicKey());
    }

    public function test_private_key_returns_correct_path(): void
    {
        $expectedPath = dirname((new \ReflectionClass(RootCredential::class))->getFileName()).'/ssh_key';
        $this->assertEquals($expectedPath, $this->credential->privateKey());
    }

    public function test_public_key_path_points_to_valid_location(): void
    {
        $path = $this->credential->publicKey();
        $this->assertStringContainsString('/app/Packages/Credentials/ssh_key.pub', $path);
    }

    public function test_private_key_path_points_to_valid_location(): void
    {
        $path = $this->credential->privateKey();
        $this->assertStringContainsString('/app/Packages/Credentials/ssh_key', $path);
    }

    public function test_all_methods_return_strings(): void
    {
        $this->assertIsString($this->credential->user());
        $this->assertIsString($this->credential->publicKey());
        $this->assertIsString($this->credential->privateKey());
    }
}
