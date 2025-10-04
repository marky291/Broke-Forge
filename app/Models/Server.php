<?php

namespace App\Models;

use App\Enums\MonitoringStatus;
use App\Enums\SchedulerStatus;
use App\Packages\Enums\CredentialType;
use App\Packages\Enums\ProvisionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;

/**
 * @method static firstOrCreate(array $attributes, array $values = [])
 *
 * @property string $name
 * @property string $public_ip
 * @property int $ssh_port
 * @property string|null $private_ip
 * @property string $connection
 * @property string $vanity_name
 * @property ProvisionStatus $provision_status
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
        'connection',
        'provision_status',
        'ssh_root_password',
        'monitoring_token',
        'monitoring_status',
        'monitoring_collection_interval',
        'monitoring_installed_at',
        'monitoring_uninstalled_at',
        'scheduler_token',
        'scheduler_status',
        'scheduler_installed_at',
        'scheduler_uninstalled_at',
    ];

    protected $attributes = [
        'ssh_port' => 22,
    ];

    protected function casts(): array
    {
        return [
            'ssh_port' => 'integer',
            'provision_status' => ProvisionStatus::class,
            'ssh_root_password' => 'encrypted',
            'monitoring_token' => 'encrypted',
            'monitoring_status' => MonitoringStatus::class,
            'monitoring_collection_interval' => 'integer',
            'monitoring_installed_at' => 'datetime',
            'monitoring_uninstalled_at' => 'datetime',
            'scheduler_token' => 'encrypted',
            'scheduler_status' => SchedulerStatus::class,
            'scheduler_installed_at' => 'datetime',
            'scheduler_uninstalled_at' => 'datetime',
        ];
    }

    /**
     * Generate a unique monitoring token for API authentication
     */
    public function generateMonitoringToken(): string
    {
        $token = bin2hex(random_bytes(config('monitoring.token_length')));
        $this->update(['monitoring_token' => $token]);

        return $token;
    }

    /**
     * Generate a unique scheduler token for API authentication
     */
    public function generateSchedulerToken(): string
    {
        $token = bin2hex(random_bytes(config('scheduler.token_length')));
        $this->update(['scheduler_token' => $token]);

        return $token;
    }

    public static function register(string $publicIp): self
    {
        return static::firstOrCreate(
            ['public_ip' => $publicIp]
        );
    }

    /**
     * Check if the server is connected.
     */
    public function isConnected(): bool
    {
        return $this->connection === 'connected';
    }

    /**
     * Check if the server is deleted.
     */
    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
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
        return $this->hasMany(ServerSite::class);
    }

    /**
     * Get all SSH credentials for this server.
     */
    public function credentials(): HasMany
    {
        return $this->hasMany(ServerCredential::class);
    }

    /**
     * Get a specific credential type for this server.
     *
     * @param  CredentialType|string  $type  The credential type
     * @return ServerCredential|null The credential or null if not found
     */
    public function credential(CredentialType|string $type): ?ServerCredential
    {
        $credentialType = is_string($type) ? $type : $type->value;

        return $this->credentials()->where('credential_type', $credentialType)->first();
    }

    /**
     * Get the SSH username for a specific credential type.
     *
     * @param  CredentialType  $type  The credential type
     * @return string The SSH username
     */
    public function getUsernameFor(CredentialType $type): string
    {
        return $type->username();
    }

    /**
     * Create an authenticated SSH connection using server-specific credentials.
     *
     * @param  CredentialType|string  $credentialType  The credential type
     * @return \Spatie\Ssh\Ssh Configured SSH connection
     *
     * @throws \RuntimeException If credential not found or connection cannot be created
     */
    public function createSshConnection(CredentialType|string $credentialType): \Spatie\Ssh\Ssh
    {
        // Convert string to enum if necessary
        $type = is_string($credentialType) ? CredentialType::fromString($credentialType) : $credentialType;

        // Use the connection builder service
        $builder = new \App\Packages\Services\Credential\SshConnectionBuilder;

        return $builder->build($this, $type);
    }

    /**
     * Get all events for the server.
     */
    public function events(): HasMany
    {
        return $this->hasMany(ServerEvent::class);
    }

    public function firewall(): HasOne
    {
        return $this->hasOne(ServerFirewall::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(ServerMetric::class);
    }

    public function scheduledTasks(): HasMany
    {
        return $this->hasMany(ServerScheduledTask::class);
    }

    public function scheduledTaskRuns(): HasMany
    {
        return $this->hasMany(ServerScheduledTaskRun::class);
    }

    public function databases(): HasMany
    {
        return $this->hasMany(ServerDatabase::class);
    }

    public function phps(): HasMany
    {
        return $this->hasMany(ServerPhp::class);
    }

    public function defaultPhp(): HasOne
    {
        return $this->hasOne(ServerPhp::class)->where('is_cli_default', true);
    }

    public function reverseProxy(): HasOne
    {
        return $this->hasOne(ServerReverseProxy::class);
    }

    /**
     * Check if server provisioning is complete.
     */
    public function isProvisioned(): bool
    {
        return $this->provision_status === ProvisionStatus::Completed;
    }

    /**
     * Check if scheduler is active
     */
    public function schedulerIsActive(): bool
    {
        return $this->scheduler_status === SchedulerStatus::Active;
    }

    /**
     * Check if scheduler is installing
     */
    public function schedulerIsInstalling(): bool
    {
        return $this->scheduler_status === SchedulerStatus::Installing;
    }

    /**
     * Check if scheduler failed
     */
    public function schedulerIsFailed(): bool
    {
        return $this->scheduler_status === SchedulerStatus::Failed;
    }

    /**
     * Check if monitoring is active
     */
    public function monitoringIsActive(): bool
    {
        return $this->monitoring_status === MonitoringStatus::Active;
    }

    /**
     * Check if monitoring is installing
     */
    public function monitoringIsInstalling(): bool
    {
        return $this->monitoring_status === MonitoringStatus::Installing;
    }

    /**
     * Check if monitoring failed
     */
    public function monitoringIsFailed(): bool
    {
        return $this->monitoring_status === MonitoringStatus::Failed;
    }

    protected static function booted(): void
    {
        static::creating(function (self $server): void {
            if (empty($server->ssh_root_password)) {
                $server->ssh_root_password = self::generatePassword();
            }
        });

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
    }

    protected static function generatePassword(int $length = 24): string
    {
        // Limit to URL-safe characters to make display and copy simple.
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $alphabetLength = strlen($alphabet) - 1;

        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, $alphabetLength)];
        }

        return $password;
    }
}
