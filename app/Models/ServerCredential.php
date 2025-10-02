<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * Server Credential Model
 *
 * Stores encrypted SSH credentials per server for different access types.
 * Each server can have multiple credential types (root, user, worker).
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
     * Generate a new SSH key pair for this credential.
     */
    public static function generateKeyPair(Server $server, string $credentialType): self
    {
        $tempDir = sys_get_temp_dir();
        $keyName = sprintf('server_%d_%s_%d', $server->id, $credentialType, time());
        $keyPath = $tempDir . '/' . $keyName;

        // Generate RSA 4096-bit key pair
        $command = sprintf(
            'ssh-keygen -t rsa -b 4096 -f %s -N "" -C "%s@server-%d"',
            escapeshellarg($keyPath),
            $credentialType,
            $server->id
        );

        exec($command, $output, $resultCode);

        if ($resultCode !== 0) {
            throw new \RuntimeException("Failed to generate SSH key pair for {$credentialType} credential");
        }

        // Read generated keys
        $privateKey = file_get_contents($keyPath);
        $publicKey = file_get_contents($keyPath . '.pub');

        // Clean up temporary files
        @unlink($keyPath);
        @unlink($keyPath . '.pub');

        // Create or update credential
        return self::updateOrCreate(
            [
                'server_id' => $server->id,
                'credential_type' => $credentialType,
            ],
            [
                'private_key' => trim($privateKey),
                'public_key' => trim($publicKey),
            ]
        );
    }
}
