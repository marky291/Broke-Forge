<?php

namespace App\Models;

use App\Enums\ServerProvider;
use App\Enums\TaskStatus;
use App\Packages\Credential\Ssh;
use App\Packages\Services\SourceProvider\ServerSshKeyManager;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @method static firstOrCreate(array $attributes, array $values = [])
 *
 * @property string $name
 * @property string $public_ip
 * @property int $ssh_port
 * @property string|null $private_ip
 * @property string $connection
 * @property string $vanity_name
 * @property TaskStatus $provision_status
 * @property \Illuminate\Support\Collection|null $provision
 * @property int $id
 * @property TaskStatus|mixed $connection_status
 */
class Server extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'vanity_name',
        'provider',
        'public_ip',
        'private_ip',
        'ssh_port',
        'connection_status',
        'provision_status',
        'provision',
        'ssh_root_password',
        'os_name',
        'os_version',
        'os_codename',
        'monitoring_token',
        'monitoring_status',
        'monitoring_collection_interval',
        'monitoring_installed_at',
        'monitoring_uninstalled_at',
        'scheduler_token',
        'scheduler_status',
        'scheduler_installed_at',
        'scheduler_uninstalled_at',
        'supervisor_status',
        'supervisor_installed_at',
        'supervisor_uninstalled_at',
        'source_provider_ssh_key_added',
        'source_provider_ssh_key_id',
        'source_provider_ssh_key_title',
        'add_ssh_key_to_github',
    ];

    protected $attributes = [
        'ssh_port' => 22,
        'provision' => '{"1":"installing"}',
    ];

    protected function casts(): array
    {
        return [
            'ssh_port' => 'integer',
            'connection_status' => TaskStatus::class, // Only uses: Pending (initial), Success (connected), Failed (connection error)
            'provider' => ServerProvider::class,
            'provision_status' => TaskStatus::class,
            'provision' => AsCollection::class,
            'ssh_root_password' => 'encrypted',
            'monitoring_token' => 'encrypted',
            'monitoring_status' => TaskStatus::class,
            'monitoring_collection_interval' => 'integer',
            'monitoring_installed_at' => 'datetime',
            'monitoring_uninstalled_at' => 'datetime',
            'scheduler_token' => 'encrypted',
            'scheduler_status' => TaskStatus::class,
            'scheduler_installed_at' => 'datetime',
            'scheduler_uninstalled_at' => 'datetime',
            'supervisor_status' => TaskStatus::class,
            'supervisor_installed_at' => 'datetime',
            'supervisor_uninstalled_at' => 'datetime',
            'source_provider_ssh_key_added' => 'boolean',
            'add_ssh_key_to_github' => 'boolean',
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
            ['public_ip' => $publicIp],
            ['vanity_name' => $publicIp]
        );
    }

    /**
     * Check if the server is connected.
     */
    public function isConnected(): bool
    {
        return $this->connection_status === TaskStatus::Success;
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
     * Create an authenticated SSH connection to this server.
     *
     * @param  string  $user  The SSH user ('root' or 'brokeforge')
     * @return \Spatie\Ssh\Ssh Configured SSH connection ready to execute commands
     *
     * @throws \RuntimeException If credential not found
     */
    public function ssh(string $user = 'root'): \Spatie\Ssh\Ssh
    {
        return Ssh::connect($this, $user);
    }

    /**
     * Detect and update server OS information
     */
    public function detectOsInfo(): bool
    {
        try {
            $ssh = $this->ssh('root');

            // Get OS information using lsb_release
            $result = $ssh->execute('lsb_release -a 2>/dev/null');

            if (! $result->isSuccessful()) {
                // Fallback to /etc/os-release
                $result = $ssh->execute('cat /etc/os-release');
            }

            $output = $result->getOutput();

            // Parse lsb_release output
            preg_match('/Distributor ID:\s*(.+)/i', $output, $nameMatch);
            preg_match('/Release:\s*(.+)/i', $output, $versionMatch);
            preg_match('/Codename:\s*(.+)/i', $output, $codenameMatch);

            // If lsb_release didn't work, try /etc/os-release format
            if (empty($nameMatch)) {
                preg_match('/^NAME="?([^"\n]+)"?/m', $output, $nameMatch);
                preg_match('/^VERSION_ID="?([^"\n]+)"?/m', $output, $versionMatch);
                preg_match('/^VERSION_CODENAME="?([^"\n]+)"?/m', $output, $codenameMatch);
            }

            $this->update([
                'os_name' => trim($nameMatch[1] ?? ''),
                'os_version' => trim($versionMatch[1] ?? ''),
                'os_codename' => trim($codenameMatch[1] ?? ''),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::warning("Failed to detect OS info for server #{$this->id}: {$e->getMessage()}");

            return false;
        }
    }

    public function firewall(): HasOne
    {
        return $this->hasOne(ServerFirewall::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(ServerMetric::class);
    }

    public function monitors(): HasMany
    {
        return $this->hasMany(ServerMonitor::class);
    }

    public function scheduledTasks(): HasMany
    {
        return $this->hasMany(ServerScheduledTask::class);
    }

    public function scheduledTaskRuns(): HasMany
    {
        return $this->hasMany(ServerScheduledTaskRun::class);
    }

    public function supervisorTasks(): HasMany
    {
        return $this->hasMany(ServerSupervisorTask::class);
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
        return $this->provision_status === TaskStatus::Success;
    }

    /**
     * Check if scheduler is active
     */
    public function schedulerIsActive(): bool
    {
        return $this->scheduler_status === TaskStatus::Active;
    }

    /**
     * Check if scheduler is installing
     */
    public function schedulerIsInstalling(): bool
    {
        return $this->scheduler_status === TaskStatus::Installing;
    }

    /**
     * Check if scheduler failed
     */
    public function schedulerIsFailed(): bool
    {
        return $this->scheduler_status === TaskStatus::Failed;
    }

    /**
     * Check if monitoring is active
     */
    public function monitoringIsActive(): bool
    {
        return $this->monitoring_status === TaskStatus::Active;
    }

    /**
     * Check if monitoring is installing
     */
    public function monitoringIsInstalling(): bool
    {
        return $this->monitoring_status === TaskStatus::Installing;
    }

    /**
     * Check if monitoring failed
     */
    public function monitoringIsFailed(): bool
    {
        return $this->monitoring_status === TaskStatus::Failed;
    }

    /**
     * Check if supervisor is active
     */
    public function supervisorIsActive(): bool
    {
        return $this->supervisor_status === TaskStatus::Active;
    }

    /**
     * Check if supervisor is installing
     */
    public function supervisorIsInstalling(): bool
    {
        return $this->supervisor_status === TaskStatus::Installing;
    }

    /**
     * Check if supervisor failed
     */
    public function supervisorIsFailed(): bool
    {
        return $this->supervisor_status === TaskStatus::Failed;
    }

    protected static function booted(): void
    {
        static::creating(function (self $server): void {
            if (empty($server->ssh_root_password)) {
                $server->ssh_root_password = self::generatePassword();
            }
        });

        static::created(function (self $server): void {
            activity()
                ->causedBy(Auth::user())
                ->performedOn($server)
                ->event('server.created')
                ->withProperties([
                    'vanity_name' => $server->vanity_name,
                    'public_ip' => $server->public_ip,
                    'private_ip' => $server->private_ip,
                ])
                ->log(sprintf('Server %s created', $server->vanity_name ?? $server->public_ip));
        });

        static::updated(function (self $server): void {
            // Only broadcast if meaningful fields changed
            $broadcastFields = [
                'provision',
                'provision_status',
                'connection_status',
                'monitoring_status',
                'scheduler_status',
                'supervisor_status',
                'os_name',
                'os_version',
                'os_codename',
            ];

            if ($server->wasChanged($broadcastFields)) {
                \App\Events\ServerUpdated::dispatch($server->id);
            }
        });

        static::deleting(function (self $server): void {
            // Only attempt removal if key was added to GitHub
            if (! $server->source_provider_ssh_key_added) {
                return;
            }

            // Get user's GitHub provider
            $githubProvider = $server->user?->githubProvider();
            if (! $githubProvider) {
                return; // User doesn't have GitHub connected
            }

            try {
                $keyManager = new ServerSshKeyManager($server, $githubProvider);
                $keyManager->removeServerKeyFromGitHub();

                Log::info('Removed server SSH key from GitHub during deletion', [
                    'server_id' => $server->id,
                    'key_id' => $server->source_provider_ssh_key_id,
                ]);
            } catch (\Exception $e) {
                // Log error but don't block server deletion
                Log::warning('Failed to remove server SSH key from GitHub during deletion', [
                    'server_id' => $server->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        static::deleted(function (self $server): void {
            activity()
                ->causedBy(Auth::user())
                ->performedOn($server)
                ->event('server.deleted')
                ->withProperties([
                    'vanity_name' => $server->vanity_name,
                    'public_ip' => $server->public_ip,
                    'ssh_key_was_on_github' => $server->source_provider_ssh_key_added,
                ])
                ->log(sprintf('Server %s deleted', $server->vanity_name ?? $server->public_ip));
        });
    }

    public static function generatePassword(int $length = 24): string
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
