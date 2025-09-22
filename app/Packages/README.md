# Package Guidelines

This document establishes rules and best practices for structuring packages within the `@app/Packages/` directory. All packages must follow these guidelines to ensure consistency, maintainability, and integration with the BrokeForge provisioning system.

## Package Architecture Overview

The package system is built on a layered architecture that provides a consistent interface for remote server management. **All package classes, including single command executors, must follow the same `execute()` and `commands()` method pattern.**

```
app/Packages/
├── Base/                    # Abstract base classes
│   ├── PackageInstaller.php    # Base installer class
│   ├── PackageRemover.php       # Base remover class
│   ├── PackageManager.php       # Core SSH and milestone functionality
│   └── Milestones.php           # Abstract milestone class
├── Contracts/               # Interfaces
│   ├── Installer.php           # Installation contract
│   └── Remover.php             # Removal contract
├── Credentials/             # SSH credential types
│   ├── RootCredential.php      # Root user access
│   ├── UserCredential.php      # App user access
│   ├── WorkerCredential.php    # Worker user access
│   └── SshCredential.php       # Interface for all credentials
├── Enums/                   # Type definitions
│   ├── ServiceType.php         # Service categories
│   ├── ProvisionStatus.php     # Provision states
│   └── Connection.php          # Connection states
└── Services/                # Service implementations
    ├── {Category}/             # Service category (WebServer, Database, etc.)
    │   └── {ServiceName}/      # Specific service implementation
    │       ├── {Service}Installer.php
    │       ├── {Service}InstallerMilestones.php
    │       ├── {Service}InstallerJob.php
    │       ├── {Service}Remover.php (optional)
    │       └── {Service}RemoverMilestones.php (optional)
    └── Sites/                  # Special category for site management
        ├── SiteInstaller.php
        ├── SiteRemover.php
        └── ...
```

## Directory Structure Standards

### Service Organization

Services are organized hierarchically by category and specific implementation:

- **Category**: Broad service type (e.g., `WebServer`, `Database`, `Sites`)
- **ServiceName**: Specific implementation (e.g., `MySQL`, `Redis`, `GitRepository`)

**Examples:**
```
Services/WebServer/                    # Web server category
├── WebServiceInstaller.php           # NGINX + PHP installer
├── WebServiceInstallerMilestones.php # Progress tracking
└── WebServiceInstallerJob.php        # Queue job

Services/Database/MySQL/               # Database category, MySQL specific
├── MySqlInstaller.php
├── MySqlInstallerMilestones.php
├── MySqlRemover.php
└── MySqlRemoverMilestones.php

Services/Sites/                        # Site management category
├── SiteInstaller.php                  # Generic site provisioning
├── GitRepositoryInstaller.php        # Git-specific functionality
└── ...
```

### File Naming Conventions

**ALL packages must follow strict naming conventions:**

1. **Installer Classes**: `{PackageName}Installer.php` (e.g., `WebServiceInstaller.php`, `SiteCommandInstaller.php`)
2. **Remover Classes**: `{PackageName}Remover.php` (e.g., `WebServiceRemover.php`, `SiteCommandRemover.php`)
3. **Milestone Classes**: `{PackageName}{Action}Milestones.php` (e.g., `WebServiceInstallerMilestones.php`)
4. **Job Classes**: `{PackageName}{Action}Job.php` (e.g., `WebServiceInstallerJob.php`)

**Important:** Even single command executors must follow this pattern:
- ❌ Wrong: `SiteCommandExecutor.php`
- ✅ Correct: `SiteCommandInstaller.php`

All class names must use PascalCase and match their filename exactly.

## Code Review Process

### Before Creating Any New Package

**ALWAYS** review existing packages to understand established patterns and avoid duplication:

1. **Examine Similar Packages**: Look at `WebServiceInstaller`, `MySqlInstaller`, `SiteInstaller`, etc.
2. **Check Base Classes**: Review `PackageInstaller`, `PackageRemover`, `PackageManager` capabilities
3. **Review Existing Credentials**: Use `RootCredential`, `UserCredential`, `WorkerCredential` before creating new ones
4. **Examine Milestone Patterns**: Look at existing milestone classes for consistent naming and structure
5. **Check Service Types**: Use existing `ServiceType` constants before adding new ones

### Questions to Ask Before Implementation

- Can I extend an existing base class instead of creating a new one?
- Does a similar package already exist that I can learn from or extend?
- Can I reuse existing SSH credentials instead of creating custom ones?
- Are there existing milestone patterns I should follow?
- Can I use existing enum values instead of creating new ones?

### Example: Reviewing Before Creating a Redis Installer

```bash
# Review existing database installers
ls app/Packages/Services/Database/
# MySQL/ (examine MySqlInstaller.php and MySqlInstallerMilestones.php)

# Check base classes
cat app/Packages/Base/PackageInstaller.php

# Review service types
cat app/Packages/Enums/ServiceType.php
# Use ServiceType::DATABASE (already exists)

# Check credentials
ls app/Packages/Credentials/
# Use RootCredential (appropriate for system service installation)
```

### Leveraging Existing Patterns

```php
// ✅ GOOD: Reusing established patterns
class RedisInstaller extends PackageInstaller  // Existing base class
{
    protected function serviceType(): string
    {
        return ServiceType::DATABASE;  // Existing enum value
    }

    protected function sshCredential(): SshCredential
    {
        return new RootCredential;  // Existing credential class
    }

    // Follow existing milestone naming patterns from MySqlInstaller
    protected function milestones(): Milestones
    {
        return new RedisInstallerMilestones;
    }
}

// ❌ BAD: Creating unnecessary new patterns
class RedisInstaller extends CustomRedisBaseClass  // Unnecessary new base
{
    protected function serviceType(): string
    {
        return ServiceType::REDIS;  // Unnecessary new enum value
    }

    protected function sshCredential(): SshCredential
    {
        return new RedisCredential;  // Unnecessary new credential
    }
}
```

## Package Installer Implementation

### Required Structure

Every Package Installer must extend `PackageInstaller` and implement these abstract methods:

```php
<?php

namespace App\Packages\Services\{Category};

use App\Packages\Base\Milestones;
use App\Packages\Base\PackageInstaller;
use App\Packages\Credentials\SshCredential;

/**
 * {Service} Installation Class
 *
 * Brief description of what this installer does
 */
class {ServiceName}Installer extends PackageInstaller
{
    /**
     * Service type identifier for milestone tracking
     */
    protected function serviceType(): string
    {
        return ServiceType::{CATEGORY};
    }

    /**
     * Milestone implementation for progress tracking
     */
    protected function milestones(): Milestones
    {
        return new {ServiceName}InstallerMilestones;
    }

    /**
     * SSH credential type for remote execution
     */
    protected function sshCredential(): SshCredential
    {
        return new RootCredential; // or UserCredential/WorkerCredential
    }

    /**
     * Execute the installation process
     *
     * Each package can define its own execute method signature
     * based on its specific requirements. Common patterns:
     * - execute(): void (no arguments needed)
     * - execute(string $command, int $timeout = 120): array (command execution)
     * - execute(array $config): void (configuration-based)
     */
    public function execute(): void
    {
        // Default implementation - override as needed
        $this->install($this->commands());
    }

    /**
     * Generate SSH commands for installation
     *
     * Each package can define its own commands method signature
     * to match the arguments passed from execute()
     */
    protected function commands(): array
    {
        return [
            // Array of commands and milestone tracking
        ];
    }
}
```

### Implementation Guidelines

1. **Review Existing Code First**: Always examine existing packages to understand patterns and reuse existing solutions before creating anything new
2. **Avoid New Classes/Methods**: Do not create new methods or classes unless absolutely necessary - leverage existing base classes and patterns
3. **Constructor**: The base `PackageInstaller` constructor accepts a `Server $server` parameter - do not override unless necessary
4. **Execute Method**: Contains ALL installation logic including data preparation, validation, and calls `$this->install()` - accepts any arguments needed for configuration
5. **Commands Method**: Contains ONLY SSH commands and milestone tracking closures - accepts arguments for dynamic command generation
6. **Avoid Additional Methods**: Do not create helper methods - keep all logic within `execute()` and `commands()` methods
7. **Parameter Passing**: Use function parameters instead of constructors for package-specific configuration
8. **Error Handling**: Command failures automatically throw `RuntimeException`
9. **Logging**: All milestone tracking is automatically logged via the base class

### Command Array Structure

The `commands()` method must return an array containing:

```php
protected function commands(string $version = '8.3', array $config = []): array
{
    // Generate configuration content inline (avoid helper methods)
    $configContent = view('provision.service-config', [
        'version' => $version,
        'config' => $config
    ])->render();

    return [
        // Milestone tracking (closure)
        $this->track({ServiceName}InstallerMilestones::PREPARE_SYSTEM),

        // SSH commands (strings) - use parameters for dynamic values
        'DEBIAN_FRONTEND=noninteractive apt-get update -y',
        "systemctl enable --now service-name-{$version}",

        // Configuration generation (inline, no helper methods)
        "cat > /etc/service/config << 'EOF'\n{$configContent}\nEOF",

        // Database operations (closures) - use parameters in closures
        fn () => $this->server->services()->updateOrCreate([
            'configuration' => array_merge(['version' => $version], $config)
        ]),

        // Final milestone
        $this->track({ServiceName}InstallerMilestones::COMPLETE),
    ];
}
```

## Package Remover Implementation

Package Removers follow the same pattern but extend `PackageRemover`:

## Single Command Executors

**Before creating new command executors, review existing packages like `SiteInstaller`, `GitRepositoryInstaller`, etc. to understand established patterns and reuse existing solutions.**

Single command executors must follow the same naming convention: `{PackageName}Installer.php`. They must use the package pattern with `execute()` and `commands()` methods rather than standalone `run()` methods. **Extend existing base classes rather than creating new ones.**

### Correct Structure for Single Command Executors

```php
<?php

namespace App\Packages\Services\Sites;

use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Base\Milestones;
use App\Packages\Base\PackageInstaller;
use App\Packages\Credentials\SshCredential;
use App\Packages\Credentials\UserCredential;
use App\Packages\Enums\ServiceType;

/**
 * Site Command Installer
 *
 * Executes custom commands within site directories following package patterns
 */
class SiteCommandInstaller extends PackageInstaller
{
    protected ServerSite $site;

    public function __construct(Server $server, ServerSite $site)
    {
        parent::__construct($server);
        $this->site = $site;
    }

    protected function serviceType(): string
    {
        return ServiceType::SITE;
    }

    protected function milestones(): Milestones
    {
        return new SiteCommandInstallerMilestones;
    }

    protected function sshCredential(): SshCredential
    {
        return new UserCredential;
    }

    /**
     * Execute a custom command within the site directory
     */
    public function execute(string $command, int $timeout = 120): array
    {
        if (trim($command) === '') {
            throw new \RuntimeException('Cannot execute an empty command.');
        }

        $start = (int) (microtime(true) * 1000);

        try {
            $this->install($this->commands($command, $timeout));
            $duration = (int) (microtime(true) * 1000) - $start;

            return [
                'command' => $command,
                'output' => $this->commandOutput ?? '',
                'errorOutput' => $this->commandError ?? '',
                'exitCode' => 0,
                'ranAt' => now()->toIso8601String(),
                'durationMs' => $duration,
                'success' => true,
            ];
        } catch (\Exception $e) {
            $duration = (int) (microtime(true) * 1000) - $start;

            return [
                'command' => $command,
                'output' => '',
                'errorOutput' => $e->getMessage(),
                'exitCode' => 1,
                'ranAt' => now()->toIso8601String(),
                'durationMs' => $duration,
                'success' => false,
            ];
        }
    }

    protected function commands(string $command, int $timeout): array
    {
        $workingDirectory = $this->resolveWorkingDirectory();

        return [
            $this->track(SiteCommandInstallerMilestones::PREPARE_EXECUTION),

            // Capture command output for return
            function () use ($command, $workingDirectory, $timeout) {
                $remoteCommand = sprintf('cd %s && %s', escapeshellarg($workingDirectory), $command);

                $process = $this->ssh($this->sshCredential()->user(), $this->server->public_ip, $this->server->ssh_port)
                    ->disableStrictHostKeyChecking()
                    ->setTimeout($timeout)
                    ->execute($remoteCommand);

                // Store output for execute method to return
                $this->commandOutput = rtrim($process->getOutput());
                $this->commandError = rtrim($process->getErrorOutput());

                if (!$process->isSuccessful()) {
                    throw new \RuntimeException("Command failed with exit code {$process->getExitCode()}");
                }
            },

            $this->track(SiteCommandInstallerMilestones::COMMAND_COMPLETE),
        ];
    }

    protected function resolveWorkingDirectory(): string
    {
        if ($this->site->document_root) {
            return $this->site->document_root;
        }

        if ($this->site->domain) {
            return "/home/{$this->sshCredential()->user()}/{$this->site->domain}";
        }

        return "/home/{$this->sshCredential()->user()}/site-{$this->site->id}";
    }
}
```

### Milestone Class for Single Command Executors

```php
<?php

namespace App\Packages\Services\Sites;

use App\Packages\Base\Milestones;

class SiteCommandInstallerMilestones extends Milestones
{
    public const PREPARE_EXECUTION = 'prepare_execution';
    public const COMMAND_COMPLETE = 'command_complete';

    private const LABELS = [
        self::PREPARE_EXECUTION => 'Preparing command execution',
        self::COMMAND_COMPLETE => 'Command execution complete',
    ];

    public static function labels(): array
    {
        return self::LABELS;
    }

    public static function label(string $milestone): ?string
    {
        return self::LABELS[$milestone] ?? null;
    }

    public function countLabels(): int
    {
        return count(self::LABELS);
    }
}
```

### Why Single Command Executors Must Follow Package Patterns

1. **Consistency**: All packages use the same interface and patterns
2. **Progress Tracking**: Milestone tracking works across all package types
3. **Error Handling**: Consistent error handling and logging
4. **SSH Management**: Unified SSH credential and connection handling
5. **Testing**: Same testing patterns apply to all package types
6. **Maintainability**: Developers know what to expect from any package class

### Incorrect Pattern (Avoid)

```php
// ❌ WRONG - Incorrect naming and standalone run() method
class SiteCommandExecutor
{
    public function run(string $command): array
    {
        // Direct SSH execution without package patterns
    }
}
```

### Correct Pattern (Use This)

```php
// ✅ CORRECT - Proper naming and package pattern with execute() and commands()
class SiteCommandInstaller extends PackageInstaller
{
    public function execute(string $command): array
    {
        // Package execution logic
        $this->install($this->commands($command));
    }

    protected function commands(string $command): array
    {
        // SSH commands with milestone tracking
    }
}
```

## Package Remover Implementation

Package Removers follow the same pattern but extend `PackageRemover`:

```php
<?php

namespace App\Packages\Services\{Category};

use App\Packages\Base\Milestones;
use App\Packages\Base\PackageRemover;
use App\Packages\Credentials\SshCredential;

class {ServiceName}Remover extends PackageRemover
{
    protected function serviceType(): string
    {
        return ServiceType::{CATEGORY};
    }

    protected function milestones(): Milestones
    {
        return new {ServiceName}RemoverMilestones;
    }

    protected function sshCredential(): SshCredential
    {
        return new RootCredential;
    }

    public function execute(...$args): void
    {
        $this->remove($this->commands(...$args));
    }

    protected function commands(bool $keepConfig = false, array $options = []): array
    {
        return [
            $this->track({ServiceName}RemoverMilestones::STOP_SERVICE),
            'systemctl stop service-name',
            'systemctl disable service-name',

            $this->track({ServiceName}RemoverMilestones::REMOVE_FILES),
            $keepConfig ? 'echo "Keeping configuration files"' : 'rm -rf /etc/service-name/',
            'rm -rf /var/lib/service-name',

            $this->track({ServiceName}RemoverMilestones::COMPLETE),
        ];
    }
}
```

## Milestone System

### Milestone Class Structure

Each installer/remover requires a corresponding milestone class:

```php
<?php

namespace App\Packages\Services\{Category};

use App\Packages\Base\Milestones;

class {ServiceName}{Action}Milestones extends Milestones
{
    // Define milestone constants
    public const PREPARE_SYSTEM = 'prepare_system';
    public const INSTALL_SOFTWARE = 'install_software';
    public const CONFIGURE_SERVICE = 'configure_service';
    public const COMPLETE = 'complete';

    // Define human-readable labels
    private const LABELS = [
        self::PREPARE_SYSTEM => 'Preparing system',
        self::INSTALL_SOFTWARE => 'Installing software packages',
        self::CONFIGURE_SERVICE => 'Configuring service',
        self::COMPLETE => 'Installation complete',
    ];

    /**
     * Get all milestone labels
     */
    public static function labels(): array
    {
        return self::LABELS;
    }

    /**
     * Get label for specific milestone
     */
    public static function label(string $milestone): ?string
    {
        return self::LABELS[$milestone] ?? null;
    }

    /**
     * Count total milestones for progress calculation
     */
    public function countLabels(): int
    {
        return count(self::LABELS);
    }
}
```

### Milestone Naming Conventions

Use descriptive, action-oriented constant names:
- `PREPARE_SYSTEM` - System preparation tasks
- `INSTALL_SOFTWARE` - Package installation
- `CONFIGURE_SERVICE` - Service configuration
- `START_SERVICE` - Service startup
- `VERIFY_INSTALLATION` - Installation verification
- `COMPLETE` - Final completion milestone

### Progress Tracking Usage

Track milestones in your command array:

```php
$this->track({ServiceName}InstallerMilestones::PREPARE_SYSTEM),
'apt-get update -y',
'apt-get install -y prerequisites',

$this->track({ServiceName}InstallerMilestones::INSTALL_SOFTWARE),
'apt-get install -y main-package',
```

## SSH Credential Management

### Available Credential Types

1. **RootCredential**: Full system access for system-level operations
2. **UserCredential**: App user access for site and user-level operations
3. **WorkerCredential**: Limited access for specific worker tasks

### Credential Selection Guidelines

```php
// System services (MySQL, NGINX, etc.)
protected function sshCredential(): SshCredential
{
    return new RootCredential;
}

// Site operations (creating sites, managing files)
protected function sshCredential(): SshCredential
{
    return new UserCredential;
}

// Background tasks or limited operations
protected function sshCredential(): SshCredential
{
    return new WorkerCredential;
}
```

### Custom Credentials

If needed, implement the `SshCredential` interface:

```php
class CustomCredential implements SshCredential
{
    public function user(): string
    {
        return 'custom-user';
    }

    public function publicKey(): string
    {
        return __DIR__.'/custom_key.pub';
    }

    public function privateKey(): string
    {
        return __DIR__.'/custom_key';
    }
}
```

## Job Integration

### Job Class Structure

Every installer should have a corresponding job for queue processing:

```php
<?php

namespace App\Packages\Services\{Category};

use App\Models\Server;
use App\Models\ServerService;
use App\Packages\Enums\ProvisionStatus;
use App\Packages\Enums\ServiceType;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class {ServiceName}InstallerJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Server $server,
        public array $configuration = []
    ) {}

    public function handle(): void
    {
        Log::info("Starting {service} installation for server #{$this->server->id}");

        try {
            // Create installer instance
            $installer = new {ServiceName}Installer($this->server);

            // Create database service record
            $service = ServerService::updateOrCreate(
                [
                    'server_id' => $this->server->id,
                    'service_name' => '{service_name}',
                ],
                [
                    'service_type' => ServiceType::{CATEGORY},
                    'configuration' => $this->configuration,
                    'status' => 'installing',
                ]
            );

            // Execute installation with configuration parameters
            $installer->execute($this->configuration);

            // Update service status
            $service->status = 'active';
            $service->save();

            // Update server status if needed
            $this->server->provision_status = ProvisionStatus::Completed;
            $this->server->save();

            Log::info("{Service} installation completed for server #{$this->server->id}");
        } catch (\Exception $e) {
            Log::error("{Service} installation failed for server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update service status
            if (isset($service)) {
                $service->status = 'failed';
                $service->save();
            }

            // Update server status
            $this->server->provision_status = ProvisionStatus::Failed;
            $this->server->save();

            throw $e;
        }
    }
}
```

### Job Integration Best Practices

1. **Error Handling**: Always wrap execution in try-catch blocks
2. **Database Updates**: Update `ServerService` and `Server` records appropriately
3. **Logging**: Log start, success, and failure events
4. **Status Management**: Use proper enum values for status updates
5. **Configuration**: Accept configuration parameters through constructor

## Service Type Classification

Use the `ServiceType` enum to categorize services:

```php
class ServiceType
{
    public const DATABASE = 'database';      // MySQL, PostgreSQL, Redis
    public const SERVER = 'server';          // System-level services
    public const WEBSERVER = 'webserver';    // NGINX, Apache, PHP
    public const SITE = 'site';              // Individual sites/applications
}
```

Choose the appropriate service type based on:
- **DATABASE**: Database engines and data storage services
- **SERVER**: System-level infrastructure services
- **WEBSERVER**: Web server and runtime environments
- **SITE**: Individual website or application provisioning

## Testing Requirements

### Testing Philosophy

**Avoid mocking unless absolutely necessary.** Only mock external systems that cannot be reliably tested, such as SSH connections to remote servers. Test all other functionality using real implementations.

### Unit Tests

Create PHPUnit tests for each installer using real database and model interactions:

```php
<?php

namespace Tests\Unit\Packages\Services\{Category};

use App\Models\Server;
use App\Packages\Services\{Category}\{ServiceName}Installer;
use Tests\TestCase;

class {ServiceName}InstallerTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_type_returns_correct_value(): void
    {
        $server = Server::factory()->create();
        $installer = new {ServiceName}Installer($server);

        $this->assertEquals(ServiceType::{CATEGORY}, $installer->serviceType());
    }

    public function test_milestone_class_is_instantiated(): void
    {
        $server = Server::factory()->create();
        $installer = new {ServiceName}Installer($server);

        $milestones = $installer->milestones();

        $this->assertInstanceOf({ServiceName}InstallerMilestones::class, $milestones);
    }

    public function test_commands_array_contains_expected_structure(): void
    {
        $server = Server::factory()->create();
        $installer = new {ServiceName}Installer($server);

        $commands = $installer->commands('8.3', ['option' => 'value']);

        $this->assertNotEmpty($commands);
        $this->assertIsArray($commands);

        // Test for milestone tracking closures
        $closureCount = 0;
        foreach ($commands as $command) {
            if ($command instanceof \Closure) {
                $closureCount++;
            }
        }
        $this->assertGreaterThan(0, $closureCount, 'Commands should contain milestone tracking closures');
    }

    public function test_ssh_credential_returns_expected_type(): void
    {
        $server = Server::factory()->create();
        $installer = new {ServiceName}Installer($server);

        $credential = $installer->sshCredential();

        $this->assertInstanceOf(SshCredential::class, $credential);
    }
}
```

### Feature Tests

Test job integration and database interactions using real implementations, only mocking SSH execution:

```php
<?php

namespace Tests\Feature\Packages\Services\{Category};

use App\Models\Server;
use App\Models\ServerService;
use App\Packages\Services\{Category}\{ServiceName}InstallerJob;
use Illuminate\Support\Facades\Queue;
use Spatie\Ssh\Ssh;
use Tests\TestCase;

class {ServiceName}InstallerJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_creates_service_record_with_real_database(): void
    {
        // Use real server and database - no mocking
        $server = Server::factory()->create([
            'public_ip' => '192.168.1.100',
            'ssh_port' => 22,
        ]);

        $job = new {ServiceName}InstallerJob($server, ['version' => '8.3']);

        // Only mock SSH execution (external system we can't control)
        $this->mock(Ssh::class, function ($mock) {
            $mock->shouldReceive('create')->andReturnSelf();
            $mock->shouldReceive('disableStrictHostKeyChecking')->andReturnSelf();
            $mock->shouldReceive('execute')->andReturnSelf();
            $mock->shouldReceive('isSuccessful')->andReturn(true);
        });

        $job->handle();

        // Test real database interactions
        $this->assertDatabaseHas('server_services', [
            'server_id' => $server->id,
            'service_name' => '{service_name}',
            'status' => 'active',
        ]);

        // Test server model updates
        $server->refresh();
        $this->assertEquals('completed', $server->provision_status);
    }

    public function test_job_handles_ssh_failure_correctly(): void
    {
        $server = Server::factory()->create();
        $job = new {ServiceName}InstallerJob($server);

        // Mock SSH failure scenario
        $this->mock(Ssh::class, function ($mock) {
            $mock->shouldReceive('create')->andReturnSelf();
            $mock->shouldReceive('disableStrictHostKeyChecking')->andReturnSelf();
            $mock->shouldReceive('execute')->andReturnSelf();
            $mock->shouldReceive('isSuccessful')->andReturn(false);
        });

        $this->expectException(\RuntimeException::class);

        $job->handle();

        // Verify failure is recorded in real database
        $service = ServerService::where('server_id', $server->id)->first();
        $this->assertEquals('failed', $service?->status);
    }
}
```

### Milestone Testing

Test milestone functionality using real database interactions:

```php
public function test_milestone_tracking_creates_provision_events(): void
{
    $server = Server::factory()->create();
    $installer = new {ServiceName}Installer($server);

    // Mock only SSH execution
    $this->mock(Ssh::class, function ($mock) {
        $mock->shouldReceive('create')->andReturnSelf();
        $mock->shouldReceive('disableStrictHostKeyChecking')->andReturnSelf();
        $mock->shouldReceive('execute')->andReturnSelf();
        $mock->shouldReceive('isSuccessful')->andReturn(true);
    });

    $installer->execute(['version' => '8.3']);

    // Test real ProvisionEvent creation
    $this->assertDatabaseHas('provision_events', [
        'server_id' => $server->id,
        'service_type' => ServiceType::{CATEGORY},
        'provision_type' => 'install',
    ]);
}
```

### What to Mock vs. What to Test with Real Implementations

**✅ Use Real Implementations For:**
- Database operations and Eloquent models
- Laravel framework features (views, config, etc.)
- Internal application logic
- Milestone tracking and ProvisionEvent creation
- Server and ServerService model updates
- Queue job logic and state management

**⚠️ Only Mock When Absolutely Necessary:**
- **SSH connections and remote command execution** (external systems)
- **External API calls** (third-party services)
- **File system operations on remote servers** (can't control environment)
- **Network-dependent operations** (unreliable in test environment)

## Configuration File Generation

### Using Blade Templates for Clean Implementation

For configuration files, use Blade templates to maintain clean and maintainable code. Generate content directly in the `commands()` method using the `view()` helper:

```php
protected function commands(string $phpVersion, string $phpPackages): array
{
    // Get user credential for template variables
    $userCredential = new UserCredential;
    $appUser = $userCredential->user();

    // Generate nginx configuration using existing Blade template
    $nginxConfig = view('nginx.default', [
        'appUser' => $appUser,
        'phpVersion' => $phpVersion,
    ])->render();

    return [
        $this->track(WebServiceInstallerMilestones::CONFIGURE_NGINX),

        // Write config file using rendered Blade template
        "cat > /etc/nginx/sites-available/default << 'EOF'\n{$nginxConfig}\nEOF",

        // Continue with other commands...
    ];
}
```

### Real Example: Nginx Default Configuration

The existing `resources/views/nginx/default.blade.php` demonstrates this pattern:

```nginx
server {
    listen 80 default_server;
    listen [::]:80 default_server;

    server_name _;

    root /home/{{ $appUser }}/default/public;
    index index.php index.html index.htm;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php{{ $phpVersion }}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    error_log /var/log/nginx/default-error.log;
    access_log /var/log/nginx/default-access.log;
}
```

### Template Organization

Store Blade templates in organized directories under `resources/views/`:
```
resources/views/
├── nginx/
│   ├── default.blade.php          # Default site configuration
│   └── site.blade.php             # Generic site configuration
├── provision/
│   └── default-site.blade.php     # Default site content
└── mysql/
    └── config.blade.php           # MySQL configuration templates
```

### Benefits of Blade Templates

1. **Separation of Concerns**: Configuration logic separated from provisioning logic
2. **Maintainability**: Easy to update configurations without touching package code
3. **Reusability**: Templates can be shared across multiple packages
4. **Version Control**: Configuration changes are tracked separately
5. **Syntax Highlighting**: Better IDE support for configuration files

## Error Handling and Logging

### Automatic Error Handling

The base `PackageManager` class automatically:
- Throws `RuntimeException` for failed SSH commands
- Logs failed commands with server context
- Provides detailed error information

### Custom Error Handling

For complex operations, implement custom error handling:

```php
protected function commands(): array
{
    return [
        $this->track(Milestones::CUSTOM_OPERATION),

        // Custom error handling for complex operations
        function () {
            try {
                // Complex operation
                $this->performComplexOperation();
            } catch (\Exception $e) {
                Log::error("Custom operation failed", [
                    'server_id' => $this->server->id,
                    'error' => $e->getMessage(),
                ]);
                throw new \RuntimeException("Custom operation failed: {$e->getMessage()}");
            }
        },
    ];
}
```

## Real-World Example: Proper Method Usage

The `WebServiceInstaller` demonstrates the correct pattern of keeping logic within the recommended methods:

```php
// In WebServiceInstaller.php
public function execute(): void
{
    // ALL data preparation and logic goes in execute()
    $phpService = $this->server->services()->where('service_name', 'php')->latest('id')->first();
    $phpVersion = $phpService->configuration['version'];

    // Compose PHP packages inline (no helper methods)
    $phpPackages = implode(' ', [
        "php{$phpVersion}-fpm",
        "php{$phpVersion}-cli",
        "php{$phpVersion}-common",
        // ... more packages
    ]);

    // Pass all required data to commands method
    $this->install($this->commands($phpVersion, $phpPackages));
}

protected function commands(string $phpVersion, string $phpPackages): array
{
    // Get user credential inline (avoid helper methods)
    $userCredential = new UserCredential;
    $appUser = $userCredential->user();

    // Generate nginx config using Blade template (clean & maintainable)
    $nginxConfig = view('nginx.default', [
        'appUser' => $appUser,
        'phpVersion' => $phpVersion,
    ])->render();

    return [
        $this->track(WebServiceInstallerMilestones::PREPARE_SYSTEM),
        'DEBIAN_FRONTEND=noninteractive apt-get update -y',

        $this->track(WebServiceInstallerMilestones::INSTALL_SOFTWARE),
        "DEBIAN_FRONTEND=noninteractive apt-get install -y nginx {$phpPackages}",

        $this->track(WebServiceInstallerMilestones::CONFIGURE_NGINX),
        "cat > /etc/nginx/sites-available/default << 'EOF'\n{$nginxConfig}\nEOF",

        $this->track(WebServiceInstallerMilestones::ENABLE_SERVICES),
        "systemctl enable --now php{$phpVersion}-fpm",

        // Database operations inline
        fn () => $this->server->sites()->updateOrCreate(
            ['domain' => 'default'],
            [
                'document_root' => "/home/{$appUser}/default",
                'php_version' => $phpVersion,
                'status' => 'active',
            ]
        ),
    ];
}
```

## Best Practices Summary

1. **Review Existing Code First**: Always examine existing packages (`WebServiceInstaller`, `SiteInstaller`, etc.) to understand patterns and reuse solutions before creating anything new
2. **Avoid Creating New Classes/Methods**: Do not create new methods or classes unless absolutely necessary - leverage existing base classes and established patterns
3. **Follow Naming Conventions**: Use consistent naming for classes and files
4. **Implement All Required Methods**: Every package must implement the abstract methods (`execute()`, `commands()`, etc.)
5. **Use Package Patterns Universally**: ALL packages, including single command executors, must follow the `execute()` and `commands()` pattern - no standalone `run()` methods
6. **Use Parameters, Not Constructors**: Pass configuration via `execute()` and `commands()` parameters
7. **Avoid Additional Methods**: Keep ALL logic within `execute()` and `commands()` methods - no helper methods
8. **Use Appropriate Credentials**: Choose the right SSH credential type for the task
9. **Track Progress**: Implement comprehensive milestone tracking for all package types
10. **Handle Errors Gracefully**: Use try-catch blocks and proper logging
11. **Test Thoroughly**: Write both unit and feature tests
12. **Document Well**: Use PHPDoc blocks to describe functionality
13. **Keep It Simple**: Break complex operations into smaller, manageable steps
14. **Use Enums**: Leverage type safety with enum constants
15. **Follow Laravel Conventions**: Use Laravel best practices throughout

## Integration with BrokeForge

### Server Model Integration

Packages automatically integrate with the `Server` model:
- Access server properties via `$this->server`
- Update server status through the model
- Create related `ServerService` records

### Queue Integration

All package jobs should:
- Implement `ShouldQueue` interface
- Use `Queueable` trait
- Handle failures gracefully
- Update database records appropriately

### Frontend Integration

#### Progress Tracking with ProvisionEvent Model

The frontend should track package installation/removal progress by querying the `ProvisionEvent` model directly from the database. Each milestone tracked in packages automatically creates `ProvisionEvent` records.

**ProvisionEvent Model Structure:**
```php
// app/Models/ProvisionEvent.php
class ProvisionEvent extends Model
{
    protected $fillable = [
        'server_id',        // Server being provisioned
        'service_type',     // ServiceType enum value (database, webserver, etc.)
        'provision_type',   // 'install' or 'uninstall'
        'milestone',        // Milestone constant (e.g., 'install_software')
        'current_step',     // Current step number
        'total_steps',      // Total number of steps
        'details',          // Additional metadata (array)
    ];

    // Automatic progress percentage calculation
    public function getProgressPercentageAttribute(): float
    {
        return round(($this->current_step / $this->total_steps) * 100, 2);
    }

    // Helper methods
    public function isInstall(): bool;
    public function isUninstall(): bool;
}
```

**Frontend Progress Queries:**

```php
// Get latest progress for a specific service installation
$latestProgress = ProvisionEvent::where('server_id', $serverId)
    ->where('service_type', 'webserver')
    ->where('provision_type', 'install')
    ->latest()
    ->first();

$progressPercentage = $latestProgress?->progress_percentage ?? 0;
$currentMilestone = $latestProgress?->milestone;

// Get all progress events for detailed step-by-step display
$progressEvents = ProvisionEvent::where('server_id', $serverId)
    ->where('service_type', 'webserver')
    ->where('provision_type', 'install')
    ->orderBy('created_at')
    ->get();

// Check if installation is complete (last milestone)
$isComplete = ProvisionEvent::where('server_id', $serverId)
    ->where('service_type', 'webserver')
    ->where('provision_type', 'install')
    ->where('milestone', 'complete')
    ->exists();
```

**Inertia.js Frontend Implementation:**

```php
// In your controller
public function provisionStatus(Server $server)
{
    return Inertia::render('Server/ProvisionStatus', [
        'server' => $server,
        'progress' => ProvisionEvent::where('server_id', $server->id)
            ->latest()
            ->first(),
        'allEvents' => ProvisionEvent::where('server_id', $server->id)
            ->orderBy('created_at')
            ->get(),
    ]);
}
```

```typescript
// React component for progress tracking
interface ProvisionEvent {
    id: number;
    service_type: string;
    provision_type: 'install' | 'uninstall';
    milestone: string;
    current_step: number;
    total_steps: number;
    progress_percentage: number;
    created_at: string;
}

function ProvisionProgress({ server, progress, allEvents }: Props) {
    return (
        <div>
            {progress && (
                <div>
                    <div>Progress: {progress.progress_percentage}%</div>
                    <div>Current Step: {progress.milestone}</div>
                    <div>Step {progress.current_step} of {progress.total_steps}</div>
                </div>
            )}

            <div>
                {allEvents.map(event => (
                    <div key={event.id}>
                        ✓ {event.milestone} ({event.created_at})
                    </div>
                ))}
            </div>
        </div>
    );
}
```

**Real-time Updates with Polling:**

```typescript
// Use Inertia's polling feature for real-time progress
import { router } from '@inertiajs/react';

useEffect(() => {
    const interval = setInterval(() => {
        // Only poll if installation is in progress
        if (progress && progress.progress_percentage < 100) {
            router.reload({ only: ['progress', 'allEvents'] });
        }
    }, 2000); // Poll every 2 seconds

    return () => clearInterval(interval);
}, [progress]);
```

**Benefits of Database-Driven Progress Tracking:**
- **Persistent**: Progress survives page refreshes and browser restarts
- **Accurate**: Direct reflection of actual package execution progress
- **Detailed**: Step-by-step milestone tracking with timestamps
- **Flexible**: Easy to query for different views (summary, detailed, filtered)
- **Reliable**: No dependency on WebSocket connections or external state

This documentation ensures all future packages maintain consistency with the established BrokeForge architecture while providing clear guidelines for implementation and testing.