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
- `credentialType()`: Returns `CredentialType` enum (Root or BrokeForge)
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
│   ├── Credential/         # SSH credential management services
│   │   ├── ServerCredentialConnection.php  # Creates authenticated SSH connections
│   │   ├── SshKeyGenerator.php       # Generates SSH key pairs
│   │   └── TempKeyFile.php           # Manages temp key file lifecycle
│   ├── Nginx/              # Server-level (ServerPackage)
│   ├── PHP/                # Server-level (ServerPackage)
│   ├── Database/           # Server-level (ServerPackage)
│   ├── Firewall/           # Server-level (ServerPackage)
│   └── Sites/              # Site-level (SitePackage)
│       ├── SiteInstaller.php
│       ├── Git/
│       └── Command/
└── Enums/                  # Type definitions
    ├── CredentialType.php  # Root, BrokeForge enum with username resolution
    ├── PackageName.php
    └── PackageType.php
```

**Example Package Structure:**
```php
class NginxInstaller extends PackageInstaller implements ServerPackage
{
    public function packageName(): PackageName { return PackageName::Nginx; }
    public function packageType(): PackageType { return PackageType::ReverseProxy; }
    public function credentialType(): CredentialType { return CredentialType::Root; }
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
- Two credential types per server (defined by `CredentialType` enum):
  - **CredentialType::Root**: System-level operations (package installs, service management) - username: `root`
  - **CredentialType::BrokeForge**: Site-level operations (Git, deployments, site management) - username: `brokeforge`
- Generated during provisioning via `SshKeyGenerator` service
- Retrieved via `$server->credential(CredentialType::BrokeForge)` or `$server->credential('brokeforge')` (both supported)
- Username resolution centralized in `CredentialType::username()` method

**File Permissions & Ownership:**
- All files in `/home/brokeforge/` are owned by `brokeforge:brokeforge` with 775 permissions
- BrokeForge user has full permissions only within `/home/brokeforge/` directory
- Git repositories must add safe.directory config: `git config --global --add safe.directory <path>` to allow brokeforge user Git operations

**Credential Services** (`app/Packages/Services/Credential/`):
- `SshKeyGenerator`: Generates SSH key pairs for server credentials
- `ServerCredentialConnection`: Creates authenticated SSH connections using credentials
- `TempKeyFile`: Value object managing temporary key file lifecycle (auto-cleanup via destructor)

**SSH Connection Creation:**
```php
// Create authenticated SSH connection
$ssh = $server->createSshConnection(CredentialType::Root);
$result = $ssh->execute('whoami');

// Or using string (backward compatibility)
$ssh = $server->createSshConnection('brokeforge');
```

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

**High-Level Overview:**

1. User creates server via `ServerController@store`
2. Server model generates encrypted root password automatically
3. `ProvisionAccess` generates unique SSH keys (root/brokeforge) via `SshKeyGenerator`
4. User manually runs provision script on remote server
5. Script deploys SSH keys and calls back to `ProvisionCallbackController`
6. Callback verifies SSH access and dispatches package installation jobs (Nginx, PHP, Firewall)
7. Each job tracks progress via `ServerEvent` records

**Detailed Provisioning Process:**

**Step 1: Server Creation & Key Generation**
```php
// ServerController creates server
$server = Server::create([...]);

// ProvisionAccess generates script with embedded keys
$provisionAccess = new ProvisionAccess();
$script = $provisionAccess->makeScriptFor($server, $rootPassword);

// SshKeyGenerator creates unique RSA 4096-bit keys for each credential type
foreach ([CredentialType::Root, CredentialType::BrokeForge] as $type) {
    ServerCredential::generateKeyPair($server, $type);
}
```

**Step 2: Remote Script Execution** (`resources/views/scripts/provision_setup_x64.blade.php`)

User manually executes on remote server:
```bash
wget -O script.sh "https://brokeforge.app/servers/{id}/provision"
bash script.sh
```

Script performs these operations:
1. **Sends "started" callback** to `ProvisionCallbackController`
2. **Creates users**: root and brokeforge
3. **Configures Git**: Sets up Git for brokeforge user
4. **Deploys SSH keys** to each user's `~/.ssh/` directory:
   - Private key: `~/.ssh/id_rsa` (0600 permissions)
   - Public key: `~/.ssh/id_rsa.pub` (0644 permissions)
   - Authorized keys: `~/.ssh/authorized_keys` (0600 permissions, overwritten not appended)
5. **Configures SSH**: Disables password auth, enables pubkey auth, restarts sshd
6. **Sets directory permissions**: Home directories set to 755 (SSH requires non-group-writable)
7. **Verifies SSH locally**: Tests `ssh user@localhost whoami` for both users
8. **Sends "completed" callback** with debugging output:
   ```
   ================================================
     BrokeForge Provision Summary
   ================================================
   Server IP:   192.168.2.27
   Root user:   root
   App user:    brokeforge
   SSH Port:    22
   Callback:    Sending completion notification...
   ================================================
   ```

**Step 3: Callback Verification** (`ProvisionCallbackController`)

Upon receiving "completed" callback:
```php
// Verify SSH access from BrokeForge to remote server
foreach (CredentialType::cases() as $credentialType) {
    $ssh = $server->createSshConnection($credentialType);
    $result = $ssh->execute('whoami');

    // Must match expected username
    if (trim($result->getOutput()) !== $credentialType->username()) {
        // Mark provision as failed
    }
}

// If all verifications pass, dispatch installation jobs
NginxInstallerJob::dispatch($server, PhpVersion::PHP83);
```

**Step 4: Package Installation via SSH**

BrokeForge connects to remote server and executes package installations:
```php
// PackageManager uses ServerCredentialConnection to create authenticated connections
$ssh = $server->createSshConnection($this->credentialType());

// Executes commands array sequentially
foreach ($this->commands() as $command) {
    if (is_string($command)) {
        $ssh->execute($command); // Remote SSH command
    } else {
        $command(); // Local closure (DB operations, tracking)
    }
}
```

**Critical SSH Key Requirements:**
- **MUST preserve trailing newline** in private keys - SSH will reject keys without it
- `SshKeyGenerator` does NOT trim private keys (only public keys)
- `TempKeyFile` writes keys to temp files with 0600 permissions
- `ServerCredentialConnection` keeps temp files alive via static array until script ends

**Common Provisioning Issues:**

1. **SSH Verification Failures**: Home directory must be 755, not 775 - SSH rejects group-writable home directories
2. **Git "dubious ownership" errors**: BrokeForge user operating on repositories requires `git config --global --add safe.directory <path>`
3. **Permission denied on git operations**: Ensure directories are `brokeforge:brokeforge` with 775 permissions
4. **Authorized_keys accumulation**: Script overwrites (not appends) to prevent old keys from previous provisions

**Debugging Failed Provisions:**

Check provision script output for:
- `[✓] All SSH users verified successfully` - Local verification passed
- `[✓] started callback sent successfully` - BrokeForge received notification
- `[✓] completed callback sent successfully` - BrokeForge notified completion

Check Laravel logs for:
- SSH connection errors (invalid key format, permission denied)
- Callback verification failures (whoami returning empty/wrong username)
- Package installation progress and errors

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
- `ServerCredential`: Per-server SSH keys (root/brokeforge)
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
- `app/Packages/Services/Credential/`: SSH credential management services
  - `ServerCredentialConnection.php`: Creates authenticated SSH connections
  - `SshKeyGenerator.php`: Generates SSH key pairs
  - `TempKeyFile.php`: Temp file lifecycle management
- `app/Packages/Enums/CredentialType.php`: Credential type enum with username resolution
- `app/Models/Server.php`: Server model with credential relationships and `createSshConnection()` method
- `app/Models/ServerCredential.php`: Credential model with key generation and username methods
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

### Debugging Remote Servers

**Using Tinker for Remote Debugging:**

When debugging issues on remote servers, use `php artisan tinker` to execute commands directly on the remote host via SSH:

```php
$server = \App\Models\Server::find(1);
$ssh = $server->createSshConnection(\App\Packages\Enums\CredentialType::Root);

// Execute commands and inspect output
$result = $ssh->execute('systemctl status some-service');
echo $result->getOutput();
echo $result->getErrorOutput();
echo $result->getExitCode();

// Check logs
$result = $ssh->execute('journalctl -u some-service -n 50 --no-pager');
echo $result->getOutput();

// Verify file contents
$result = $ssh->execute('cat /path/to/file');
echo $result->getOutput();

// Fix issues directly
$ssh->execute('systemctl restart some-service');
```

**Common Debugging Patterns:**

```php
// Check if service is running
$ssh->execute('systemctl is-active service-name');

// View recent logs
$ssh->execute('journalctl -u service-name --since "10 minutes ago" --no-pager');

// Test script execution
$result = $ssh->execute('/path/to/script.sh 2>&1');
echo "Exit code: " . $result->getExitCode();

// Recreate files from Blade templates
$content = view('monitoring.metrics-collector', [...])->render();
$command = "cat > /path/to/file << 'EOF'\n{$content}\nEOF";
$ssh->execute($command);
```

This approach is especially useful for:
- Diagnosing why systemd services are failing
- Verifying file permissions and ownership
- Testing scripts before package deployment
- Hot-fixing issues without full reinstallation

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

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.12
- inertiajs/inertia-laravel (INERTIA) - v2
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/socialite (SOCIALITE) - v5
- laravel/wayfinder (WAYFINDER) - v0
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v11
- @inertiajs/react (INERTIA) - v2
- react (REACT) - v19
- tailwindcss (TAILWINDCSS) - v4
- @laravel/vite-plugin-wayfinder (WAYFINDER) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3


## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure - don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.


=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double check the available parameters.

## URLs
- Whenever you share a project URL with the user you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain / IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation specific for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The 'search-docs' tool is perfect for all Laravel related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel-ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries - package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit"
3. Quoted Phrases (Exact Position) - query="infinite scroll" - Words must be adjacent and in that order
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit"
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms


=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over comments. Never use comments within the code itself unless there is something _very_ complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.


=== inertia-laravel/core rules ===

## Inertia Core

- Inertia.js components should be placed in the `resources/js/Pages` directory unless specified differently in the JS bundler (vite.config.js).
- Use `Inertia::render()` for server-side routing instead of traditional Blade views.
- Use `search-docs` for accurate guidance on all things Inertia.

<code-snippet lang="php" name="Inertia::render Example">
// routes/web.php example
Route::get('/users', function () {
    return Inertia::render('Users/Index', [
        'users' => User::all()
    ]);
});
</code-snippet>


=== inertia-laravel/v2 rules ===

## Inertia v2

- Make use of all Inertia features from v1 & v2. Check the documentation before making any changes to ensure we are taking the correct approach.

### Inertia v2 New Features
- Polling
- Prefetching
- Deferred props
- Infinite scrolling using merging props and `WhenVisible`
- Lazy loading data on scroll

### Deferred Props & Empty States
- When using deferred props on the frontend, you should add a nice empty state with pulsing / animated skeleton.

### Inertia Form General Guidance
- The recommended way to build forms when using Inertia is with the `<Form>` component - a useful example is below. Use `search-docs` with a query of `form component` for guidance.
- Forms can also be built using the `useForm` helper for more programmatic control, or to follow existing conventions. Use `search-docs` with a query of `useForm helper` for guidance.
- `resetOnError`, `resetOnSuccess`, and `setDefaultsOnSuccess` are available on the `<Form>` component. Use `search-docs` with a query of 'form component resetting' for guidance.


=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation
- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation
- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration
- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing
- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] <name>` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error
- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.


=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version specific documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

### Laravel 12 Structure
- No middleware files in `app/Http/Middleware/`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- **No app\Console\Kernel.php** - use `bootstrap/app.php` or `routes/console.php` for console configuration.
- **Commands auto-register** - files in `app/Console/Commands/` are automatically available and do not require manual registration.

### Database
- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 11 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models
- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.


=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.


=== phpunit/core rules ===

## PHPUnit Core

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit <name>` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should test all of the happy paths, failure paths, and weird paths.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files, these are core to the application.

### Running Tests
- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test`.
- To run all tests in a file: `php artisan test tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --filter=testName` (recommended after making a change to a related file).


=== inertia-react/core rules ===

## Inertia + React

- Use `router.visit()` or `<Link>` for navigation instead of traditional links.

<code-snippet name="Inertia Client Navigation" lang="react">

import { Link } from '@inertiajs/react'
<Link href="/">Home</Link>

</code-snippet>


=== inertia-react/v2/forms rules ===

## Inertia + React Forms

<code-snippet name="`<Form>` Component Example" lang="react">

import { Form } from '@inertiajs/react'

export default () => (
    <Form action="/users" method="post">
        {({
            errors,
            hasErrors,
            processing,
            wasSuccessful,
            recentlySuccessful,
            clearErrors,
            resetAndClearErrors,
            defaults
        }) => (
        <>
        <input type="text" name="name" />

        {errors.name && <div>{errors.name}</div>}

        <button type="submit" disabled={processing}>
            {processing ? 'Creating...' : 'Create User'}
        </button>

        {wasSuccessful && <div>User created successfully!</div>}
        </>
    )}
    </Form>
)

</code-snippet>


=== tailwindcss/core rules ===

## Tailwind Core

- Use Tailwind CSS classes to style HTML, check and use existing tailwind conventions within the project before writing your own.
- Offer to extract repeated patterns into components that match the project's conventions (i.e. Blade, JSX, Vue, etc..)
- Think through class placement, order, priority, and defaults - remove redundant classes, add classes to parent or child carefully to limit repetition, group elements logically
- You can use the `search-docs` tool to get exact examples from the official documentation when needed.

### Spacing
- When listing items, use gap utilities for spacing, don't use margins.

    <code-snippet name="Valid Flex Gap Spacing Example" lang="html">
        <div class="flex gap-8">
            <div>Superior</div>
            <div>Michigan</div>
            <div>Erie</div>
        </div>
    </code-snippet>


### Dark Mode
- If existing pages and components support dark mode, new pages and components must support dark mode in a similar way, typically using `dark:`.


=== tailwindcss/v4 rules ===

## Tailwind 4

- Always use Tailwind CSS v4 - do not use the deprecated utilities.
- `corePlugins` is not supported in Tailwind v4.
- In Tailwind v4, you import Tailwind using a regular CSS `@import` statement, not using the `@tailwind` directives used in v3:

<code-snippet name="Tailwind v4 Import Tailwind Diff" lang="diff">
   - @tailwind base;
   - @tailwind components;
   - @tailwind utilities;
   + @import "tailwindcss";
</code-snippet>


### Replaced Utilities
- Tailwind v4 removed deprecated utilities. Do not use the deprecated option - use the replacement.
- Opacity values are still numeric.

| Deprecated |	Replacement |
|------------+--------------|
| bg-opacity-* | bg-black/* |
| text-opacity-* | text-black/* |
| border-opacity-* | border-black/* |
| divide-opacity-* | divide-black/* |
| ring-opacity-* | ring-black/* |
| placeholder-opacity-* | placeholder-black/* |
| flex-shrink-* | shrink-* |
| flex-grow-* | grow-* |
| overflow-ellipsis | text-ellipsis |
| decoration-slice | box-decoration-slice |
| decoration-clone | box-decoration-clone |


=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test` with a specific filename or filter.
</laravel-boost-guidelines>
