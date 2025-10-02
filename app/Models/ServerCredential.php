<?php

namespace App\Models;

use App\Packages\Enums\CredentialType;
use App\Packages\Services\Credential\SshKeyGenerator;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * Server Credential Model
 *
 * Stores encrypted SSH credentials per server for different access types.
 * Each server can have multiple credential types (root, brokeforge).
 */
class ServerCredential extends Model
{
    protected $fillable = [
        'server_id',
        'credential_type',
        'private_key',
        'public_key',
    ];

    /**
     * Encrypt private key when storing, decrypt when retrieving.
     */
    protected function privateKey(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Crypt::decryptString($value) : null,
            set: fn (?string $value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    /**
     * Get the server that owns this credential.
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Get the credential type as an enum.
     */
    public function type(): CredentialType
    {
        return CredentialType::from($this->credential_type);
    }

    /**
     * Get the SSH username for this credential type.
     */
    public function getUsername(): string
    {
        return $this->type()->username();
    }

    /**
     * Generate a new SSH key pair for this credential.
     *
     * @param  Server  $server  The server to generate keys for
     * @param  CredentialType|string  $credentialType  The credential type
     * @return self The created/updated credential
     */
    public static function generateKeyPair(Server $server, CredentialType|string $credentialType): self
    {
        // Convert string to enum if necessary
        $type = is_string($credentialType) ? CredentialType::fromString($credentialType) : $credentialType;

        // Generate key pair using the service
        $generator = new SshKeyGenerator;
        $keys = $generator->generate($server->id, $type);

        // Create or update credential
        return self::updateOrCreate(
            [
                'server_id' => $server->id,
                'credential_type' => $type->value,
            ],
            [
                'private_key' => $keys['private_key'],
                'public_key' => $keys['public_key'],
            ]
        );
    }
}
