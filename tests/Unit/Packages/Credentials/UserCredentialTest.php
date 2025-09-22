<?php

namespace Tests\Unit\Packages\Credentials;

use App\Packages\Credentials\SshCredential;
use App\Packages\Credentials\UserCredential;
use Tests\TestCase;

class UserCredentialTest extends TestCase
{
    private UserCredential $credential;

    protected function setUp(): void
    {
        parent::setUp();
        $this->credential = new UserCredential;
    }

    public function test_implements_ssh_credential_interface(): void
    {
        $this->assertInstanceOf(SshCredential::class, $this->credential);
    }

    public function test_user_returns_slugified_app_name(): void
    {
        config(['app.name' => 'Test Application']);
        $this->assertEquals('test-application', $this->credential->user());
    }

    public function test_user_handles_special_characters(): void
    {
        config(['app.name' => 'Test@App#123']);
        $this->assertEquals('test-at-app123', $this->credential->user());
    }

    public function test_user_handles_uppercase_characters(): void
    {
        config(['app.name' => 'UPPERCASE_APP']);
        $this->assertEquals('uppercase-app', $this->credential->user());
    }

    public function test_user_handles_spaces(): void
    {
        config(['app.name' => 'App With Spaces']);
        $this->assertEquals('app-with-spaces', $this->credential->user());
    }

    public function test_public_key_returns_correct_path(): void
    {
        $expectedPath = dirname((new \ReflectionClass(UserCredential::class))->getFileName()).'/Keys/ssh_key.pub';
        $this->assertEquals($expectedPath, $this->credential->publicKey());
    }

    public function test_private_key_returns_correct_path(): void
    {
        $expectedPath = dirname((new \ReflectionClass(UserCredential::class))->getFileName()).'/Keys/ssh_key';
        $this->assertEquals($expectedPath, $this->credential->privateKey());
    }

    public function test_public_key_path_points_to_keys_directory(): void
    {
        $path = $this->credential->publicKey();
        $this->assertStringContainsString('/app/Packages/Credentials/Keys/ssh_key.pub', $path);
    }

    public function test_private_key_path_points_to_keys_directory(): void
    {
        $path = $this->credential->privateKey();
        $this->assertStringContainsString('/app/Packages/Credentials/Keys/ssh_key', $path);
    }

    public function test_all_methods_return_strings(): void
    {
        $this->assertIsString($this->credential->user());
        $this->assertIsString($this->credential->publicKey());
        $this->assertIsString($this->credential->privateKey());
    }
}
