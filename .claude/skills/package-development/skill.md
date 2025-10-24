---
name: Package Development
description: Use this skill when building packages with real-time status updates (firewall rules, scheduled tasks, SSL certificates, deployments). Automatically invoked for implementing the Reverb Package Lifecycle Pattern. Triggered by prompts like "create firewall rule package", "add scheduled tasks feature", "implement SSL management", or any package requiring real-time installation/removal status updates.
allowed-tools: Bash(php artisan*), Bash(vendor/bin/pint*), Read, Write, Edit, Glob, Grep, mcp__laravel-boost__*
---

# Package Development - Reverb Package Lifecycle Pattern

## When to Use This Pattern

**✅ MANDATORY when:**
- Users need to see **real-time** installation/removal progress
- Operation takes **>2 seconds** to complete
- Resource has status transitions: `pending → installing → active/failed`
- Creating **user-facing resources** (firewall rules, scheduled tasks, SSL certificates, deployments, cron jobs)

**❌ DON'T USE when:**
- Initial server provisioning (one-time setup)
- Operations complete in <2 seconds
- Infrastructure-only packages with no user-facing resources
- No meaningful status transitions

## Core Philosophy

**Event-driven architecture:** Model changes automatically trigger Reverb broadcasts → Frontend fetches fresh resource data via Inertia. **No polling required.**

**Key principle:** Create database record FIRST with `status: 'pending'`, then job manages lifecycle while model events automatically broadcast changes.

## Preventing Concurrent Server Operations

**⚠️ MANDATORY for all package jobs** - Prevent dpkg lock conflicts by ensuring only ONE package operation runs per server at a time.

### The Problem
Debian/Ubuntu's package manager (`apt-get`, `dpkg`) uses file-based locks (`/var/lib/dpkg/lock-frontend`). Running multiple package operations on the same server simultaneously causes lock conflicts and job failures.

### The Solution: WithoutOverlapping Middleware
Use Laravel's `WithoutOverlapping` job middleware to serialize jobs per server:

```php
use Illuminate\Queue\Middleware\WithoutOverlapping;

public $timeout = 600;
public $tries = 0;           // Unlimited attempts (lock waits don't fail job)
public $maxExceptions = 3;   // Limit actual execution failures

public function middleware(): array
{
    return [
        (new WithoutOverlapping("package:action:{$this->server->id}"))->shared()
            ->releaseAfter(15)      // Wait 15 seconds before retrying when lock is held
            ->expireAfter(900),     // Lock expires after 15 minutes (safety)
    ];
}
```

**Configuration:**
- `$timeout = 600` - Maximum execution time in seconds (10 minutes)
- `$tries = 0` - Unlimited attempts allows infinite retries when encountering locks
- `$maxExceptions = 3` - Only actual execution failures count toward failure limit
- `releaseAfter(15)` - Wait 15 seconds before retrying when lock is held
- `expireAfter(900)` - Lock auto-expires after 15 minutes if job crashes
- **Lock key: `"package:action:{$this->server->id}"`** - Explicit shared lock key for ALL package jobs on the same server
- **`->shared()`** - Ensures the lock is shared across different job classes

**⚠️ CRITICAL: Why `$tries = 0` with `$maxExceptions = 3`?**

Middlewares like `WithoutOverlapping` consume attempts from the `$tries` count. When a job encounters the lock:
- Middleware releases the job back to the queue
- This counts as one "attempt" toward `$tries`
- With `$tries = 3`, jobs can fail after 3 lock encounters WITHOUT ever executing

**The Solution:**
- `$tries = 0` - Unlimited attempts (lock waits won't cause permanent failure)
- `$maxExceptions = 3` - Only ACTUAL execution exceptions count toward the 3-failure limit

This ensures jobs will infinitely retry when encountering locks (waiting for other jobs to complete) but still protect from infinite failure loops via `$maxExceptions`.

**⚠️ IMPORTANT: Why Custom Lock Key?**
By default, `WithoutOverlapping($this->server->id)` includes the job class name in the lock key:
- `MySqlInstallerJob` → `laravel:queue:overlapping:MySqlInstallerJob:1`
- `RedisInstallerJob` → `laravel:queue:overlapping:RedisInstallerJob:1`

These are DIFFERENT locks and won't prevent concurrent execution! Using a custom shared key like `"package:action:{$this->server->id}"` with `->shared()` ensures ALL package jobs (MySQL, Redis, PHP, Nginx, Firewall, etc.) use the SAME lock for a server, preventing dpkg conflicts.

**Behavior:**
- Job stays in "pending" status while waiting for lock to be acquired
- Lock waits: Released back to queue (infinite retries with `$tries = 0`)
- Actual failures: Counted towards `$maxExceptions` limit (3 max)
- Different servers can run operations in parallel (isolated by server ID)
- No code changes needed in `handle()` method - middleware handles everything

**Example Timeline:**
1. T=0s: Job 1 (PHP installer) starts on Server #5 → acquires lock
2. T=5s: Job 2 (MySQL installer) tries Server #5 → can't get lock → released (will retry infinitely)
3. T=35s: Job 2 retries → still locked → released (will retry infinitely)
4. T=240s: Job 1 completes → releases lock
5. T=245s: Job 2 retries → gets lock → runs successfully ✅
6. If Job 2 throws exception → catches, increments exception count (1/3) → retries
7. If Job 2 throws 3 exceptions → `$maxExceptions` limit reached → permanently fails

**Reference:** Laravel Queue Middleware documentation - `WithoutOverlapping` middleware

## Quick Implementation Checklist

### 1. Database & Model Setup
- [ ] Migration has `status` column (string, default `'pending'`)
- [ ] Model includes `'status'` in `$fillable`
- [ ] Status enum created with all lifecycle states
- [ ] Model has `booted()` method with event listeners (`created`, `updated`, `deleted`)

### 2. Backend Implementation
- [ ] Controller creates record with `status: 'pending'` BEFORE dispatching job
- [ ] Controller dispatches job with record model (NOT ID or array)
- [ ] Job accepts record model instance
- [ ] Job has `public $timeout = 600;` property for execution time limit
- [ ] Job has `public $tries = 0;` for unlimited lock wait retries
- [ ] Job has `public $maxExceptions = 3;` to limit actual execution failures
- [ ] Job has `middleware()` method returning `WithoutOverlapping` middleware
- [ ] Job manages status lifecycle: `pending → installing → active/failed`
- [ ] Job implements `failed()` method for error handling
- [ ] Installer class accepts only existing models

### 3. Frontend Implementation
- [ ] Component uses `useEcho()` hook to listen for updates
- [ ] Uses `router.reload()` to fetch fresh data on broadcast
- [ ] Status badges for all lifecycle states (pending, installing, active, failed)
- [ ] Retry button for failed installations

### 4. Removal Lifecycle
- [ ] Status enum includes `'removing'` state
- [ ] Controller updates status to `'removing'` before dispatching removal job
- [ ] Removal job deletes record on success
- [ ] Removal job restores original status on failure

## Implementation Pattern

### 1. Status Enum

```php
// app/Enums/FirewallRuleStatus.php
enum FirewallRuleStatus: string
{
    case Pending = 'pending';      // Record created, job not started
    case Installing = 'installing'; // Job actively running
    case Active = 'active';        // Installation completed successfully
    case Failed = 'failed';        // Installation failed with errors
    case Removing = 'removing';    // Removal in progress
}
```

### 2. Database Migration

```php
Schema::create('server_firewall_rules', function (Blueprint $table) {
    $table->id();
    $table->foreignId('server_id')->constrained()->onDelete('cascade');
    $table->string('name');
    $table->integer('port');
    $table->string('protocol');
    $table->string('status')->default('pending');  // ← Critical!
    $table->text('error_log')->nullable();
    $table->timestamps();
});
```

### 3. Model with Automatic Broadcasting

```php
// app/Models/ServerFirewallRule.php
class ServerFirewallRule extends Model
{
    protected $fillable = [
        'server_id',
        'name',
        'port',
        'protocol',
        'status',
        'error_log',
    ];

    protected $casts = [
        'status' => FirewallRuleStatus::class,
    ];

    /**
     * Automatically broadcast when model changes
     */
    protected static function booted(): void
    {
        static::created(function (self $rule): void {
            \App\Events\ServerUpdated::dispatch($rule->server_id);
        });

        static::updated(function (self $rule): void {
            \App\Events\ServerUpdated::dispatch($rule->server_id);
        });

        static::deleted(function (self $rule): void {
            \App\Events\ServerUpdated::dispatch($rule->server_id);
        });
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
```

**Key Points:**
- Model events (`created`, `updated`, `deleted`) automatically broadcast
- **NO manual `dispatch()` calls** needed in controllers/jobs
- Every status change triggers WebSocket event to frontend

### 4. Controller Creates Record First

```php
// app/Http/Controllers/ServerFirewallController.php
public function store(StoreFirewallRuleRequest $request, Server $server): RedirectResponse
{
    Gate::authorize('createRule', [ServerFirewall::class, $server]);

    // ✅ CREATE RECORD FIRST with 'pending' status (default)
    $rule = $server->firewallRules()->create($request->validated());

    Log::info('Firewall rule created', [
        'user_id' => auth()->id(),
        'server_id' => $server->id,
        'rule_id' => $rule->id,
    ]);

    // ✅ THEN dispatch job with rule model (not ID or array)
    FirewallRuleInstallerJob::dispatch($server, $rule);

    return redirect()
        ->route('servers.firewall', $server)
        ->with('success', 'Firewall rule created and installation started');
}
```

**Critical:**
- Create record FIRST (immediate UI visibility)
- Record starts with `status: 'pending'`
- Dispatch job with model instance, NOT ID or array

### 5. Job Manages Status Lifecycle (Using Taskable)

**All standard lifecycle jobs extend `Taskable`** to eliminate boilerplate:

```php
// app/Packages/Services/Firewall/FirewallRuleInstallerJob.php
use App\Packages\Taskable;
use Illuminate\Database\Eloquent\Model;

class FirewallRuleInstallerJob extends Taskable
{
    public function __construct(
        public Server $server,
        public ServerFirewallRule $rule  // ← Receives model instance, NOT ID
    ) {}

    protected function getModelQuery()
    {
        return ServerFirewallRule::query();
    }

    protected function getResourceId(): int
    {
        return $this->rule->id;
    }

    protected function getInProgressStatus(): mixed
    {
        return FirewallRuleStatus::Installing;
    }

    protected function getSuccessStatus(): mixed
    {
        return FirewallRuleStatus::Active;
    }

    protected function getFailedStatus(): mixed
    {
        return FirewallRuleStatus::Failed;
    }

    protected function executeOperation(Model $model): void
    {
        $installer = new FirewallRuleInstaller($this->server);

        $singleRule = [
            'port' => $model->port,
            'protocol' => 'tcp',
            'action' => $model->rule_type ?? 'allow',
            'source' => $model->from_ip_address ?? null,
            'comment' => $model->name,
        ];

        $installer->execute([$singleRule]);
    }

    protected function getLogContext(Model $model): array
    {
        return [
            'rule_id' => $model->id,
            'server_id' => $this->server->id,
            'rule_name' => $model->name,
            'port' => $model->port,
        ];
    }

    protected function getOperationName(): string
    {
        return 'firewall rule configuration';
    }

    protected function findModelForFailure(): ?Model
    {
        return ServerFirewallRule::find($this->rule->id);
    }

    protected function getFailedLogContext(\Throwable $exception): array
    {
        return [
            'rule_id' => $this->rule->id,
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ];
    }
}
```

**What Taskable Provides:**
- ✅ Automatic properties: `$timeout = 600`, `$tries = 0`, `$maxExceptions = 3`
- ✅ Automatic `middleware()` with `WithoutOverlapping` configuration
- ✅ Automatic lifecycle: `pending → installing → active/failed`
- ✅ Automatic logging at start, success, and failure
- ✅ Automatic error handling and status updates
- ✅ Built-in `failed()` method implementation
- ✅ **Reduces code from ~140 lines to ~80 lines** (40% reduction)

**Optional Methods You Can Override:**
- `shouldDeleteOnSuccess()` - Return `true` for remover jobs
- `getAdditionalSuccessData()` - Add extra data on success (e.g., timestamps)
- `getStatusField()` - Change status field name (default: `'status'`)
- `getErrorField()` - Change error field name (default: `'error_log'`)
- `loadModel()` - Custom loading logic (e.g., use `find()` instead of `findOrFail()`)

**Critical:**
- Job accepts model instance, NOT ID or array
- Extend `Taskable` for standard lifecycle jobs
- Implement all required abstract methods
- Each status update broadcasts automatically via model events
- Base class handles all boilerplate (properties, middleware, logging, error handling)

### 6. Installer Accepts Only Existing Models

```php
// app/Packages/Services/Firewall/FirewallRuleInstaller.php
class FirewallRuleInstaller extends PackageInstaller
{
    protected ServerFirewallRule $rule;

    public function __construct(Server $server, ServerFirewallRule $rule)
    {
        parent::__construct($server);
        $this->rule = $rule;  // Only accepts existing rule model
    }

    public function execute(): void
    {
        // Install on remote server
        $this->install($this->commands());
    }

    protected function commands(): array
    {
        return [
            $this->track(FirewallRuleInstallerMilestones::PREPARE_RULE),
            "ufw allow {$this->rule->port}/{$this->rule->protocol}",
            $this->track(FirewallRuleInstallerMilestones::COMPLETE),
        ];
    }
}
```

### 7. Frontend with useEcho

```typescript
// resources/js/pages/servers/firewall.tsx
import { useEcho } from '@laravel/echo-react';
import { router } from '@inertiajs/react';
import { AlertCircle, CheckCircle, Loader2 } from 'lucide-react';

export default function Firewall({ server }: Props) {
    // ✅ Listen for real-time updates
    useEcho(`servers.${server.id}`, 'ServerUpdated', () => {
        router.reload({
            only: ['server'],
            preserveScroll: true,
            preserveState: true,
        });
    });

    return (
        <div>
            {server.firewallRules?.map(rule => (
                <div key={rule.id}>
                    <div>{rule.name} - Port {rule.port}</div>

                    {/* Status badges for all lifecycle states */}
                    {rule.status === 'pending' && (
                        <span className="inline-flex items-center gap-1 rounded bg-amber-500/10 px-1.5 py-0.5 text-xs text-amber-600">
                            <Loader2 className="h-3 w-3" />
                            Pending
                        </span>
                    )}

                    {rule.status === 'installing' && (
                        <span className="inline-flex items-center gap-1 rounded bg-blue-500/10 px-1.5 py-0.5 text-xs text-blue-600">
                            <Loader2 className="h-3 w-3 animate-spin" />
                            Installing
                        </span>
                    )}

                    {rule.status === 'active' && (
                        <span className="inline-flex items-center gap-1 rounded bg-green-500/10 px-1.5 py-0.5 text-xs text-green-600">
                            <CheckCircle className="h-3 w-3" />
                            Active
                        </span>
                    )}

                    {rule.status === 'failed' && (
                        <span className="inline-flex items-center gap-1 rounded bg-red-500/10 px-1.5 py-0.5 text-xs text-red-600">
                            <AlertCircle className="h-3 w-3" />
                            Failed
                        </span>
                    )}
                </div>
            ))}
        </div>
    );
}
```

## Removal Lifecycle Pattern

### Controller Updates Status Before Removal

```php
public function destroy(Server $server, ServerFirewallRule $rule): RedirectResponse
{
    Gate::authorize('deleteRule', [ServerFirewall::class, $server]);

    // ✅ UPDATE status to 'removing' (broadcasts automatically)
    $rule->update(['status' => 'removing']);

    // ✅ THEN dispatch removal job with rule ID
    FirewallRuleRemoverJob::dispatch($server, $rule->id);

    return redirect()
        ->route('servers.firewall', $server)
        ->with('success', 'Firewall rule removal started');
}
```

### Removal Job Deletes on Success (Using Taskable)

```php
use App\Packages\Taskable;
use Illuminate\Database\Eloquent\Model;

class FirewallRuleRemoverJob extends Taskable
{
    public function __construct(
        public Server $server,
        public int $ruleId
    ) {}

    protected function getModelQuery()
    {
        return ServerFirewallRule::query();
    }

    protected function getResourceId(): int
    {
        return $this->ruleId;
    }

    protected function getInProgressStatus(): mixed
    {
        return FirewallRuleStatus::Removing;
    }

    protected function getSuccessStatus(): mixed
    {
        return FirewallRuleStatus::Active; // Not used since we delete
    }

    protected function getFailedStatus(): mixed
    {
        return FirewallRuleStatus::Failed;
    }

    protected function shouldDeleteOnSuccess(): bool
    {
        return true;  // ✅ Automatically deletes on success
    }

    protected function executeOperation(Model $model): void
    {
        $uninstaller = new FirewallRuleUninstaller($this->server);

        $ruleConfig = [
            'port' => $model->port,
            'from_ip_address' => $model->from_ip_address,
            'rule_type' => $model->rule_type,
            'name' => $model->name,
        ];

        $uninstaller->execute($ruleConfig);
    }

    protected function getLogContext(Model $model): array
    {
        return [
            'rule_id' => $this->ruleId,
            'server_id' => $this->server->id,
        ];
    }

    protected function getOperationName(): string
    {
        return "firewall rule removal for server #{$this->server->id}";
    }

    protected function findModelForFailure(): ?Model
    {
        return ServerFirewallRule::find($this->ruleId);
    }

    protected function getFailedLogContext(\Throwable $exception): array
    {
        return [
            'rule_id' => $this->ruleId,
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ];
    }
}
```

**Key Point:** Setting `shouldDeleteOnSuccess()` to `true` automatically deletes the model on successful completion. Base class handles all logging and error recovery.

## Package Organization Structure

**All service-related classes MUST be in `app/Packages/Services/{ServiceName}/`:**

```
app/Packages/Services/{ServiceName}/
├── Commands/          # Artisan commands (auto-discovered)
├── Events/            # Domain events
├── Listeners/         # Event listeners
├── Notifications/     # Email/notification classes
├── Jobs/              # Background jobs (suffix: Job.php)
└── Services/          # Service/Installer classes (no suffix)
```

### Naming Conventions
- **Jobs**: `FirewallRuleInstallerJob.php`, `FirewallRuleRemoverJob.php`
- **Installers**: `FirewallRuleInstaller.php`, `FirewallRuleRemover.php`
- **Commands**: `EvaluateServerMonitorsCommand.php`
- **Events**: `MonitorTriggeredEvent.php`
- **Listeners**: `SendMonitorAlertNotification.php`
- **Notifications**: `MonitorTriggeredNotification.php`

## Complete Lifecycle Flow

```
1. User Creates Rule
       ↓
2. Controller creates DB record (status: pending)
       ↓
3. Model's created() event fires → ServerUpdated dispatched → Reverb broadcasts
       ↓
4. Frontend receives WebSocket notification → router.reload() → UI shows "Pending"
       ↓
5. Job starts executing
       ↓
6. Job updates status to 'installing' → Model's updated() event → Broadcast
       ↓
7. Frontend reloads → UI shows "Installing" with spinner
       ↓
8. Job completes successfully
       ↓
9. Job updates status to 'active' → Model's updated() event → Broadcast
       ↓
10. Frontend reloads → UI shows "Active" ✅

[OR on failure]

8. Job fails
       ↓
9. Job updates status to 'failed' → Model's updated() event → Broadcast
       ↓
10. Frontend reloads → UI shows "Failed" with retry button ⚠️
```

## Testing Requirements

**Every package MUST have tests covering:**

1. ✅ Controller creates record with `status: 'pending'`
2. ✅ Controller dispatches job with record ID
3. ✅ Job updates status to `'installing'`
4. ✅ Job updates status to `'active'` on success
5. ✅ Job updates status to `'failed'` on error
6. ✅ Model events dispatch `ServerUpdated` broadcast
7. ✅ Removal sets status to `'removing'`
8. ✅ Removal deletes record on success
9. ✅ Removal restores original status on failure

**Reference:** `tests/Feature/ServerScheduledTaskLifecycleTest.php`

## Reference Implementations

Study these for complete examples:
- `app/Packages/Services/Firewall/` - Firewall rule lifecycle
- `app/Packages/Services/Scheduler/Task/` - Scheduled task lifecycle
- `app/Models/ServerFirewallRule.php` - Model with broadcasting events
- `tests/Feature/ServerScheduledTaskLifecycleTest.php` - Complete test suite

## Critical Rules

1. **Create record FIRST** with `status: 'pending'` before dispatching job
2. **Job accepts model instance** (not ID or array)
3. **Prevent concurrent operations** - use `WithoutOverlapping` middleware with `$tries = 0` and `$maxExceptions = 3`
4. **Model events broadcast** automatically - never manually dispatch
5. **Frontend uses useEcho + router.reload()** - no polling
6. **Status badges** for all lifecycle states
7. **Removal pattern** - set status to `'removing'`, delete on success, restore on failure
8. **Job failure handling** - implement `failed()` method
9. **Write comprehensive tests** for all status transitions

## Validation Before Completion

**Package is NOT complete until:**

- ✅ Status enum with all lifecycle states
- ✅ Migration with `status` column defaulting to `'pending'`
- ✅ Model with automatic broadcasting events
- ✅ Controller creates record first, dispatches job with model instance
- ✅ Job has `$timeout = 600`, `$tries = 0`, `$maxExceptions = 3`
- ✅ Job has `middleware()` method with `WithoutOverlapping`
- ✅ Job manages lifecycle: pending → installing → active/failed
- ✅ Job implements `failed()` method
- ✅ Frontend has status badges and retry button
- ✅ Removal lifecycle implemented (if applicable)
- ✅ All tests pass
- ✅ Code formatted with Pint
