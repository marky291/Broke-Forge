<?php

namespace Database\Factories;

use App\Models\Server;
use App\Packages\Enums\CredentialType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServerCredential>
 */
class ServerCredentialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Generate a simple test SSH key pair
        $privateKey = "-----BEGIN OPENSSH PRIVATE KEY-----\ntest-private-key\n-----END OPENSSH PRIVATE KEY-----";
        $publicKey = 'ssh-rsa test-public-key';

        return [
            'server_id' => Server::factory(),
            'credential_type' => CredentialType::Root->value,
            'private_key' => $privateKey,
            'public_key' => $publicKey,
        ];
    }

    public function root(): static
    {
        return $this->state(fn (array $attributes) => [
            'credential_type' => CredentialType::Root->value,
        ]);
    }

    public function brokeforge(): static
    {
        return $this->state(fn (array $attributes) => [
            'credential_type' => CredentialType::BrokeForge->value,
        ]);
    }
}
