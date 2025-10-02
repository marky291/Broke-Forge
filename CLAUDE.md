# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

BrokeForge is a server management and application deployment service for automating server provisioning and application deployments (Laravel, Symfony, WordPress, PHP, Node.js). It's currently **in active development** with no legacy code.

**Tech Stack:**
- Laravel 12 (PHP 8.2+)
- React 19 with Inertia.js 2.0
- TypeScript
- Tailwind CSS 4.0
- Queue-based background processing
- SSH-based remote server management via `spatie/ssh`

## Development Commands

### Running the Application

```bash
# Start all services (server, queue worker, logs, vite)
composer dev

# Start with SSR support
composer dev:ssr

# Individual services
php artisan serve
php artisan queue:listen --tries=1
php artisan pail --timeout=0
npm run dev
```

### Testing

```bash
# Run all tests
composer test

# Run specific test file
php artisan test tests/Feature/ExampleTest.php

# Run specific test method
php artisan test --filter=test_method_name
```

### Code Quality

```bash
# PHP formatting (Laravel Pint)
./vendor/bin/pint

# TypeScript/React linting
npm run lint

# TypeScript type checking
npm run types

# Format frontend code (Prettier)
npm run format
npm run format:check
```

### Frontend Build

```bash
# Development build
npm run dev

# Production build
npm run build

# SSR build
npm run build:ssr
```

### Database

```bash
# Run migrations
php artisan migrate

# Fresh migration with seeding
php artisan migrate:fresh --seed

# Create new migration
php artisan make:migration create_table_name
```

## Architecture Overview

### Package System (Core Abstraction)

BrokeForge uses a **Package System** for all server provisioning operations. This is the most critical architectural pattern to understand.

**Location:** `app/Packages/`

**Key Concepts:**

1. **Package Interface** (`app/Packages/Base/Package.php`): All packages implement this interface
2. **Package Manager** (`app/Packages/Base/PackageManager.php`): Base class providing SSH execution, milestone tracking, and error handling
3. **Package Types:**
   - **ServerPackage**: Server-level operations (Nginx, MySQL, PHP, Firewall) - uses root credentials
   - **SitePackage**: Site-level operations (site creation, Git, commands) - uses user credentials

**Required Methods for All Packages:**
- `packageName()`: Returns `PackageName` enum
- `packageType()`: Returns `PackageType` enum
- `milestones()`: Returns milestone tracker for progress
- `sshCredential()`: Returns SSH credential type (RootCredential, UserCredential, or WorkerCredential)
- `execute()`: Entry point for package execution (can accept custom parameters)
- `commands()`: Returns array of SSH commands and closures to execute

**Directory Structure:**
```
app/Packages/
├── Base/                    # Abstract base classes
│   ├── Package.php          # Interface all packages implement
│   ├── PackageManager.php   # Base class with SSH/milestone logic
│   ├── PackageInstaller.php # Base for installers
│   ├── PackageRemover.php   # Base for removers
│   └── Milestones.php       # Progress tracking
├── Services/
│   ├── Nginx/              # Server-level (ServerPackage)
│   ├── PHP/                # Server-level (ServerPackage)
│   ├── Database/           # Server-level (ServerPackage)
│   ├── Firewall/           # Server-level (ServerPackage)
│   └── Sites/              # Site-level (SitePackage)
│       ├── SiteInstaller.php
│       ├── Git/
│       └── Command/
└── Enums/                  # Type definitions
```

**Example Package Structure:**
```php
class NginxInstaller extends PackageInstaller implements ServerPackage
{
    public function packageName(): PackageName { return PackageName::Nginx; }
    public function packageType(): PackageType { return PackageType::ReverseProxy; }
    public function sshCredential(): SshCredential { return new RootCredential; }
    public function milestones(): Milestones { return new NginxInstallerMilestones; }

    public function execute(): void {
        // Prepare data, then call install with commands
        $this->install($this->commands());
    }

    protected function commands(): array {
        return [
            $this->track(NginxInstallerMilestones::PREPARE_SYSTEM),
            'apt-get update -y',
            $this->track(NginxInstallerMilestones::INSTALL_SOFTWARE),
            'apt-get install -y nginx',
            // Closures for database operations
            fn() => $this->server->update(['status' => 'active']),
            $this->track(NginxInstallerMilestones::COMPLETE),
        ];
    }
}
```

**Important:** Review `app/Packages/README.md` for comprehensive package development guidelines before creating new packages.

### SSH Credential Management

**Per-Server Encrypted Credentials:**
- Each server has unique SSH key pairs stored encrypted in `server_credentials` table
- Three credential types per server:
  - **root**: Server-level operations (ServerPackage implementations)
  - **user**: Site-level operations (SitePackage implementations)
  - **worker**: Git operations
- Generated during provisioning via `ProvisionAccess` class
- Retrieved via `$server->credential('root'|'user'|'worker')`

**Root Password:**
- Stored encrypted on `Server` model as `ssh_root_password`
- Auto-generated on server creation
- Used during initial provisioning only

### Job System

All package operations are dispatched as Laravel queue jobs:

```php
class NginxInstallerJob implements ShouldQueue
{
    public function __construct(public Server $server, public PhpVersion $phpVersion) {}

    public function handle(): void {
        $installer = new NginxInstaller($this->server);
        $installer->execute($this->phpVersion);
    }
}
```

**Jobs are lightweight wrappers** - all business logic belongs in the package classes.

### Progress Tracking & Milestones

**Server-Level Packages** create `ServerEvent` records:
- Tracks installation/removal progress
- Frontend polls these events for real-time updates
- Automatic percentage calculation based on milestone steps

**Milestone Pattern:**
```php
class NginxInstallerMilestones extends Milestones
{
    public const PREPARE_SYSTEM = 'prepare_system';
    public const INSTALL_SOFTWARE = 'install_software';
    public const COMPLETE = 'complete';

    private const LABELS = [
        self::PREPARE_SYSTEM => 'Preparing system',
        self::INSTALL_SOFTWARE => 'Installing software',
        self::COMPLETE => 'Installation complete',
    ];
}
```

### Provisioning Flow

1. User creates server via `ServerController@store`
2. Server model generates encrypted root password automatically
3. `ProvisionAccess` generates unique SSH keys (root/user/worker)
4. Provision script (`resources/views/scripts/provision_setup_x64.blade.php`) runs on remote server
5. Script deploys SSH keys and calls back to `ProvisionCallbackController`
6. Callback dispatches package installation jobs (Nginx, PHP, Firewall)
7. Each job tracks progress via `ServerEvent` records

### Frontend Architecture

**Inertia.js with React:**
- Server-side routing via Laravel routes
- Pages in `resources/js/pages/`
- Shared components in `resources/js/components/`
- Type-safe with TypeScript

**Key Pages:**
- `dashboard.tsx`: Main overview
- `servers/*.tsx`: Server management UI
- `servers/provisioning.tsx`: Real-time provision tracking

**Progress Tracking:**
Frontend polls `ServerEvent` records to show installation progress:
```typescript
// Components fetch latest event and display progress_percentage
const progress = server.events.latest;
<Progress value={progress.current_step / progress.total_steps * 100} />
```

### Database Models

**Core Models:**
- `Server`: Server instances with encrypted credentials
- `ServerCredential`: Per-server SSH keys (root/user/worker)
- `ServerEvent`: Package installation/removal progress tracking
- `ServerSite`: Sites hosted on servers
- `ServerPhp`: PHP versions installed
- `ServerDatabase`: Database instances
- `ServerFirewall`: Firewall configurations

**Relationships:**
- Server has many credentials, events, sites, databases
- Events belong to server and track package operations
- Sites belong to server

## Key Files to Reference

- `app/Packages/README.md`: Comprehensive package development guide
- `app/Packages/Base/PackageManager.php`: Core SSH execution and milestone tracking
- `app/Packages/Services/Nginx/NginxInstaller.php`: Reference implementation
- `app/Models/Server.php`: Server model with credential relationships
- `app/Packages/ProvisionAccess.php`: Provision script generation
- `resources/views/scripts/provision_setup_x64.blade.php`: Remote server provisioning script

## Development Guidelines

### Creating New Packages

1. **Always review existing packages first** - reuse patterns from `NginxInstaller`, `SiteInstaller`, etc.
2. Extend `PackageInstaller` or `PackageRemover`
3. Implement `ServerPackage` (server-level) or `SitePackage` (site-level)
4. Keep logic in `execute()` and `commands()` methods only
5. Use Blade templates for configuration files
6. Create corresponding `Job` class as lightweight wrapper
7. Create `Milestones` class for progress tracking

### SSH Command Patterns

Commands array accepts:
- **Strings**: Shell commands to execute
- **Closures**: Database operations, tracking milestones
- Mix both for complex operations

```php
protected function commands(): array {
    return [
        $this->track(Milestone::START),
        'apt-get update',
        fn() => $this->server->update(['status' => 'installing']),
        'systemctl restart nginx',
        $this->track(Milestone::COMPLETE),
    ];
}
```

### Testing Approach

- Use `RefreshDatabase` trait for integration tests
- Only mock SSH connections (`Spatie\Ssh\Ssh`) - test everything else with real implementations
- Test milestone tracking creates `ServerEvent` records
- Test job dispatching and completion

## Configuration Notes

- Development server runs on `192.168.2.1` (configured in composer.json)
- Queue worker runs with `--tries=1` (fail fast during development)
- Concurrent services managed via `concurrently` package
- Database: SQLite for development, configurable for production

## Important Reminders

- Do what has been asked; nothing more, nothing less
- NEVER create files unless absolutely necessary
- ALWAYS prefer editing existing files to creating new ones
- NEVER proactively create documentation files (*.md) or README files unless explicitly requested
- Review `app/Packages/README.md` before creating any new packages - it contains comprehensive guidelines
- When working with Blade templates, use single quotes for literal strings to avoid bash variable expansion issues
