# Package Guidelines

This document establishes rules and best practices for structuring packages within the `@app/Packages/` directory. All packages must follow these guidelines to ensure consistency, maintainability, and integration with the BrokeForge provisioning system.

## 🆕 NEW: Reverb Package Lifecycle Pattern (Mandatory for Real-Time Updates)

**⚠️ CRITICAL FOR NEW PACKAGES**: When building packages that require real-time status updates (firewall rules, scheduled tasks, SSL certificates, deployments, etc.), you **MUST** use the **Reverb Package Lifecycle** pattern:

1. **Create database record FIRST** with `status: 'pending'` before dispatching job
2. **Job receives record ID** (not data array) and manages status lifecycle: pending → installing → active/failed
3. **Model events automatically broadcast** changes via Laravel Reverb (never manually dispatch events)
4. **Frontend uses useEcho + router.reload()** to fetch fresh data when WebSocket events arrive

**Key principle:** Event-driven architecture where model changes automatically trigger broadcasts, and frontend fetches updated resource data via Inertia. **No polling required.**

**📖 Quick Links:**
- [Quick Decision Guide: Should You Use Reverb Package Lifecycle?](#-quick-decision-guide-should-you-use-reverb-package-lifecycle)
- [Rule 6: Reverb Package Lifecycle Pattern (Full Implementation)](#rule-6-reverb-package-lifecycle-pattern)
- [Complete Implementation Steps](#implementation-steps)
- [Testing the Reverb Package Lifecycle](#testing-the-reverb-package-lifecycle)
- [Migration to Reverb Package Lifecycle](#migration-to-reverb-package-lifecycle)

---

## Package Architecture Overview

The package system is built on a layered architecture that provides a consistent interface for remote server management. **All package classes, including single command executors, must follow the same `execute()` and `commands()` method pattern.**

```
app/Packages/
├── Base/                    # Abstract base classes
│   ├── Package.php              # Package interface (ALL packages must implement)
│   ├── ServerPackage.php        # Interface for server-level packages
│   ├── SitePackage.php          # Interface for site-level packages
│   ├── PackageInstaller.php    # Base installer class
│   ├── PackageRemover.php       # Base remover class
│   ├── PackageManager.php       # Core SSH and milestone functionality
│   └── Milestones.php           # Abstract milestone class
├── Contracts/               # Interfaces
│   ├── Installer.php           # Installation contract
│   └── Remover.php             # Removal contract
├── Enums/                   # SSH credential types
│   └── CredentialType.php      # Root and BrokeForge credential types
├── Enums/                   # Type definitions
│   ├── PackageName.php         # Service categories
│   ├── PackageType.php         # Package type categories
│   ├── ProvisionStatus.php     # Provision states
│   └── Connection.php          # Connection states
└── Services/                # Service implementations
    ├── {Category}/             # Server-level services (Nginx, Database, PHP, Firewall, etc.)
    │   └── {ServiceName}/      # Specific service implementation
    │       ├── {Service}Installer.php
    │       ├── {Service}InstallerMilestones.php
    │       ├── {Service}InstallerJob.php
    │       ├── {Service}Remover.php (optional)
    │       └── {Service}RemoverMilestones.php (optional)
    └── Sites/                  # Site-level packages (MUST be in Sites directory)
        ├── SiteInstaller.php
        ├── SiteRemover.php
        ├── Command/            # Site command executors
        ├── Git/                # Git repository management
        ├── Explorer/           # File exploration
        └── ...
```

## Directory Structure Standards

### Service Organization

Services are organized hierarchically with a clear distinction between server-level and site-level packages:

#### Server-Level Packages (implement ServerPackage)
Located in `Services/{Category}/` where Category is the service type:

```
Services/Nginx/                        # Web server services
├── NginxInstaller.php                # Implements ServerPackage
├── NginxInstallerMilestones.php
└── NginxInstallerJob.php

Services/Database/MySQL/               # Database services
├── MySqlInstaller.php                # Implements ServerPackage
├── MySqlInstallerMilestones.php
├── MySqlRemover.php
└── MySqlRemoverMilestones.php

Services/PHP/                          # Runtime environments
├── PhpInstaller.php                  # Implements ServerPackage
├── PhpInstallerMilestones.php
└── PhpInstallerJob.php

Services/Firewall/                    # Security services
├── FirewallInstaller.php             # Implements ServerPackage
├── FirewallRuleInstaller.php         # Implements ServerPackage
└── ...
```

#### Site-Level Packages (implement SitePackage)
**MUST** be located in `Services/Sites/` directory:

```
Services/Sites/                        # All site-level packages
├── SiteInstaller.php                 # Implements SitePackage
├── SiteRemover.php                   # Implements SitePackage
├── Command/                          # Site command execution
│   └── SiteCommandInstaller.php     # Implements SitePackage
├── Git/                              # Git repository management
│   └── GitRepositoryInstaller.php   # Implements SitePackage
└── Explorer/                         # File exploration
    └── SiteFileExplorer.php         # Implements SitePackage
```

**Key Rules:**
- Server packages: Use any category under `Services/` EXCEPT `Sites/`
- Site packages: MUST be in `Services/Sites/` or its subdirectories
- This separation ensures clear boundaries between infrastructure and application-level concerns

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
3. **Review Credential Types**: Use `CredentialType::Root` for system operations, `CredentialType::BrokeForge` for site operations
4. **Examine Milestone Patterns**: Look at existing milestone classes for consistent naming and structure
5. **Check Service Types**: Use existing `PackageName` constants before adding new ones

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
cat app/Packages/Enums/PackageName.php
# Use PackageName::DATABASE (already exists)

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
        return PackageName::DATABASE;  // Existing enum value
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
        return PackageName::REDIS;  // Unnecessary new enum value
    }

    protected function sshCredential(): SshCredential
    {
        return new RedisCredential;  // Unnecessary new credential
    }
}
```

## Package Interface Implementation

### The Package Interface

**IMPORTANT**: All packages (installers, removers, and single command executors) MUST implement the `Package` interface. This interface is automatically implemented through the `PackageManager` base class, which both `PackageInstaller` and `PackageRemover` extend.

The `Package` interface requires:

```php
interface Package
{
    /**
     * Generic name of the current package.
     */
    public function packageName(): PackageName;

    /**
     * Package categorization type such as database, cache, queue etc.
     */
    public function packageType(): PackageType;

    /**
     * Milestones to track package progression.
     */
    public function milestones(): Milestones;

    /**
     * Credentials used to run the package on SSH
     */
    public function sshCredential(): SshCredential;
}
```

### ServerPackage and SitePackage Interfaces

**CRITICAL REQUIREMENT**: ALL packages (installers AND removers) MUST explicitly implement either `ServerPackage` or `SitePackage` interface. This is not optional.

```php
// ✅ CORRECT - Installer with interface
class NginxInstaller extends PackageInstaller implements ServerPackage { }

// ✅ CORRECT - Remover with interface
class NginxRemover extends PackageRemover implements ServerPackage { }

// ✅ CORRECT - Site installer with interface
class SiteInstaller extends PackageInstaller implements SitePackage { }

// ✅ CORRECT - Site remover with interface
class SiteRemover extends PackageRemover implements SitePackage { }

// ❌ WRONG - Missing interface (will cause "Unknown package type" error)
class NginxRemover extends PackageRemover { }

// ❌ WRONG - Missing interface (will cause "Unknown package type" error)
class SiteRemover extends PackageRemover { }
```

**Why This Matters**: The package system uses these interfaces to determine the correct credential type, directory structure, and processing logic. Omitting the interface will cause runtime errors like "Unknown package type".

To maintain clear separation between server-level and site-level packages, two specialized interfaces extend the base `Package` interface:

#### ServerPackage Interface

All server-level packages (Nginx, MySQL, PHP, Firewall, etc.) MUST implement the `ServerPackage` interface:

```php
interface ServerPackage extends Package
{
    // Inherits all methods from Package interface
    // Server-level packages typically:
    // - Use RootCredential for SSH access
    // - Install system-wide services
    // - Configure server infrastructure
}
```

**Server-level packages include:**
- **Nginx/Apache**: Web server installations
- **MySQL/PostgreSQL/Redis**: Database services
- **PHP/Node.js/Python**: Runtime environments
- **Firewall**: Security and network configuration
- **System utilities**: Mail servers, monitoring, etc.

**Directory Structure**: Server packages MUST be organized under `Services/{Category}/`

#### SitePackage Interface

All site-level packages MUST implement the `SitePackage` interface and be located in the `Services/Sites/` directory:

```php
interface SitePackage extends Package
{
    // Inherits all methods from Package interface
    // Site-level packages typically:
    // - Use UserCredential for SSH access
    // - Operate within user home directories
    // - Manage individual site/application configurations
}
```

**Site-level packages include:**
- **SiteInstaller/SiteRemover**: Core site provisioning
- **GitRepositoryInstaller**: Git repository management
- **SiteCommandInstaller**: Custom command execution within sites
- **SiteFileExplorer**: File management within site directories

**Directory Structure**: Site packages MUST be located under `Services/Sites/` or its subdirectories

### Implementation Examples

#### Server-Level Package Example

```php
namespace App\Packages\Services\Nginx;

use App\Packages\Base\ServerPackage;
use App\Packages\Base\PackageInstaller;

class NginxInstaller extends PackageInstaller implements ServerPackage
{
    public function packageName(): PackageName
    {
        return PackageName::Nginx;
    }

    public function packageType(): PackageType
    {
        return PackageType::ReverseProxy;
    }

    public function sshCredential(): SshCredential
    {
        return new RootCredential; // Server-level = root access
    }
}
```

#### Site-Level Package Example

```php
namespace App\Packages\Services\Sites;

use App\Packages\Base\SitePackage;
use App\Packages\Base\PackageInstaller;

class SiteInstaller extends PackageInstaller implements SitePackage
{
    public function packageName(): PackageName
    {
        return PackageName::Site;
    }

    public function packageType(): PackageType
    {
        return PackageType::Site;
    }

    public function sshCredential(): SshCredential
    {
        return new UserCredential; // Site-level = user access
    }
}
```

### Interface Selection Guidelines

1. **Use ServerPackage when:**
   - Installing system-wide services
   - Requiring root privileges
   - Modifying server configuration
   - Managing infrastructure components

2. **Use SitePackage when:**
   - Working within user directories
   - Managing individual sites/applications
   - Requiring user-level permissions
   - Operating on site-specific resources

3. **Directory Placement:**
   - ServerPackage implementations: `Services/{Category}/` (e.g., `Services/Database/`, `Services/PHP/`)
   - SitePackage implementations: `Services/Sites/` (all site packages MUST be in this directory)

## Package Installer Implementation

### Required Structure

Every Package Installer must extend `PackageInstaller` (which implements `Package` through `PackageManager`) and implement these required methods:

```php
<?php

namespace App\Packages\Services\{Category};

use App\Packages\Base\Milestones;
use App\Packages\Base\PackageInstaller;
use App\Packages\Credentials\SshCredential;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;

/**
 * {Service} Installation Class
 *
 * Brief description of what this installer does
 */
class {ServiceName}Installer extends PackageInstaller
{
    /**
     * Generic name of the current package
     */
    public function packageName(): PackageName
    {
        return PackageName::{SPECIFIC_NAME};
    }

    /**
     * Package categorization type
     */
    public function packageType(): PackageType
    {
        return PackageType::{CATEGORY};
    }

    /**
     * Service type identifier for milestone tracking
     * @deprecated Use packageName() instead
     */
    protected function serviceType(): string
    {
        return $this->packageName()->value;
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

        // Database persistence - track what's installed on remote server
        $this->persist(PackageType::SERVICE, PackageName::SERVICE_NAME, PackageVersion::Version1, [
            'version' => $version,
            'config' => $config
        ]),

        // Database operations (closures) - use parameters in closures
        fn () => $this->server->services()->updateOrCreate([
            'configuration' => array_merge(['version' => $version], $config)
        ]),

        // Final milestone
        $this->track({ServiceName}InstallerMilestones::COMPLETE),
    ];
}
```

## Database Persistence with the `persist` Method

### Purpose

The `persist` method is used to track what packages and configurations are installed on the remote server. This creates a record in the database that mirrors the actual state of the remote server, allowing you to:
- Track installed packages and their versions
- Store configuration details
- Query what's installed on any server
- Manage dependencies between packages

### Method Signature

```php
$this->persist(
    PackageType $type,           // The type of package (PHP, Firewall, Database, etc.)
    PackageName $name,           // The specific package name
    PackageVersion $version,     // The version installed
    array $configuration = []    // Additional configuration data
)
```

### Usage in Commands Array

Place `persist` calls after the SSH commands that actually install the package:

```php
protected function commands(): array
{
    return [
        $this->track(NginxInstallerMilestones::INSTALL_PHP),

        // Install PHP packages
        "apt-get install -y php{$phpVersion->value}-fpm php{$phpVersion->value}-cli",

        // Persist PHP installation to database
        $this->persist(
            PackageType::PHP,
            PackageName::Php83,
            $phpVersion,
            ['modules' => ['fpm', 'cli', 'mysql', 'xml']]
        ),

        $this->track(NginxInstallerMilestones::CONFIGURE_FIREWALL),

        // Configure firewall
        'ufw allow 80/tcp',
        'ufw allow 443/tcp',

        // Persist firewall configuration
        $this->persist(
            PackageType::Firewall,
            PackageName::FirewallUfw,
            PackageVersion::Version1,
            ['rules' => [
                ['port' => 80, 'protocol' => 'tcp'],
                ['port' => 443, 'protocol' => 'tcp']
            ]]
        ),
    ];
}
```

### Real-World Example from NginxInstaller

```php
// After installing and starting services
$this->track(NginxInstallerMilestones::ENABLE_SERVICES),
'systemctl enable --now nginx',
"systemctl enable --now php{$phpVersion->value}-fpm",

// Persist PHP installation
$this->persist(PackageType::PHP, PackageName::Php83, $phpVersion, []),

// After configuring firewall
$this->track(NginxInstallerMilestones::CONFIGURE_FIREWALL),
'ufw allow 80/tcp >/dev/null 2>&1 || true',
'ufw allow 443/tcp >/dev/null 2>&1 || true',

// Persist firewall configuration
$this->persist(
    PackageType::Firewall,
    PackageName::FirewallUfw,
    PackageVersion::Version1,
    ['rules' => [
        ['port' => 80],
        ['port' => 443]
    ]]
),
```

### Best Practices for Using `persist`

1. **Call After Installation**: Always call `persist` AFTER the SSH commands that install the package
2. **Include Configuration**: Store relevant configuration in the array parameter for future reference
3. **Use Appropriate Enums**: Use existing PackageType, PackageName, and PackageVersion enums
4. **Track Dependencies**: Include dependency information in the configuration array when relevant
5. **Be Specific**: Include version information and specific modules/features installed

### Configuration Array Examples

Different package types should include relevant configuration:

```php
// PHP Installation
$this->persist(PackageType::PHP, PackageName::Php83, $phpVersion, [
    'modules' => ['fpm', 'cli', 'mysql', 'xml', 'mbstring'],
    'ini_settings' => ['memory_limit' => '256M', 'max_execution_time' => '30']
]);

// MySQL Installation
$this->persist(PackageType::Database, PackageName::MySQL, PackageVersion::Version80, [
    'port' => 3306,
    'datadir' => '/var/lib/mysql',
    'root_password_set' => true
]);

// Nginx Installation
$this->persist(PackageType::WebServer, PackageName::Nginx, PackageVersion::Version1, [
    'worker_processes' => 'auto',
    'worker_connections' => 1024,
    'sites_enabled' => ['default']
]);

// Firewall Rules
$this->persist(PackageType::Firewall, PackageName::FirewallUfw, PackageVersion::Version1, [
    'rules' => [
        ['port' => 22, 'protocol' => 'tcp', 'source' => 'any'],
        ['port' => 80, 'protocol' => 'tcp', 'source' => 'any'],
        ['port' => 443, 'protocol' => 'tcp', 'source' => 'any'],
        ['port' => 3306, 'protocol' => 'tcp', 'source' => '10.0.0.0/8']
    ]
]);
```

### Database Schema

#### For Server-Level Packages

The `persist` method creates/updates records in the `server_packages` table:

```sql
server_packages
├── id
├── server_id           // The server this package is installed on
├── package_type        // PackageType enum value
├── package_name        // PackageName enum value
├── package_version     // PackageVersion enum value
├── configuration       // JSON column with configuration details
├── status             // Installation status
├── created_at
└── updated_at
```

#### For Site-Level Packages

Site packages use the `server_site_packages` table which includes site association:

```sql
server_site_packages
├── id
├── server_id           // The server this package is installed on
├── site_id            // The specific site this package belongs to
├── service_name        // Service name identifier
├── service_type        // Service type category
├── configuration       // JSON column with configuration details
├── status             // Installation status
├── installed_at        // Installation timestamp
├── uninstalled_at     // Uninstallation timestamp
├── created_at
└── updated_at
```

### Querying Persisted Data

#### For Server-Level Packages

Query server-level packages using the `server_packages` table:

```php
// Get all packages on a server
$packages = $server->packages;

// Check if PHP is installed
$phpInstalled = $server->packages()
    ->where('package_type', PackageType::PHP)
    ->exists();

// Get PHP configuration
$phpConfig = $server->packages()
    ->where('package_type', PackageType::PHP)
    ->where('package_name', PackageName::Php83)
    ->first()
    ?->configuration;

// Find all servers with specific firewall rules
$serversWithHttps = Server::whereHas('packages', function ($query) {
    $query->where('package_type', PackageType::Firewall)
          ->whereJsonContains('configuration->rules', ['port' => 443]);
})->get();
```

#### For Site-Level Packages

Query site-level packages using the `server_site_packages` table:

```php
// Get all site packages for a specific site
$sitePackages = $site->packages;

// Check if Git is configured for a site
$gitEnabled = $site->packages()
    ->where('service_type', 'git')
    ->exists();

// Get site command history
$commandHistory = $site->packages()
    ->where('service_type', 'command')
    ->orderBy('installed_at', 'desc')
    ->get();

// Find all sites with specific packages
$sitesWithGit = ServerSite::whereHas('packages', function ($query) {
    $query->where('service_type', 'git')
          ->where('status', 'active');
})->get();

// Get all site packages for a server
$allSitePackages = ServerSitePackage::where('server_id', $server->id)
    ->with('site')
    ->get()
    ->groupBy('site_id');
```

### Integration with Package Removers

#### For Server-Level Package Removers

Server package removers should remove the persisted records from `server_packages`:

```php
class NginxRemover extends PackageRemover implements ServerPackage
{
    protected function commands(): array
    {
        return [
            $this->track(NginxRemoverMilestones::STOP_SERVICE),
            'systemctl stop nginx',
            'systemctl disable nginx',

            $this->track(NginxRemoverMilestones::REMOVE_PACKAGE),
            'apt-get remove -y nginx',

            // Remove persisted record from server_packages
            fn() => $this->server->packages()
                ->where('package_type', PackageType::WebServer)
                ->where('package_name', PackageName::Nginx)
                ->delete(),
        ];
    }
}
```

#### For Site-Level Package Removers

Site package removers should remove records from `server_site_packages`:

```php
class GitRepositoryRemover extends PackageRemover implements SitePackage
{
    protected function commands(): array
    {
        return [
            $this->track(GitRemoverMilestones::REMOVE_REPOSITORY),
            'rm -rf .git',

            // Remove persisted record from server_site_packages
            fn() => $this->site->packages()
                ->where('service_type', 'git')
                ->delete(),

            // Or remove by server and site
            fn() => ServerSitePackage::where('server_id', $this->server->id)
                ->where('site_id', $this->site->id)
                ->where('service_type', 'git')
                ->delete(),
        ];
    }
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
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;
use App\Packages\Services\Sites\Command\SiteCommandInstallerMilestones;

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

    /**
     * Generic name of the current package
     */
    public function packageName(): PackageName
    {
        return PackageName::SITE;
    }

    /**
     * Package categorization type
     */
    public function packageType(): PackageType
    {
        return PackageType::SITE;
    }

    /**
     * Service type identifier for milestone tracking
     * @deprecated Use packageName() instead
     */
    protected function serviceType(): string
    {
        return $this->packageName()->value;
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

Package Removers follow the same pattern but extend `PackageRemover` (which also implements `Package` through `PackageManager`).

**CRITICAL: All removers MUST implement either `ServerPackage` or `SitePackage` interface, just like installers.**

### Server-Level Package Remover

```php
<?php

namespace App\Packages\Services\{Category};

use App\Packages\Base\Milestones;
use App\Packages\Base\PackageRemover;
use App\Packages\Base\ServerPackage;
use App\Packages\Credentials\SshCredential;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;

class {ServiceName}Remover extends PackageRemover implements ServerPackage
{
    /**
     * Generic name of the current package
     */
    public function packageName(): PackageName
    {
        return PackageName::{SPECIFIC_NAME};
    }

    /**
     * Package categorization type
     */
    public function packageType(): PackageType
    {
        return PackageType::{CATEGORY};
    }

    /**
     * Service type identifier for milestone tracking
     * @deprecated Use packageName() instead
     */
    protected function serviceType(): string
    {
        return $this->packageName()->value;
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

### Site-Level Package Remover

**IMPORTANT: Site-level removers MUST be in `Services/Sites/` directory and implement `SitePackage`:**

```php
<?php

namespace App\Packages\Services\Sites;

use App\Models\ServerSite;
use App\Packages\Base\Milestones;
use App\Packages\Base\PackageRemover;
use App\Packages\Base\SitePackage;
use App\Packages\Enums\CredentialType;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;

class SiteRemover extends PackageRemover implements SitePackage
{
    public function packageName(): PackageName
    {
        return PackageName::Site;
    }

    public function packageType(): PackageType
    {
        return PackageType::Site;
    }

    public function milestones(): Milestones
    {
        return new SiteRemoverMilestones;
    }

    public function credentialType(): CredentialType
    {
        return CredentialType::BrokeForge;
    }

    public function execute(array $config): void
    {
        $site = $config['site'] ?? null;
        $domain = $config['domain'] ?? $site?->domain;

        if (! $domain) {
            throw new \LogicException('Site domain must be provided for removal.');
        }

        $this->remove($this->commands($domain, $site));
    }

    protected function commands(string $domain, ?ServerSite $site): array
    {
        return [
            $this->track(SiteRemoverMilestones::DISABLE_SITE),
            "rm -f /etc/nginx/sites-enabled/{$domain}",

            $this->track(SiteRemoverMilestones::RELOAD_NGINX),
            'nginx -s reload',

            $this->track(SiteRemoverMilestones::COMPLETE),
            fn () => $site?->update(['status' => 'disabled', 'deprovisioned_at' => now()]),
        ];
    }
}
```

### Key Differences: ServerPackage vs SitePackage Removers

| Aspect | ServerPackage Remover | SitePackage Remover |
|--------|----------------------|---------------------|
| **Interface** | `implements ServerPackage` | `implements SitePackage` |
| **Directory** | `Services/{Category}/` | `Services/Sites/` |
| **Credential** | `CredentialType::Root` | `CredentialType::BrokeForge` |
| **Scope** | Server-wide services | Individual sites |
| **Examples** | NginxRemover, MySqlRemover, PhpRemover | SiteRemover, GitRepositoryRemover |

**Common Mistake to Avoid:**
- ❌ Forgetting to add `implements ServerPackage` or `implements SitePackage`
- ❌ Using wrong credential type for the package level
- ❌ Placing site packages outside `Services/Sites/` directory

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

1. **CredentialType::Root**: Full system access for system-level operations (package installs, service management)
2. **CredentialType::BrokeForge**: Site-level access for application operations (Git, deployments, site management)

### Credential Selection Guidelines

```php
// System services (MySQL, NGINX, PHP, etc.)
public function credentialType(): CredentialType
{
    return CredentialType::Root;
}

// Site operations (Git, deployments, site commands)
public function credentialType(): CredentialType
{
    return CredentialType::BrokeForge;
}
```

**Note:** Each server has unique SSH keys stored encrypted in the database. Access credentials via `$server->credential(CredentialType::BrokeForge)` or `$server->credential('brokeforge')`.

```php

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

## Real-Time Updates with Laravel Reverb

### When to Broadcast in Packages

Broadcasting is handled automatically by model events. You should focus on:
- **Updating models** with meaningful state changes
- **Using broadcast fields** that trigger events (provision, provision_status, connection, etc.)
- **Letting model observers** handle the broadcasting

### Broadcasting Pattern in BrokeForge

BrokeForge uses **Automatic Model-Based Broadcasting**:
1. Models automatically broadcast when meaningful fields change
2. Backend sends minimal notification events
3. Frontend listens and fetches the full resource
4. Resource class remains the single source of truth

For complete documentation, see [docs/reverb-real-time-pattern.md](../docs/reverb-real-time-pattern.md)

### Automatic Broadcasting via Model Events

**This is the preferred approach.** Broadcasting is handled automatically by model event listeners:

```php
// app/Models/Server.php
protected static function booted(): void
{
    static::updated(function (self $server): void {
        // Only broadcast if meaningful fields changed
        $broadcastFields = [
            'provision',
            'provision_status',
            'connection',
            'monitoring_status',
            'scheduler_status',
            'supervisor_status',
        ];

        if ($server->wasChanged($broadcastFields)) {
            \App\Events\ServerUpdated::dispatch($server->id);
        }
    });
}
```

**Benefits:**
- ✅ Automatic - never forget to broadcast
- ✅ Consistent - always broadcasts on meaningful changes
- ✅ Less code - no manual dispatch calls
- ✅ Maintainable - broadcasting logic in one place
- ✅ Performance - only broadcasts when necessary

### Package Implementation

**In Controllers and Jobs, simply update the model:**

```php
// NO manual broadcast needed!
public function step(Request $request, Server $server): JsonResponse
{
    // Update the model - broadcasting happens automatically
    $server->provision->put($step, $status);
    $server->save();

    return response()->json(['ok' => true]);
}
```

**In Installers, update models as needed:**

```php
class NginxInstaller extends PackageInstaller implements ServerPackage
{
    public function execute(PhpVersion $phpVersion): void
    {
        // Model automatically broadcasts on save
        $this->server->provision->put(5, ProvisionStatus::Completed->value);
        $this->server->provision->put(6, ProvisionStatus::Installing->value);
        $this->server->save();

        PhpInstallerJob::dispatchSync($this->server, $phpVersion);

        // Model automatically broadcasts on save
        $this->server->provision->put(6, ProvisionStatus::Completed->value);
        $this->server->provision->put(7, ProvisionStatus::Installing->value);
        $this->server->save();

        // Continue with remaining steps...
    }
}
```

**In Jobs, update provision_status only:**

```php
class NginxInstallerJob implements ShouldQueue
{
    public function handle(): void
    {
        try {
            $installer = new NginxInstaller($this->server);

            if ($this->isProvisioningServer) {
                // Model event handles broadcasting automatically
                $this->server->update(['provision_status' => ProvisionStatus::Installing]);
            }

            $installer->execute($this->phpVersion);

            if ($this->isProvisioningServer) {
                // Model event handles broadcasting automatically
                $this->server->update(['provision_status' => ProvisionStatus::Completed]);
            }

        } catch (Exception $e) {
            if ($this->isProvisioningServer) {
                // Model event handles broadcasting automatically
                $this->server->update(['provision_status' => ProvisionStatus::Failed]);
            }
            throw $e;
        }
    }
}
```

### Event Structure

Events should use `ShouldBroadcastNow` for immediate broadcasting:

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServerUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $serverId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('servers.'.$this->serverId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'server_id' => $this->serverId,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
```

**Key Elements:**
- `ShouldBroadcastNow`: Immediate broadcasting (no queue delay)
- `PrivateChannel`: Security (requires authorization)
- Minimal payload: Only IDs and timestamps
- Simple constructor: PHP 8+ property promotion

### Channel Authorization

Define channel authorization in `routes/channels.php`:

```php
use App\Models\Server;

Broadcast::channel('servers.{serverId}', function ($user, int $serverId) {
    return $user->id === Server::findOrNew($serverId)->user_id;
});
```

**Important:** Use a single channel per resource, not multiple topic-specific channels.

**Security Checklist:**
- ✅ Always use `PrivateChannel` for user-specific data
- ✅ Verify resource ownership in channel callback
- ✅ Return boolean: `true` for authorized, `false` for denied
- ❌ Never broadcast sensitive data in the payload

### Testing Broadcasts

Test that model changes trigger broadcasts:

```php
use App\Events\ServerUpdated;
use Illuminate\Support\Facades\Event;

public function test_it_broadcasts_on_provision_update(): void
{
    Event::fake([ServerUpdated::class]);

    $server = Server::factory()->create();
    $url = URL::signedRoute('servers.provision.step', ['server' => $server->id]);

    $this->post($url, ['step' => 1, 'status' => 'installing'])->assertOk();

    Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
        return $event->serverId === $server->id;
    });
}
```

### Broadcasting Guidelines Summary

1. **Use Model Events** for automatic broadcasting
2. **Update models** and let observers handle broadcasts
3. **Conditional broadcasting** - only when meaningful fields change
4. **Minimal payloads** - only IDs and timestamps
5. **Use `PrivateChannel`** for security
6. **Single channel per resource** - not topic-specific channels
7. **Test model observers** with `Event::fake([SpecificEvent::class])`
8. **No manual dispatches** in controllers, jobs, or installers

For complete examples and troubleshooting, see [docs/reverb-real-time-pattern.md](../docs/reverb-real-time-pattern.md)

## 📋 Quick Decision Guide: Should You Use Reverb Package Lifecycle?

Use this checklist to determine if your package should use the Reverb Package Lifecycle pattern:

### ✅ USE Reverb Package Lifecycle When:
- [ ] Users need to see installation/removal progress in **real-time**
- [ ] Operation takes **more than a few seconds** to complete
- [ ] Resource has meaningful **status transitions** (pending → installing → active/failed)
- [ ] Users should see the resource **immediately**, even before installation completes
- [ ] Package creates **user-facing resources** (firewall rules, scheduled tasks, SSL certificates, deployments)
- [ ] Operation may **fail** and users need immediate feedback

**Examples:** FirewallRuleInstaller, ServerScheduleTaskInstaller, SslCertificateInstaller, DeploymentConfigInstaller

### ❌ DON'T USE Reverb Package Lifecycle When:
- [ ] Installation happens as part of **initial server provisioning** (one-time setup)
- [ ] Operation completes in **under 2 seconds**
- [ ] Package is **infrastructure-only** with no user-facing resources
- [ ] No meaningful status transitions (just success/failure)
- [ ] Users don't need to monitor progress

**Examples:** NginxInstaller (server provisioning), PhpInstaller (server provisioning), InitialServerSetup

### Implementation Checklist (If Using Reverb Package Lifecycle):

When you determine a package needs the Reverb Package Lifecycle, follow this checklist:

- [ ] **Step 1 - Model Migration**: Add `status` column to model's migration (string, default 'pending')
- [ ] **Step 2 - Model Fillable**: Add `'status'` to model's `$fillable` array
- [ ] **Step 3 - Model Events**: Add `booted()` method with `created()`, `updated()`, `deleted()` event listeners that dispatch broadcast event
- [ ] **Step 4 - Controller**: Create database record FIRST with `status: 'pending'`, THEN dispatch job with record ID
- [ ] **Step 5 - Job Constructor**: Accept record ID (not data array) in job constructor
- [ ] **Step 6 - Job Handle**: Load record, update status to 'installing', execute installer, update to 'active' on success or 'failed' on error
- [ ] **Step 7 - Frontend useEcho**: Add `useEcho()` hook listening to server channel for broadcast events
- [ ] **Step 8 - Frontend Reload**: Use `router.reload({ only: [...] })` in useEcho callback
- [ ] **Step 9 - Resource Class**: Ensure ServerResource includes the new resource with status field
- [ ] **Step 10 - Tests**: Write tests for: pending status creation, installing status update, active status on success, failed status on error, broadcast event dispatch

**Reference Implementation**: `app/Packages/Services/Firewall/FirewallRuleInstallerJob.php` + `app/Models/ServerFirewallRule.php`

---

## ⚠️ CRITICAL ARCHITECTURAL RULES

**These rules are MANDATORY for all package implementations. Violating these patterns will cause runtime errors, job failures, and architectural inconsistencies.**

### Rule 1: Installer/Remover Classes Handle ALL Logic

**Installer and Remover classes are responsible for:**
- ✅ ALL business logic and data preparation
- ✅ ALL database operations (creating, updating, deleting records)
- ✅ SSH command generation in `commands()` method
- ✅ Milestone tracking
- ✅ Configuration validation
- ✅ Error handling and recovery

**Real-World Examples:**
- `app/Packages/Services/Nginx/NginxInstaller.php` - Handles firewall setup, PHP installation, Nginx config, database operations
- `app/Packages/Services/PHP/PhpInstaller.php` - Creates ServerPhp records, generates commands, handles installation

```php
// ✅ CORRECT - NginxInstaller handles everything including job dispatching
class NginxInstaller extends PackageInstaller implements ServerPackage
{
    public function execute(PhpVersion $phpVersion): void
    {
        // Installers can dispatch other jobs as dependencies
        FirewallInstallerJob::dispatchSync($this->server);

        // Configure firewall rules for HTTP and HTTPS
        $firewallRules = [
            ['port' => '80', 'name' => 'HTTP', 'rule_type' => 'allow', 'from_ip_address' => null],
            ['port' => '443', 'name' => 'HTTPS', 'rule_type' => 'allow', 'from_ip_address' => null],
        ];

        // One job per instance pattern - dispatch separate job for each rule
        foreach ($firewallRules as $ruleData) {
            FirewallRuleInstallerJob::dispatchSync($this->server, $ruleData);
        }

        // Installers update provision status directly
        $this->server->provision->put(5, ProvisionStatus::Completed->value);
        $this->server->provision->put(6, ProvisionStatus::Installing->value);
        $this->server->save();

        // Dispatch PHP installation job
        PhpInstallerJob::dispatchSync($this->server, $phpVersion);

        $this->server->provision->put(6, ProvisionStatus::Completed->value);
        $this->server->provision->put(7, ProvisionStatus::Installing->value);
        $this->server->save();

        // Generate and execute SSH commands
        $this->install($this->commands($phpVersion));

        $this->server->provision->put(7, ProvisionStatus::Completed->value);
        $this->server->save();
    }

    protected function commands(PhpVersion $phpVersion): array
    {
        $appUser = config('app.ssh_user', str_replace(' ', '', strtolower(config('app.name'))));

        return [
            $this->track(NginxInstallerMilestones::PREPARE_SYSTEM),
            'apt-get update -y',

            $this->track(NginxInstallerMilestones::INSTALL_SOFTWARE),
            'apt-get install -y nginx',

            $this->track(NginxInstallerMilestones::CONFIGURE_NGINX),

            // Database operations in closures - executed later when array is processed
            function () use ($appUser, $phpVersion) {
                $this->server->sites()->updateOrCreate(
                    ['domain' => 'default'],
                    [
                        'document_root' => "/home/{$appUser}/default",
                        'nginx_config_path' => '/etc/nginx/sites-available/default',
                        'php_version' => $phpVersion,
                        'ssl_enabled' => false,
                        'configuration' => ['is_default_site' => true],
                        'status' => 'active',
                        'provisioned_at' => now(),
                        'deprovisioned_at' => null,
                    ]
                );
            },

            $this->track(NginxInstallerMilestones::COMPLETE),
        ];
    }
}
```

### Rule 2: Job Classes Are ONLY Lightweight Wrappers

**Job classes should ONLY:**
- ✅ Log start/completion messages
- ✅ Create installer/remover instance
- ✅ Dispatch the installer/remover
- ✅ Catch and re-throw exceptions for Laravel's retry mechanism

**Job classes should NEVER:**
- ❌ Create or update database records
- ❌ Contain business logic
- ❌ Generate SSH commands
- ❌ Use the `persist()` method
- ❌ Track milestones

**Real-World Example:**

```php
// ✅ CORRECT - NginxInstallerJob (app/Packages/Services/Nginx/NginxInstallerJob.php)
class NginxInstallerJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 600;

    public function __construct(
        public Server $server,
        public PhpVersion $phpVersion,
        public bool $isProvisioningServer = false
    ) {}

    public function handle(): void
    {
        set_time_limit(0);

        Log::info("Starting Nginx installation for server #{$this->server->id} with PHP {$this->phpVersion->value}");

        try {
            $installer = new NginxInstaller($this->server);

            if ($this->isProvisioningServer) {
                $this->server->update(['provision_status' => ProvisionStatus::Installing]);
            }

            // Installer handles EVERYTHING - business logic, DB ops, SSH commands, milestones
            $installer->execute($this->phpVersion);

            Log::info("Nginx installation completed for server #{$this->server->id}");

            if ($this->isProvisioningServer) {
                $this->server->update(['provision_status' => ProvisionStatus::Completed]);
            }

        } catch (Exception $e) {
            if ($this->isProvisioningServer) {
                $this->server->update(['provision_status' => ProvisionStatus::Failed]);
            }
            Log::error("Nginx installation failed for server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
```

```php
// ❌ WRONG - Job doing too much
class BadInstallerJob implements ShouldQueue
{
    public function handle(): void
    {
        // ❌ Database operations in job
        ServerPhp::create([...]);

        // ❌ Business logic in job
        $packages = $this->determinePackages();

        // ❌ SSH commands in job
        $ssh->execute('apt-get install...');
    }
}
```

### Rule 3: Use execute() Parameters, NEVER Constructors

**Configuration must be passed through `execute()` method parameters, NOT through constructor parameters.**

The base `PackageInstaller` constructor accepts ONLY `Server $server`. Do not override the constructor to add configuration parameters.

```php
// ✅ CORRECT - Configuration via execute() parameters
class NginxInstaller extends PackageInstaller implements ServerPackage
{
    // Constructor inherited from base class - accepts ONLY Server
    // public function __construct(Server $server) { parent::__construct($server); }

    public function execute(PhpVersion $phpVersion): void  // ← Parameters here!
    {
        $this->install($this->commands($phpVersion));
    }

    protected function commands(PhpVersion $phpVersion): array  // ← Parameters here!
    {
        return [
            "systemctl enable --now php{$phpVersion->value}-fpm",
            // ... more commands using $phpVersion
        ];
    }
}
```

```php
// ❌ WRONG - Configuration via constructor
class BadInstaller extends PackageInstaller
{
    public function __construct(
        Server $server,
        PhpVersion $phpVersion  // ❌ NO! Don't add config to constructor
    ) {
        parent::__construct($server);
        $this->phpVersion = $phpVersion;
    }
}
```

**Why this matters:** Passing configuration through `execute()` keeps installers reusable and makes the command generation dynamic.

### Rule 4: One Job = One Instance

**Each job should handle ONE installation or removal. For multiple items, dispatch MULTIPLE jobs.**

This pattern provides:
- ✅ Granular retry logic (if one fails, others succeed)
- ✅ Individual error handling
- ✅ Proper job queue monitoring
- ✅ Database integrity (one job = one record)

**Real-World Example from NginxInstaller:**

```php
// ✅ CORRECT - One job per firewall rule (NginxInstaller.php:59-67)
$firewallRules = [
    ['port' => '80', 'name' => 'HTTP', 'rule_type' => 'allow'],
    ['port' => '443', 'name' => 'HTTPS', 'rule_type' => 'allow'],
];

// Dispatch SEPARATE job for EACH rule
foreach ($firewallRules as $ruleData) {
    FirewallRuleInstallerJob::dispatchSync($this->server, $ruleData);
}
```

```php
// ❌ WRONG - One job handling multiple items
FirewallRuleInstallerJob::dispatchSync($this->server, $firewallRules);  // Array of rules ❌
```

**Another Real-World Example:**

```php
// ✅ CORRECT - Installing multiple scheduled tasks
$tasks = [
    ['name' => 'Cleanup', 'command' => 'rm -rf /tmp/*', 'frequency' => ScheduleFrequency::Daily],
    ['name' => 'Backup', 'command' => 'backup.sh', 'frequency' => ScheduleFrequency::Weekly],
];

foreach ($tasks as $taskData) {
    ServerScheduleTaskInstallerJob::dispatch($this->server, $taskData);  // One job per task ✅
}
```

### Rule 5: commands() Generates SSH Commands, execute() Prepares Data

**Clear separation of responsibilities:**

- **`execute()` method:**
  - Prepares and validates data
  - Queries database
  - Calls `$this->install($this->commands())`
  - Handles high-level orchestration

- **`commands()` method:**
  - Returns array of SSH commands (strings)
  - Returns milestone tracking closures
  - Returns database operation closures
  - Receives parameters from `execute()`

```php
// ✅ CORRECT - Clear separation (PhpInstaller.php:65-104)
public function execute(PhpVersion $phpVersion): void
{
    // Data preparation in execute()
    $isFirstPhp = $this->server->phps()->count() === 0;

    // Database operations in execute()
    ServerPhp::firstOrCreate([...]);

    // Compose package list
    $phpPackages = implode(' ', [
        "php{$phpVersion->value}-fpm",
        "php{$phpVersion->value}-cli",
        // ... more packages
    ]);

    // Pass to commands() which generates SSH commands
    $this->install($this->commands($phpVersion, $phpPackages));
}

protected function commands(PhpVersion $phpVersion, string $phpPackages): array
{
    // ONLY SSH commands and closures
    return [
        $this->track(PhpInstallerMilestones::INSTALL_PHP),
        "apt-get install -y {$phpPackages}",
        "systemctl enable php{$phpVersion->value}-fpm",
    ];
}
```

### Rule 6: Reverb Package Lifecycle Pattern

**⚠️ MANDATORY for packages that benefit from real-time status updates**

The Reverb Package Lifecycle pattern creates database records FIRST with a status field, then updates that status through the installation/removal lifecycle. This provides immediate visibility and real-time progress updates to users via Laravel Reverb WebSocket broadcasting.

**Why "Reverb Package Lifecycle"?**
This pattern is specifically designed to leverage Laravel Reverb for real-time updates. By managing status in the database and broadcasting changes automatically, the frontend receives instant WebSocket notifications of progress without polling.

#### Core Architecture: Event-Driven Real-Time Updates

The Reverb Package Lifecycle uses an **event-driven architecture** where model changes automatically trigger broadcasts, and the frontend listens for these events to fetch updated data:

```
┌─────────────┐      ┌──────────────┐      ┌─────────────┐      ┌──────────────┐
│  Database   │ ───> │ Model Events │ ───> │   Laravel   │ ───> │   Frontend   │
│   Update    │      │  (created,   │      │   Reverb    │      │   useEcho    │
│             │      │   updated,   │      │  WebSocket  │      │   Listener   │
│             │      │   deleted)   │      │ Broadcasting│      │              │
└─────────────┘      └──────────────┘      └─────────────┘      └──────────────┘
                                                                         │
                                                                         ▼
                                                                  ┌──────────────┐
                                                                  │   Inertia    │
                                                                  │ router.reload│
                                                                  │ Fetch Fresh  │
                                                                  │ Resource Data│
                                                                  └──────────────┘
```

**Key Principles:**
1. **Model Events Drive Everything**: Every database change (create, update, delete) automatically triggers a broadcast
2. **Minimal WebSocket Payload**: Events only contain resource ID and timestamp (no complex data)
3. **Frontend Fetches Fresh Data**: On receiving event, Inertia reloads the resource from the API
4. **Single Source of Truth**: All data transformation stays in the Resource class
5. **No Polling Required**: Real-time updates via WebSocket connections eliminate the need for HTTP polling

**Why This Architecture?**
- ✅ **Automatic Broadcasting**: Model events ensure broadcasts are never forgotten
- ✅ **Data Consistency**: Resource class remains the single source of truth
- ✅ **Type Safety**: Frontend always receives properly typed, transformed data
- ✅ **Performance**: Minimal WebSocket payloads, efficient partial page reloads
- ✅ **Maintainability**: Broadcasting logic centralized in model observers

#### When to Use This Pattern

Use the Reverb Package Lifecycle pattern when:
- ✅ Users need to see installation/removal progress in real-time
- ✅ Operations take more than a few seconds to complete
- ✅ The resource has meaningful status transitions (pending → installing → active/failed)
- ✅ Users should see the resource immediately, even before installation completes

**Examples:** Firewall rules, scheduled tasks, deployment configurations, SSL certificates, cron jobs

#### Pattern Flow

**Traditional Pattern (Old - Don't Use):**
```
User Action → Job Dispatched → SSH Commands Execute → Record Created on Success
```
Problem: No visibility until completion, no real-time updates, users see nothing until success/failure.

**Reverb Package Lifecycle (New - Use This):**
```
User Action → Record Created (status: pending)
→ Broadcast → Frontend Shows "Pending"
→ Job Updates Status (status: installing)
→ Broadcast → Frontend Shows "Installing"
→ SSH Commands Execute
→ Job Updates Status (status: active/failed)
→ Broadcast → Frontend Shows Final Status
```
Benefits: Immediate visibility, real-time progress, automatic Reverb broadcasting.

#### Implementation Steps

**Step 1: Controller Creates Record First**

The controller creates the database record with `status: 'pending'` BEFORE dispatching the job:

```php
// app/Http/Controllers/ServerFirewallController.php
public function store(FirewallRuleRequest $request, Server $server): RedirectResponse
{
    try {
        // Ensure parent resource exists
        if (! $server->firewall) {
            return back()->with('error', 'Firewall is not installed on this server.');
        }

        // ✅ CREATE RECORD FIRST with 'pending' status
        $rule = ServerFirewallRule::create([
            'server_firewall_id' => $server->firewall->id,
            'name' => $request->validated('name'),
            'port' => $request->validated('port'),
            'from_ip_address' => $request->validated('from_ip_address'),
            'rule_type' => $request->validated('rule_type', 'allow'),
            'status' => 'pending',  // ← Initial status
        ]);

        // ✅ THEN dispatch job with the record ID
        FirewallRuleInstallerJob::dispatch($server, $rule->id);

        return back()->with('success', 'Firewall rule is being applied.');

    } catch (\Exception $e) {
        Log::error('Failed to create firewall rule', [
            'server_id' => $server->id,
            'error' => $e->getMessage(),
        ]);

        return back()->with('error', 'Failed to create firewall rules.');
    }
}
```

**Step 2: Job Manages Status Lifecycle**

The job receives the record ID and manages status transitions:

```php
// app/Packages/Services/Firewall/FirewallRuleInstallerJob.php
class FirewallRuleInstallerJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Server $server,
        public int $ruleId  // ← Receives record ID, not data
    ) {}

    public function handle(): void
    {
        // Load the record
        $rule = ServerFirewallRule::findOrFail($this->ruleId);

        Log::info("Starting firewall rule configuration", [
            'rule_id' => $rule->id,
            'server_id' => $this->server->id,
        ]);

        try {
            // ✅ UPDATE: pending → installing
            $rule->update(['status' => 'installing']);
            // Model event broadcasts automatically via Reverb

            // Create installer and execute
            $installer = new FirewallRuleInstaller($this->server);
            $installer->execute([/* rule data */]);

            // ✅ UPDATE: installing → active
            $rule->update(['status' => 'active']);
            // Model event broadcasts automatically via Reverb

            Log::info("Firewall rule configured successfully", ['rule_id' => $rule->id]);

        } catch (\Exception $e) {
            // ✅ UPDATE: any → failed
            $rule->update(['status' => 'failed']);
            // Model event broadcasts automatically via Reverb

            Log::error("Firewall rule configuration failed", [
                'rule_id' => $rule->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;  // Re-throw for Laravel's retry mechanism
        }
    }
}
```

**Step 3: Model Events Automatically Broadcast Changes**

**CRITICAL**: This is where the magic happens. Model event listeners automatically dispatch broadcast events whenever the model changes. This ensures broadcasts are **NEVER forgotten** and happen **consistently** across the entire application.

```php
// app/Models/ServerFirewallRule.php
class ServerFirewallRule extends Model
{
    protected $fillable = [
        'server_firewall_id',
        'name',
        'port',
        'from_ip_address',
        'rule_type',
        'status',  // ← Status field for lifecycle
    ];

    protected static function booted(): void
    {
        // ✅ Broadcast on creation (status: pending)
        // Triggers when: $rule = ServerFirewallRule::create([...])
        static::created(function (self $rule): void {
            \App\Events\ServerUpdated::dispatch($rule->firewall->server_id);
        });

        // ✅ Broadcast on status updates (pending → installing → active/failed)
        // Triggers when: $rule->update(['status' => 'installing'])
        static::updated(function (self $rule): void {
            \App\Events\ServerUpdated::dispatch($rule->firewall->server_id);
        });

        // ✅ Broadcast on deletion
        // Triggers when: $rule->delete()
        static::deleted(function (self $rule): void {
            \App\Events\ServerUpdated::dispatch($rule->firewall->server_id);
        });
    }

    public function firewall(): BelongsTo
    {
        return $this->belongsTo(ServerFirewall::class, 'server_firewall_id');
    }
}
```

**What This Means:**
- **Zero Manual Broadcasting**: No need to call `dispatch()` in controllers, jobs, or installers
- **Automatic & Consistent**: Every model change triggers a broadcast automatically
- **Lifecycle Coverage**: Creation, updates, and deletion all broadcast
- **Immediate Delivery**: Events use `ShouldBroadcastNow` for instant WebSocket delivery

**The Event Class:**

```php
// app/Events/ServerUpdated.php
class ServerUpdated implements ShouldBroadcastNow  // ← Immediate broadcasting
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $serverId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('servers.'.$this->serverId),  // ← WebSocket channel
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'server_id' => $this->serverId,          // ← Minimal payload
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
```

**Why Minimal Payload?**
- The event only sends the server ID and timestamp (no complex data)
- Frontend uses this notification to **fetch the full resource** via Inertia
- Resource class transforms the data with consistent formatting
- Avoids data duplication and transformation logic in multiple places

**Step 4: Frontend Listens and Fetches Fresh Data with useEcho + Inertia**

**CRITICAL**: The frontend uses `useEcho` to listen for WebSocket events, then uses `router.reload()` to **fetch fresh resource data** from the server. This is a **"Broadcast Notification → Fetch Full Resource"** pattern.

```typescript
// resources/js/pages/servers/firewall.tsx
import { useEcho } from '@laravel/echo-react';
import { router } from '@inertiajs/react';

export default function Firewall({ server }: Props) {
    // ✅ Listen for real-time server updates via Reverb WebSocket
    // This hook automatically:
    // 1. Subscribes to the 'servers.{id}' private channel
    // 2. Listens for 'ServerUpdated' events
    // 3. Calls the callback when events are received
    useEcho(`servers.${server.id}`, 'ServerUpdated', () => {
        // ✅ Fetch fresh data from the server via Inertia
        // This makes a partial page reload to get updated resource data
        router.reload({
            only: ['server'],        // ← Only reload 'server' prop (efficient!)
            preserveScroll: true,    // ← Keep scroll position
            preserveState: true,     // ← Keep other component state
        });
    });

    return (
        <div>
            {server.rules.map(rule => (
                <div key={rule.id}>
                    <span>{rule.name}</span>
                    <span>{rule.port}</span>
                    {/* Status updates automatically via Reverb */}
                    <Badge status={rule.status}>
                        {rule.status}  {/* pending → installing → active/failed */}
                    </Badge>
                </div>
            ))}
        </div>
    );
}
```

**How the Complete Event-Driven Flow Works:**

```
User Creates Rule
       ↓
Controller creates DB record (status: pending)
       ↓
Model's created() event fires
       ↓
ServerUpdated event dispatched
       ↓
Reverb broadcasts to WebSocket channel 'servers.{id}'
       ↓
Frontend useEcho receives notification
       ↓
router.reload() fetches fresh server data via Inertia
       ↓
UI updates to show rule with "pending" status
       ↓
Job starts executing
       ↓
Job updates DB record (status: installing)
       ↓
Model's updated() event fires
       ↓
ServerUpdated event dispatched again
       ↓
Frontend receives notification and reloads
       ↓
UI updates to show "installing" status
       ↓
Job completes successfully
       ↓
Job updates DB record (status: active)
       ↓
Model's updated() event fires again
       ↓
ServerUpdated event dispatched again
       ↓
Frontend receives notification and reloads
       ↓
UI updates to show "active" status ✅
```

**Critical Points:**
- **No Polling**: Frontend never polls the server for updates
- **No Manual Broadcasts**: Developers never call `dispatch()` manually
- **Automatic**: Model events handle everything automatically
- **Efficient**: Inertia only reloads the 'server' prop, not the entire page
- **Real-Time**: Updates appear instantly via WebSocket
- **Reliable**: Every model change triggers a broadcast (impossible to forget)

**The Resource Layer (Single Source of Truth):**

When `router.reload()` fetches data, it goes through the Resource class:

```php
// app/Http/Resources/ServerResource.php
class ServerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vanity_name' => $this->vanity_name,
            // ... other fields
            'rules' => $this->firewall?->rules->map(fn ($rule) => [
                'id' => $rule->id,
                'name' => $rule->name,
                'port' => $rule->port,
                'from_ip_address' => $rule->from_ip_address,
                'rule_type' => $rule->rule_type,
                'status' => $rule->status,  // ← Always current from DB
                'created_at' => $rule->created_at->toISOString(),
            ])->toArray(),
        ];
    }
}
```

**Benefits of This Architecture:**
- ✅ **Single Source of Truth**: All data transformation in Resource class
- ✅ **Type Safety**: Frontend receives consistent, typed data
- ✅ **No Data Duplication**: Don't repeat transformation logic in events
- ✅ **Maintainable**: Changes to data structure happen in one place
- ✅ **Testable**: Can test model events, resource transformations separately

#### Status Field Guidelines

Define clear status values that represent the lifecycle:

**Installation Statuses:**
- `'pending'` - Record created, job not started yet
- `'installing'` - Job is actively running SSH commands
- `'active'` - Installation completed successfully
- `'failed'` - Installation failed with errors

**Removal Statuses:**
- `'removing'` - Removal in progress
- (deleted) - Record removed from database after successful removal

#### Key Benefits

1. **Immediate Visibility:** Users see the record instantly with 'pending' status
2. **Real-Time Progress:** Status updates broadcast automatically via Reverb WebSockets
3. **No Polling Required:** Frontend uses `useEcho` instead of polling endpoints
4. **Automatic Broadcasting:** Model events handle all broadcasting, no manual dispatch
5. **Granular Retry Logic:** Failed items can be retried individually
6. **Better UX:** Users see progress rather than waiting for completion

#### Complete Example: Firewall Rule Lifecycle

Reference implementation: `app/Packages/Services/Firewall/FirewallRuleInstallerJob.php`

```php
// 1. Controller creates record first (app/Http/Controllers/ServerFirewallController.php:42-49)
$rule = ServerFirewallRule::create([
    'server_firewall_id' => $server->firewall->id,
    'name' => 'Allow HTTP',
    'port' => '80',
    'rule_type' => 'allow',
    'status' => 'pending',  // ✅ User sees this immediately
]);

FirewallRuleInstallerJob::dispatch($server, $rule->id);

// 2. Job manages lifecycle (app/Packages/Services/Firewall/FirewallRuleInstallerJob.php:31-80)
public function handle(): void
{
    $rule = ServerFirewallRule::findOrFail($this->ruleId);

    try {
        $rule->update(['status' => 'installing']);  // ✅ Broadcasts to frontend

        $installer = new FirewallRuleInstaller($this->server);
        $installer->execute($ruleData);

        $rule->update(['status' => 'active']);      // ✅ Broadcasts to frontend
    } catch (\Exception $e) {
        $rule->update(['status' => 'failed']);      // ✅ Broadcasts to frontend
        throw $e;
    }
}

// 3. Model broadcasts automatically (app/Models/ServerFirewallRule.php:27-40)
static::updated(function (self $rule): void {
    \App\Events\ServerUpdated::dispatch($rule->firewall->server_id);
    // ✅ Reverb sends WebSocket notification to frontend
});

// 4. Frontend updates in real-time (resources/js/pages/servers/firewall.tsx)
useEcho(`servers.${server.id}`, 'ServerUpdated', () => {
    router.reload({ only: ['server'] });
    // ✅ UI shows: pending → installing → active/failed
});
```

#### Testing the Reverb Package Lifecycle

Test all three lifecycle stages:

```php
// tests/Feature/ServerFirewallRuleLifecycleTest.php
public function test_controller_creates_rule_with_pending_status(): void
{
    Queue::fake();

    $server = Server::factory()->create();
    $firewall = ServerFirewall::factory()->for($server)->create();

    $this->actingAs(User::factory()->create());

    $this->post(route('servers.firewall.store', $server), [
        'name' => 'Test Rule',
        'port' => '8080',
        'rule_type' => 'allow',
    ]);

    // ✅ Verify record created with pending status
    $rule = ServerFirewallRule::where('name', 'Test Rule')->first();
    $this->assertEquals('pending', $rule->status);
}

public function test_job_updates_status_to_active_on_success(): void
{
    $rule = ServerFirewallRule::factory()->create(['status' => 'pending']);

    // Mock SSH for successful execution
    $mockSsh = $this->mockSuccessfulSsh();

    $job = new FirewallRuleInstallerJob($server, $rule->id);
    $job->handle();

    // ✅ Verify status updated to active
    $rule->refresh();
    $this->assertEquals('active', $rule->status);
}

public function test_job_updates_status_to_failed_on_error(): void
{
    $rule = ServerFirewallRule::factory()->create(['status' => 'pending']);

    // Mock SSH for failure
    $mockSsh = $this->mockFailedSsh();

    $job = new FirewallRuleInstallerJob($server, $rule->id);

    try {
        $job->handle();
    } catch (\Exception $e) {
        // Expected to throw
    }

    // ✅ Verify status updated to failed
    $rule->refresh();
    $this->assertEquals('failed', $rule->status);
}

public function test_rule_status_update_dispatches_server_updated_event(): void
{
    Event::fake([ServerUpdated::class]);

    $rule = ServerFirewallRule::factory()->create(['status' => 'pending']);

    // Update status
    $rule->update(['status' => 'installing']);

    // ✅ Verify broadcast event dispatched
    Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
        return $event->serverId === $server->id;
    });
}
```

#### Migration to Reverb Package Lifecycle

To migrate existing packages to this pattern:

1. **Add status field** to the model's migration and fillable array
2. **Update controller** to create record first with 'pending' status
3. **Modify job** to accept record ID instead of data array
4. **Add status updates** in job's handle() method (installing, active, failed)
5. **Add model events** for automatic broadcasting (created, updated, deleted)
6. **Update frontend** to use `useEcho` instead of polling
7. **Write tests** for all lifecycle stages

For complete patterns and troubleshooting, see [docs/reverb-real-time-pattern.md](../docs/reverb-real-time-pattern.md)

### Quick Reference: Correct Pattern Summary

| Component | Responsibilities | What NOT to do |
|-----------|------------------|----------------|
| **Installer/Remover** | ALL logic, database ops, SSH commands, milestones, job dispatching | Don't use constructor for config |
| **Job** | Resource limits, provision_status, logging, dispatching installer | Don't add package logic, milestones, or SSH commands |
| **execute()** | Data prep, validation, orchestration, job dispatching, provision updates | Don't generate SSH commands here |
| **commands()** | SSH command strings and closures | Don't query database or validate |
| **Dispatch Pattern** | One job = one instance (loop and dispatch) | Don't batch multiple items in one job |

### Reference Files (Study These Examples)

**Perfect examples to study:**
1. `app/Packages/Services/Nginx/NginxInstaller.php` + `NginxInstallerJob.php` - Complex installer with dependencies
2. `app/Packages/Services/PHP/PhpInstaller.php` + `PhpInstallerJob.php` - Clean, minimal job pattern
3. `app/Packages/Services/Firewall/FirewallRuleInstallerJob.php` - One-job-per-instance pattern

## Job Integration

**⚠️ CRITICAL**: Before implementing job classes, review the **⚠️ CRITICAL ARCHITECTURAL RULES** section above (search for "CRITICAL ARCHITECTURAL RULES"). The patterns described there are MANDATORY for all job implementations. Violating these rules will cause runtime errors and architectural inconsistencies.

### Job Class Philosophy

**MANDATORY PATTERN**: Job classes MUST be lightweight wrappers. They handle queue orchestration (timeouts, resource limits, high-level status) but ALL package-specific logic MUST be in the installer/remover.

**What "Lightweight" Means:**
Jobs handle orchestration concerns like:
- ✅ Setting `set_time_limit(0)` and `$timeout` for long operations
- ✅ Updating `provision_status` for UI feedback (NOT package-specific data)
- ✅ Logging and error handling
- ✅ Creating installer and calling `execute()`

Jobs are FORBIDDEN from package-specific concerns:
- ❌ Business logic or validation
- ❌ SSH command generation
- ❌ Creating package-specific database records (sites, firewall rules, scheduled tasks, etc.)
- ❌ Tracking milestones (installer does this)
- ❌ Using `persist()` method (installer does this)

### The Core Job Pattern

Every job's `handle()` method should follow this pattern from `NginxInstallerJob`:

```php
public function handle(): void
{
    // Set no time limit for long-running installation process
    set_time_limit(0);

    Log::info("Starting Nginx installation for server #{$this->server->id} with PHP {$this->phpVersion->value}");

    try {
        // Create installer instance
        $installer = new NginxInstaller($this->server);

        if ($this->isProvisioningServer) {
            $this->server->update(['provision_status' => ProvisionStatus::Installing]);
        }

        // Execute installation - the installer handles all logic, database tracking, and dependencies
        $installer->execute($this->phpVersion);

        Log::info("Nginx installation completed for server #{$this->server->id}");

        if ($this->isProvisioningServer) {
            $this->server->update(['provision_status' => ProvisionStatus::Completed]);
        }

    } catch (Exception $e) {
        if ($this->isProvisioningServer) {
            $this->server->update(['provision_status' => ProvisionStatus::Failed]);
        }
        Log::error("Nginx installation failed for server #{$this->server->id}", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        throw $e;
    }
}
```

**Jobs ARE lightweight wrappers, but they handle:**
1. ✅ Resource limits (`set_time_limit(0)` for long operations)
2. ✅ High-level status tracking (provision_status for UI feedback)
3. ✅ Logging start/completion
4. ✅ Creating installer and dispatching
5. ✅ Error handling and status updates

**Jobs still NEVER:**
- ❌ Contain business logic or validation
- ❌ Generate SSH commands
- ❌ Create package-specific database records (sites, firewall rules, etc.)
- ❌ Track milestones (installer handles this)
- ❌ Use the `persist()` method (installer handles this)

### Correct Job Class Structure

Every installer should have a corresponding job for queue processing. Here's the correct pattern based on `NginxInstallerJob`:

```php
<?php

namespace App\Packages\Services\{Category};

use App\Models\Server;
use App\Packages\Enums\{SpecificConfiguration};
use App\Packages\Enums\ProvisionStatus;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * {ServiceName} Installation Job
 *
 * Handles queued {service} installation on remote servers
 */
class {ServiceName}InstallerJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out.
     */
    public $timeout = 600;

    public function __construct(
        public Server $server,
        public {ConfigurationType} $configuration,
        public bool $isProvisioningServer = false
    ) {}

    public function handle(): void
    {
        // Set no time limit for long-running installation process
        set_time_limit(0);

        Log::info("Starting {service} installation for server #{$this->server->id}");

        try {
            // Create installer instance
            $installer = new {ServiceName}Installer($this->server);

            if ($this->isProvisioningServer) {
                $this->server->update(['provision_status' => ProvisionStatus::Installing]);
            }

            // Execute installation - the installer handles all logic, database tracking, and dependencies
            $installer->execute($this->configuration);

            Log::info("{Service} installation completed for server #{$this->server->id}");

            if ($this->isProvisioningServer) {
                $this->server->update(['provision_status' => ProvisionStatus::Completed]);
            }

        } catch (Exception $e) {
            if ($this->isProvisioningServer) {
                $this->server->update(['provision_status' => ProvisionStatus::Failed]);
            }
            Log::error("{Service} installation failed for server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
```

### Real-World Example: Nginx Installer Job

This example demonstrates the correct pattern from the actual codebase:

```php
<?php

namespace App\Packages\Services\Nginx;

use App\Models\Server;
use App\Packages\Enums\PhpVersion;
use App\Packages\Enums\ProvisionStatus;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Nginx Installation Job
 *
 * Handles queued Nginx web server installation on remote servers
 */
class NginxInstallerJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out.
     */
    public $timeout = 600;

    public function __construct(
        public Server $server,
        public PhpVersion $phpVersion,
        public bool $isProvisioningServer = false
    ) {}

    public function handle(): void
    {
        // Set no time limit for long-running installation process
        set_time_limit(0);

        Log::info("Starting Nginx installation for server #{$this->server->id} with PHP {$this->phpVersion->value}");

        try {
            // Create installer instance
            $installer = new NginxInstaller($this->server);

            if ($this->isProvisioningServer) {
                $this->server->update(['provision_status' => ProvisionStatus::Installing]);
            }

            // Execute installation - the installer handles all logic, database tracking, and dependencies
            $installer->execute($this->phpVersion);

            Log::info("Nginx installation completed for server #{$this->server->id}");

            if ($this->isProvisioningServer) {
                $this->server->update(['provision_status' => ProvisionStatus::Completed]);
            }

        } catch (Exception $e) {
            if ($this->isProvisioningServer) {
                $this->server->update(['provision_status' => ProvisionStatus::Failed]);
            }
            Log::error("Nginx installation failed for server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
```

### Why Jobs Should Be Lightweight (But Not Minimal)

Jobs ARE lightweight wrappers, but they handle important orchestration:

1. **Single Responsibility**: Jobs handle queue orchestration, installers handle installation logic
2. **Resource Management**: Jobs set timeouts and time limits for long-running processes
3. **High-Level Status**: Jobs update provision_status for UI feedback (NOT package-specific data)
4. **Centralized Logic**: All business logic, database persistence, and SSH commands stay in installers
5. **Testability**: Lightweight jobs are easier to test than fat jobs
6. **Maintainability**: Installation logic stays in one place (the installer/remover)

### Job Integration Best Practices

1. **Set Resource Limits**: Use `set_time_limit(0)` and `$timeout` property for long-running installations
2. **Track High-Level Status**: Update `provision_status` for UI feedback when `isProvisioningServer` is true
3. **No Package Logic**: Let installers handle ALL business logic, database persistence, and SSH commands
4. **No Milestone Tracking**: Installers track milestones, NOT jobs
5. **Error Handling**: Update provision_status on failure, then re-throw for Laravel's retry mechanism
6. **Configuration**: Pass configuration through constructor parameters to the installer's `execute()` method

## Service Type Classification

Use the `PackageName` enum to categorize services:

```php
class PackageName
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

        $this->assertEquals(PackageName::{CATEGORY}, $installer->serviceType());
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
use App\Models\ServerPackage;
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
        $this->assertDatabaseHas('server_packages', [
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
        $package = ServerPackage::where('server_id', $server->id)->first();
        $this->assertEquals('failed', $package?->status);
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

    // Test real ServerPackageEvent creation
    $this->assertDatabaseHas('server_package_events', [
        'server_id' => $server->id,
        'service_type' => PackageName::{CATEGORY},
        'provision_type' => 'install',
    ]);
}
```

### What to Mock vs. What to Test with Real Implementations

**✅ Use Real Implementations For:**
- Database operations and Eloquent models
- Laravel framework features (views, config, etc.)
- Internal application logic
- Milestone tracking and ServerPackageEvent creation
- Server and ServerPackage model updates
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
3. **⚠️ ALWAYS Implement ServerPackage or SitePackage Interface**: EVERY installer AND remover MUST explicitly implement either `ServerPackage` or `SitePackage` interface. Forgetting this will cause "Unknown package type" runtime errors
4. **🆕 Use Reverb Package Lifecycle for Real-Time Status**: When building packages needing real-time updates (firewall rules, scheduled tasks, SSL certificates), **MUST** create database record FIRST with `status: 'pending'`, then job manages lifecycle (pending → installing → active/failed) with automatic Reverb broadcasting. See Rule 6 in Critical Architectural Rules.
5. **Follow Naming Conventions**: Use consistent naming for classes and files
6. **Implement All Required Methods**: Every package must implement the abstract methods (`execute()`, `commands()`, etc.)
7. **Use Package Patterns Universally**: ALL packages, including single command executors, must follow the `execute()` and `commands()` pattern - no standalone `run()` methods
8. **Use Parameters, Not Constructors**: Pass configuration via `execute()` and `commands()` parameters
9. **Avoid Additional Methods**: Keep ALL logic within `execute()` and `commands()` methods - no helper methods
10. **Use Appropriate Credentials**: Choose the right SSH credential type for the task (Root for server-level, BrokeForge for site-level)
11. **Track Progress**: Implement comprehensive milestone tracking for all package types
12. **Handle Errors Gracefully**: Use try-catch blocks and proper logging
13. **Test Thoroughly**: Write both unit and feature tests (including lifecycle status transitions for Reverb packages)
14. **Document Well**: Use PHPDoc blocks to describe functionality
15. **Keep It Simple**: Break complex operations into smaller, manageable steps
16. **Use Enums**: Leverage type safety with enum constants
17. **Follow Laravel Conventions**: Use Laravel best practices throughout

## Integration with BrokeForge

### Server Model Integration

Packages automatically integrate with the `Server` model:
- Access server properties via `$this->server`
- Update server status through the model
- Create related `ServerPackage` records

### Queue Integration

All package jobs should:
- Implement `ShouldQueue` interface
- Use `Queueable` trait
- Handle failures gracefully
- Update database records appropriately

### Frontend Integration

#### Progress Tracking with Event Models

##### Server-Level Package Events (ServerPackageEvent)

The frontend tracks server-level package installation/removal progress by querying the `ServerPackageEvent` model. Each milestone tracked in server packages automatically creates `ServerPackageEvent` records.

**ServerPackageEvent Model Structure:**
```php
// app/Models/ServerPackageEvent.php
class ServerPackageEvent extends Model
{
    protected $fillable = [
        'server_id',        // Server being provisioned
        'service_type',     // PackageName enum value (database, webserver, etc.)
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

##### Site-Level Package Events (ServerSitePackageEvent)

For site-level packages, use `ServerSitePackageEvent` which includes site association:

**ServerSitePackageEvent Model Structure:**
```php
// app/Models/ServerSitePackageEvent.php
class ServerSitePackageEvent extends Model
{
    protected $fillable = [
        'server_id',        // Server containing the site
        'site_id',          // Specific site being modified
        'service_type',     // Service type (git, command, etc.)
        'provision_type',   // 'install' or 'uninstall'
        'milestone',        // Milestone constant
        'current_step',     // Current step number
        'total_steps',      // Total number of steps
        'details',          // Additional metadata (array)
        'status',          // Event status
        'error_log',       // Error details if failed
    ];

    // Automatic progress percentage calculation
    public function getProgressPercentageAttribute(): string
    {
        if ($this->total_steps == 0) {
            return "0";
        }
        return str(($this->current_step / $this->total_steps) * 100);
    }

    // Relationship methods
    public function server(): BelongsTo;
    public function site(): BelongsTo;

    // Helper methods
    public function isInstall(): bool;
    public function isUninstall(): bool;
    public function isPending(): bool;
    public function isSuccess(): bool;
    public function isFailed(): bool;
}
```

**Frontend Progress Queries:**

```php
// Get latest progress for a specific service installation
$latestProgress = ServerPackageEvent::where('server_id', $serverId)
    ->where('service_type', 'webserver')
    ->where('provision_type', 'install')
    ->latest()
    ->first();

$progressPercentage = $latestProgress?->progress_percentage ?? 0;
$currentMilestone = $latestProgress?->milestone;

// Get all progress events for detailed step-by-step display
$progressEvents = ServerPackageEvent::where('server_id', $serverId)
    ->where('service_type', 'webserver')
    ->where('provision_type', 'install')
    ->orderBy('created_at')
    ->get();

// Check if installation is complete (last milestone)
$isComplete = ServerPackageEvent::where('server_id', $serverId)
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
        'progress' => ServerPackageEvent::where('server_id', $server->id)
            ->latest()
            ->first(),
        'allEvents' => ServerPackageEvent::where('server_id', $server->id)
            ->orderBy('created_at')
            ->get(),
    ]);
}
```

```typescript
// React component for progress tracking
interface ServerPackageEvent {
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
