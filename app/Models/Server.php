<?php

namespace App\Models;

use App\Support\ServerCredentials;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

/**
 * @method static firstOrCreate(array $attributes, array $values = [])
 *
 * @property string $name
 * @property string $public_ip
 * @property int $ssh_port
 * @property string|null $private_ip
 * @property string|null $ssh_root_user
 * @property string|null $ssh_app_user
 * @property string $connection
 * @property-read User|null $user
 */
class Server extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'vanity_name',
        'public_ip',
        'private_ip',
        'ssh_port',
        'ssh_root_user',
        'ssh_app_user',
        'connection',
        'server_type',
        'provision_status',
    ];

    protected $attributes = [
        'ssh_port' => 22,
    ];

    protected $casts = [
        'ssh_port' => 'integer',
        'server_type' => \App\Provision\Enums\ServerType::class,
        'provision_status' => \App\Provision\Enums\ProvisionStatus::class,
    ];

    public static function register(string $user, string $publicIp): self
    {
        return static::firstOrCreate(
            ['public_ip' => $publicIp],
            [
                'ssh_app_user' => $user,
                'ssh_root_user' => 'root',
            ]
        );
    }

    public function services(): HasMany
    {
        return $this->hasMany(ServerService::class);
    }

    /**
     * Link the server back to its owning user for authorization concerns.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    /**
     * Get all provision events for the server.
     */
    public function provisionEvents(): HasMany
    {
        return $this->hasMany(ProvisionEvent::class);
    }

    /**
     * Check if server provisioning is complete.
     */
    public function isProvisioned(): bool
    {
        return $this->provision_status === \App\Provision\Enums\ProvisionStatus::Completed;
    }

    protected static function booted(): void
    {
        static::created(function (self $server): void {
            Activity::create([
                'type' => 'server.created',
                'description' => sprintf('Server %s created', $server->vanity_name ?? $server->public_ip),
                'causer_id' => Auth::id(),
                'subject_type' => self::class,
                'subject_id' => $server->id,
                'properties' => [
                    'vanity_name' => $server->vanity_name,
                    'public_ip' => $server->public_ip,
                    'private_ip' => $server->private_ip,
                ],
            ]);
        });

        static::deleted(function (self $server): void {
            ServerCredentials::forgetRootPassword($server);
        });
    }
}
