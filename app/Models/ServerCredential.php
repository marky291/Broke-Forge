<?php

namespace App\Models;

use App\Packages\Credential\SshKeyGenerator;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * Server Credential Model
 *
 * Stores encrypted SSH credentials per server for different users.
 * Each server can have multiple SSH users (root, brokeforge).
 */
class ServerCredential extends Model
{
    use HasFactory;

    /**
     * Root user - for server-level operations requiring elevated privileges.
     * Used for: System package installation, service management, server configuration.
     */
    public const ROOT = 'root';

    /**
     * BrokeForge user - for site-level operations and Git management.
     * Used for: Site deployments, Git operations, application code management.
     * Has full permissions only on /home/brokeforge/ directory.
     */
    public const BROKEFORGE = 'brokeforge';

    protected $fillable = [
        'server_id',
        'user',
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
     * Get the SSH username for this credential.
     *
     * For our use case, the username IS the user field.
     * - 'root' user → 'root' username
     * - 'brokeforge' user → 'brokeforge' username
     */
    public function getUsername(): string
    {
        return $this->user;
    }

    /**
     * Generate a new SSH key pair for this credential.
     *
     * @param  Server  $server  The server to generate keys for
     * @param  string  $user  The SSH user ('root' or 'brokeforge')
     * @return self The created/updated credential
     */
    public static function generateKeyPair(Server $server, string $user): self
    {
        // Generate key pair using the service
        $generator = new SshKeyGenerator;
        $keys = $generator->generate($server->id, $user);

        // Create or update credential
        return self::updateOrCreate(
            [
                'server_id' => $server->id,
                'user' => $user,
            ],
            [
                'private_key' => $keys['private_key'],
                'public_key' => $keys['public_key'],
            ]
        );
    }
}
