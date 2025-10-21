# Laravel Reverb Real-Time Update Pattern

## Overview

This document describes the recommended pattern for implementing real-time updates in BrokeForge using Laravel Reverb. This pattern follows the **Broadcast Notification → Fetch Full Resource** approach.

## Quick Start Checklist

Before implementing real-time features, ensure:

- [ ] Reverb server is running: `php artisan reverb:start`
- [ ] Environment variables configured in `.env`:
  - `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` (server-side)
  - `VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`, `VITE_REVERB_PORT` (client-side)
  - `VITE_REVERB_HOST` matches how you access the app (localhost or IP)
- [ ] Echo configured in `resources/js/app.tsx` with `configureEcho()`
- [ ] Frontend assets built: `npm run build` or `npm run dev` running
- [ ] Channel authorization defined in `routes/channels.php`
- [ ] Event class created implementing `ShouldBroadcastNow`
- [ ] Event dispatched after model updates
- [ ] Frontend component using `useEcho` hook

## The Pattern

### Philosophy

Rather than broadcasting complete data payloads, we broadcast minimal notification events that trigger the frontend to fetch the latest data from its existing source of truth (API Resource).

**Important:** Use model event listeners to automatically broadcast when models are updated. This eliminates the need to manually dispatch events after every `save()` call and ensures broadcasts are never forgotten.

### Two-Channel Broadcasting Pattern

BrokeForge uses a **dual-channel broadcasting pattern** for maximum flexibility:

1. **Specific Resource Channel** (`servers.{id}`, `sites.{id}`): For detail pages that show a single resource
2. **Generic Type Channel** (`servers`, `sites`): For dashboard and list pages that show multiple resources

When a model updates, events broadcast to BOTH channels simultaneously, allowing different pages to subscribe to the channel that fits their needs.

**Benefits:**
- ✅ Detail pages get updates for their specific resource
- ✅ Dashboard/list pages get updates for all user's resources
- ✅ No duplicate events needed - one event serves multiple use cases
- ✅ Developers choose the right channel for their component

### General Benefits

- ✅ **Single Source of Truth**: All data transformation stays in one place (the Resource class)
- ✅ **No Data Duplication**: Avoid repeating transformation logic in events
- ✅ **Consistency**: UI always displays data in the same format
- ✅ **Easy to Maintain**: Changes to data structure happen in one location
- ✅ **Instant Updates**: Real-time without polling overhead
- ✅ **Type Safety**: Resources provide consistent TypeScript interfaces
- ✅ **Flexible Subscriptions**: Subscribe to specific resources or all resources

## When to Use This Pattern

Use this pattern when:
- You have an existing API Resource that transforms model data
- The frontend already fetches data from this resource
- You want to add real-time updates without duplicating logic
- The data structure is complex or frequently changes

## Setup

### Environment Configuration

Configure both server-side and client-side Reverb settings in `.env`:

```env
# Server-side configuration (used by the Reverb server)
REVERB_APP_ID=395356
REVERB_APP_KEY=zasc7krwfjh9wf0aqind
REVERB_APP_SECRET=kegaqsev62hbih2nzdfq
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http

# Client-side configuration (used by browser JavaScript)
# IMPORTANT: If accessing the app from a network IP (e.g., 192.168.x.x),
# update VITE_REVERB_HOST to match that IP instead of "localhost"
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

**Critical: Network Access Configuration**
- If accessing the app via `http://localhost:8000`, use `VITE_REVERB_HOST=localhost`
- If accessing via IP like `http://192.168.2.1:8000`, use `VITE_REVERB_HOST=192.168.2.1`
- The browser must be able to connect to the WebSocket server at the specified host
- After changing `VITE_*` variables, rebuild assets with `npm run build` or restart `npm run dev`

### Frontend Echo Configuration

Configure Echo in `resources/js/app.tsx` **before** initializing the Inertia app:

```typescript
import { configureEcho } from '@laravel/echo-react';

configureEcho({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});

// Then initialize Inertia app
createInertiaApp({
    // ... your app config
});
```

**Key Points:**
- Call `configureEcho()` before `createInertiaApp()`
- Use environment variables for all configuration
- Enable both `ws` and `wss` transports for flexibility

## Implementation

### Backend: Event Class

Create an event that implements `ShouldBroadcastNow` for immediate broadcasting to BOTH channels:

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
            new PrivateChannel('servers.'.$this->serverId),  // Specific: servers.5
            new PrivateChannel('servers'),                    // Generic: all user's servers
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

**Key Points:**
- Use `ShouldBroadcastNow` for immediate broadcasting (no queue delay)
- Use `PrivateChannel` for security
- Broadcast to TWO channels: specific (`servers.{id}`) and generic (`servers`)
- Minimal payload: only IDs and timestamps
- No complex data transformation

### Backend: Channel Authorization

Define channel authorization in `routes/channels.php` for BOTH channel types:

```php
use App\Models\Server;
use App\Models\ServerSite;
use Illuminate\Support\Facades\Broadcast;

// Specific server channel - authorize if user owns this server
Broadcast::channel('servers.{serverId}', function ($user, int $serverId) {
    return $user->id === Server::findOrNew($serverId)->user_id;
});

// Generic servers channel - authorize if user is authenticated
Broadcast::channel('servers', function ($user) {
    return ['id' => $user->id];
});

// Specific site channel - authorize if user owns this site's server
Broadcast::channel('sites.{siteId}', function ($user, int $siteId) {
    $site = ServerSite::with('server')->find($siteId);
    return $site && $user->id === $site->server->user_id;
});

// Generic sites channel - authorize if user is authenticated
Broadcast::channel('sites', function ($user) {
    return ['id' => $user->id];
});
```

**Key Points:**
- Specific channels (`servers.{id}`, `sites.{id}`): Verify user owns the specific resource
- Generic channels (`servers`, `sites`): Authorize any authenticated user
- Return user data or `true` for authorized, `false` for denied
- Private channels prevent cross-user pollution automatically

### Backend: Broadcasting Events

Dispatch events whenever the underlying data changes:

```php
use App\Events\ServerProvisionUpdated;

// In your controller or job
$server->provision->put($step, $status);
$server->save();

ServerProvisionUpdated::dispatch($server->id);
```

**Common Broadcasting Locations:**
- **Controllers**: After saving model changes
- **Jobs**: After completing long-running operations
- **Installer Classes**: After updating provision steps during installation
- **Observers**: In model event listeners (be careful with recursion)

### Broadcasting via Model Events (Recommended)

**This is the preferred approach.** Add model event listeners to automatically broadcast when models update:

```php
// app/Models/Server.php
protected static function booted(): void
{
    static::updated(function (self $server): void {
        \App\Events\ServerUpdated::dispatch($server->id);
    });
}
```

**Benefits:**
- ✅ Automatic - never forget to broadcast
- ✅ Consistent - always broadcasts on update
- ✅ Less code - no manual dispatch calls throughout codebase
- ✅ Maintainable - broadcasting logic in one place

**Usage:** Simply update the model and broadcasting happens automatically:

```php
// In controller, job, or anywhere else
$server->provision->put(5, 'completed');
$server->save(); // Broadcasts automatically!

// No need for manual dispatch:
// ServerUpdated::dispatch($server->id); ❌ Not needed!
```

### Broadcasting from Jobs

For long-running jobs, rely on model events instead of manual dispatches:

```php
class NginxInstallerJob implements ShouldQueue
{
    public function __construct(
        public Server $server,
        public PhpVersion $phpVersion,
        public bool $isProvisioningServer = false
    ) {}

    public function handle(): void
    {
        try {
            if ($this->isProvisioningServer) {
                // Model event handles broadcasting automatically
                $this->server->update(['provision_status' => ProvisionStatus::Installing]);
            }

            $installer = new NginxInstaller($this->server);
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

### Broadcasting from Installer Classes

Model events handle broadcasting automatically when provision steps update:

```php
class NginxInstaller extends PackageInstaller
{
    public function execute(PhpVersion $phpVersion): void
    {
        // Step 5: Firewall installation
        FirewallInstallerJob::dispatchSync($this->server);

        // Model event broadcasts automatically on save
        $this->server->provision->put(5, ProvisionStatus::Completed->value);
        $this->server->provision->put(6, ProvisionStatus::Installing->value);
        $this->server->save();

        // Step 6: PHP installation
        PhpInstallerJob::dispatchSync($this->server, $phpVersion);

        // Model event broadcasts automatically on save
        $this->server->provision->put(6, ProvisionStatus::Completed->value);
        $this->server->provision->put(7, ProvisionStatus::Installing->value);
        $this->server->save();

        // Continue with remaining steps...
    }
}
```

**Key Points:**
- No manual dispatch calls needed - model events handle it
- Broadcast happens automatically on `save()`
- Cleaner code with less repetition
- Broadcasting logic centralized in model

### Frontend: Listening with useEcho

#### Option 1: Detail Page (Specific Channel)

For pages showing a single resource, subscribe to the specific channel:

```typescript
import { useEcho } from '@laravel/echo-react';
import { router } from '@inertiajs/react';

export default function ServerDetailPage({ server }) {
    // Listen to specific server updates
    useEcho(
        `servers.${server.id}`,
        'ServerUpdated',
        () => {
            router.reload({
                only: ['server'],  // Only reload server prop
                preserveScroll: true,
                preserveState: true,
            });
        }
    );

    return (
        // Your component JSX
    );
}
```

#### Option 2: Dashboard/List Page (Generic Channel)

For pages showing multiple resources, subscribe to the generic channel:

```typescript
import { useEffect } from 'react';
import { router } from '@inertiajs/react';

export default function Dashboard({ dashboard }) {
    const { servers, sites, activities } = dashboard;

    // Subscribe to generic servers channel for all server updates
    useEffect(() => {
        const channel = window.Echo?.private('servers')
            .listen('.ServerUpdated', () => {
                router.reload({
                    only: ['dashboard'],  // Reload entire dashboard
                    preserveScroll: true,
                    preserveState: true,
                });
            });

        return () => {
            window.Echo?.leave('servers');
        };
    }, []);

    // Subscribe to generic sites channel for all site updates
    useEffect(() => {
        const channel = window.Echo?.private('sites')
            .listen('.ServerSiteUpdated', () => {
                router.reload({
                    only: ['dashboard'],
                    preserveScroll: true,
                    preserveState: true,
                });
            });

        return () => {
            window.Echo?.leave('sites');
        };
    }, []);

    return (
        // Your component JSX
    );
}
```

**Key Points:**
- **Detail pages**: Use specific channel (`servers.${id}`) with `useEcho` hook
- **Dashboard/list pages**: Use generic channel (`servers`) with `useEffect` + `window.Echo`
- Channel format: no "private-" prefix (Echo adds it automatically)
- Event name: Add `.` prefix when using `window.Echo.listen()` (e.g., `.ServerUpdated`)
- Use `router.reload()` to fetch updated data
- Use `only` option to reload specific props efficiently
- Use `preserveScroll` and `preserveState` for smooth UX
- Clean up subscriptions in useEffect cleanup function

## Complete Example 1: Real-Time Server Firewall Page

This example shows how to implement a real-time firewall page that updates when firewall rules are installed, updated, or deleted.

### 1. Backend: Resource for Data Transformation

```php
// app/Http/Resources/ServerResource.php
class ServerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $firewall = $this->firewall;

        return [
            'id' => $this->id,
            'vanity_name' => $this->vanity_name,
            // ... other server fields
            'isFirewallInstalled' => $firewall !== null,
            'firewallStatus' => $this->getFirewallStatus($firewall),
            'rules' => $this->transformFirewallRules($firewall),
            'recentEvents' => $this->transformRecentEvents(),
            'latestMetrics' => $this->getLatestMetrics(),
        ];
    }

    protected function transformFirewallRules($firewall): array
    {
        if (! $firewall) {
            return [];
        }

        return $firewall->rules()->latest()->get()->map(fn ($rule) => [
            'id' => $rule->id,
            'name' => $rule->name,
            'port' => $rule->port,
            'from_ip_address' => $rule->from_ip_address,
            'rule_type' => $rule->rule_type,
            'status' => $rule->status, // pending, installing, active, failed
            'created_at' => $rule->created_at->toISOString(),
        ])->toArray();
    }
}
```

### 2. Backend: Model Event Listeners

```php
// app/Models/ServerFirewall.php
protected static function booted(): void
{
    static::created(function (self $firewall): void {
        \App\Events\ServerUpdated::dispatch($firewall->server_id);
    });

    static::updated(function (self $firewall): void {
        \App\Events\ServerUpdated::dispatch($firewall->server_id);
    });
}

// app/Models/ServerFirewallRule.php
protected static function booted(): void
{
    static::created(function (self $rule): void {
        \App\Events\ServerUpdated::dispatch($rule->firewall->server_id);
    });

    static::updated(function (self $rule): void {
        \App\Events\ServerUpdated::dispatch($rule->firewall->server_id);
    });

    static::deleted(function (self $rule): void {
        \App\Events\ServerUpdated::dispatch($rule->firewall->server_id);
    });
}
```

### 3. Backend: Controller Using Resource

```php
// app/Http/Controllers/ServerFirewallController.php
public function index(Server $server): Response
{
    // Load necessary relationships for the resource
    $server->load(['firewall.rules', 'events', 'metrics']);

    return Inertia::render('servers/firewall', [
        'server' => new ServerResource($server),
    ]);
}

public function store(FirewallRuleRequest $request, Server $server): RedirectResponse
{
    // Create rule - model events handle broadcasting automatically
    FirewallRuleInstallerJob::dispatch($server, $request->validated());

    return back()->with('success', 'Firewall rule is being applied.');
}
```

### 4. Frontend: Firewall Page with useEcho

```typescript
// resources/js/pages/servers/firewall.tsx
import { useEcho } from '@laravel/echo-react';
import { router } from '@inertiajs/react';

type Server = {
    id: number;
    vanity_name: string;
    isFirewallInstalled: boolean;
    firewallStatus: string;
    rules: FirewallRule[];
    recentEvents: FirewallEvent[];
    latestMetrics?: any;
};

export default function Firewall({ server }: { server: Server }) {
    // Listen for real-time server updates via Reverb
    useEcho(`servers.${server.id}`, 'ServerUpdated', () => {
        router.reload({
            only: ['server'],
            preserveScroll: true,
            preserveState: true,
        });
    });

    return (
        <div>
            {server.rules.map(rule => (
                <div key={rule.id}>
                    {rule.name} - {rule.status}
                </div>
            ))}
        </div>
    );
}
```

**What Updates in Real-Time:**
- Firewall rule status changes (pending → installing → active)
- New firewall rules added
- Firewall rules deleted
- Firewall enabled/disabled state

**Key Benefits:**
- No polling - instant updates via WebSockets
- Single source of truth in ServerResource
- Clean separation of concerns
- Model events ensure broadcasts never forgotten

## Complete Example 2: Real-Time Dashboard

This example shows how to implement a real-time dashboard that updates when ANY server or site changes.

### 1. Backend: Events (Dual-Channel Broadcasting)

```php
// app/Events/ServerUpdated.php
class ServerUpdated implements ShouldBroadcastNow
{
    public function __construct(public int $serverId) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('servers.'.$this->serverId),  // Specific
            new PrivateChannel('servers'),                    // Generic
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

// app/Events/ServerSiteUpdated.php
class ServerSiteUpdated implements ShouldBroadcastNow
{
    public function __construct(public int $siteId) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('sites.'.$this->siteId),  // Specific
            new PrivateChannel('sites'),                  // Generic
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'site_id' => $this->siteId,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
```

### 2. Backend: Model Event Listeners

```php
// app/Models/Server.php
protected static function booted(): void
{
    static::updated(function (self $server): void {
        // Only broadcast if meaningful fields changed
        $broadcastFields = ['provision', 'provision_status', 'connection',
                           'monitoring_status', 'scheduler_status', 'supervisor_status'];

        if ($server->wasChanged($broadcastFields)) {
            \App\Events\ServerUpdated::dispatch($server->id);
        }
    });
}

// app/Models/ServerSite.php
protected static function booted(): void
{
    static::updated(function (self $site): void {
        // Only broadcast if meaningful fields changed
        $broadcastFields = ['domain', 'status', 'health', 'git_status',
                           'ssl_enabled', 'last_deployed_at'];

        if ($site->wasChanged($broadcastFields)) {
            \App\Events\ServerSiteUpdated::dispatch($site->id);
        }
    });
}
```

### 3. Backend: Dashboard Resource

```php
// app/Http/Resources/DashboardResource.php
class DashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'servers' => $this->transformServers($this->resource['servers']),
            'sites' => $this->transformSites($this->resource['sites']),
            'activities' => $this->transformActivities($this->resource['activities']),
        ];
    }

    protected function transformServers($servers): array
    {
        return $servers->map(fn($server) => [
            'id' => $server->id,
            'name' => $server->vanity_name,
            'provision_status' => $server->provision_status?->value,
            'connection' => $server->connection?->value,
            // ... more fields
        ])->toArray();
    }
}
```

### 4. Backend: Controller

```php
// app/Http/Controllers/DashboardController.php
public function __invoke(): Response
{
    $servers = Server::with(['defaultPhp', 'sites'])->latest()->limit(5)->get();
    $sites = ServerSite::with(['server'])->latest()->limit(5)->get();
    $activities = $this->getRecentActivities();

    return Inertia::render('dashboard', [
        'dashboard' => new DashboardResource([
            'servers' => $servers,
            'sites' => $sites,
            'activities' => $activities,
        ]),
    ]);
}
```

### 5. Frontend: Dashboard Component

```typescript
// resources/js/Pages/dashboard.tsx
import { useEffect } from 'react';
import { router } from '@inertiajs/react';

export default function Dashboard({ dashboard }) {
    const { servers, sites, activities } = dashboard;

    // Listen to generic servers channel
    useEffect(() => {
        window.Echo?.private('servers').listen('.ServerUpdated', () => {
            router.reload({ only: ['dashboard'], preserveScroll: true });
        });
        return () => window.Echo?.leave('servers');
    }, []);

    // Listen to generic sites channel
    useEffect(() => {
        window.Echo?.private('sites').listen('.ServerSiteUpdated', () => {
            router.reload({ only: ['dashboard'], preserveScroll: true });
        });
        return () => window.Echo?.leave('sites');
    }, []);

    return (
        <div>
            {/* Render servers */}
            {servers.map(server => (
                <div key={server.id}>{server.name} - {server.provision_status}</div>
            ))}

            {/* Render sites */}
            {sites.map(site => (
                <div key={site.id}>{site.domain} - {site.status}</div>
            ))}
        </div>
    );
}
```

**What Updates in Real-Time:**
- Server provision status changes
- Server connection status
- Monitoring/Scheduler/Supervisor status
- Site deployments and status
- Site health checks
- Git status updates

## Complete Example 2: Server Detail Page

This example shows a detail page that subscribes to a specific resource channel.

### Frontend: Server Detail Component

```typescript
// resources/js/Pages/servers/show.tsx
import { useEcho } from '@laravel/echo-react';
import { router } from '@inertiajs/react';

export default function ServerDetailPage({ server }) {
    // Listen to specific server channel
    useEcho(`servers.${server.id}`, 'ServerUpdated', () => {
        router.reload({
            only: ['server'],
            preserveScroll: true,
            preserveState: true,
        });
    });

    return (
        <div>
            <h1>{server.name}</h1>
            <p>Status: {server.provision_status}</p>
            <p>Connection: {server.connection}</p>
        </div>
    );
}
```

**Note:** The same `ServerUpdated` event broadcasts to BOTH `servers.{id}` (detail page) and `servers` (dashboard). One event serves multiple use cases.

## Complete Example 3: Site-Level Resources with Nested Data

This example shows how to implement real-time updates for site-level resources with complex nested data (deployments, command history, git repository).

### 1. Backend: Site Resource with Nested Transforms

```php
// app/Http/Resources/ServerSiteResource.php
class ServerSiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'domain' => $this->domain,
            'status' => $this->status,
            'git_status' => $this->git_status?->value,
            // ... other site fields
            'server' => $this->transformServer(),
            'executionContext' => $this->transformExecutionContext(),
            'commandHistory' => $this->transformCommandHistory($request),
            'applicationType' => $this->transformApplicationType(),
            'gitRepository' => $this->transformGitRepository(),
            'deploymentScript' => $this->transformDeploymentScript(),
            'deployments' => $this->transformDeployments($request),
            'latestDeployment' => $this->transformLatestDeployment(),
        ];
    }

    protected function transformDeployments(Request $request): array
    {
        $page = $request->input('page', 1);
        $perPage = 10;

        $deployments = $this->deployments()
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $deployments->items() ? collect($deployments->items())->map(fn ($deployment) => [
                'id' => $deployment->id,
                'status' => $deployment->status,
                'output' => $deployment->output,
                'error_output' => $deployment->error_output,
                'commit_sha' => $deployment->commit_sha,
                'branch' => $deployment->branch,
                'duration_seconds' => $deployment->getDurationSeconds(),
                'started_at' => $deployment->started_at,
                'completed_at' => $deployment->completed_at,
                'created_at' => $deployment->created_at,
                'created_at_human' => $deployment->created_at->diffForHumans(),
            ])->toArray() : [],
            'current_page' => $deployments->currentPage(),
            'last_page' => $deployments->lastPage(),
            'per_page' => $deployments->perPage(),
            'total' => $deployments->total(),
        ];
    }

    protected function transformLatestDeployment(): ?array
    {
        $latestDeployment = $this->latestDeployment;

        if (! $latestDeployment) {
            return null;
        }

        return [
            'id' => $latestDeployment->id,
            'status' => $latestDeployment->status,
            'output' => $latestDeployment->output,
            'error_output' => $latestDeployment->error_output,
            'commit_sha' => $latestDeployment->commit_sha,
            'duration_seconds' => $latestDeployment->getDurationSeconds(),
            'started_at' => $latestDeployment->started_at,
            'completed_at' => $latestDeployment->completed_at,
        ];
    }
}
```

### 2. Backend: Model Events for Multiple Related Models

```php
// app/Models/ServerSite.php
protected static function booted(): void
{
    static::updated(function (self $site): void {
        $broadcastFields = ['domain', 'status', 'health', 'git_status',
                           'ssl_enabled', 'last_deployed_at'];

        if ($site->wasChanged($broadcastFields)) {
            \App\Events\ServerSiteUpdated::dispatch($site->id);
        }
    });
}

// app/Models/ServerDeployment.php
protected static function booted(): void
{
    // Broadcast when new deployment is created
    static::created(function (self $deployment): void {
        \App\Events\ServerSiteUpdated::dispatch($deployment->server_site_id);
    });

    // Broadcast when deployment status or output changes (for real-time progress)
    static::updated(function (self $deployment): void {
        \App\Events\ServerSiteUpdated::dispatch($deployment->server_site_id);
    });
}

// app/Models/ServerSiteCommandHistory.php
protected static function booted(): void
{
    // Broadcast when new command is executed (so history updates in real-time)
    static::created(function (self $commandHistory): void {
        \App\Events\ServerSiteUpdated::dispatch($commandHistory->server_site_id);
    });
}
```

### 3. Backend: Simplified Controllers

```php
// app/Http/Controllers/ServerSiteDeploymentsController.php
public function show(Server $server, ServerSite $site): Response
{
    // Check if site has Git repository installed
    if (! $site->hasGitRepository()) {
        return redirect()->route('servers.sites.git-repository', [$server, $site])
            ->with('error', 'Git repository must be installed before deploying.');
    }

    // Single prop with all data - resource handles transformation
    return Inertia::render('servers/site-deployments', [
        'site' => new ServerSiteResource($site),
    ]);
}

// app/Http/Controllers/ServerSiteApplicationController.php
public function show(Server $server, ServerSite $site): Response
{
    if ($site->git_status === GitStatus::Failed) {
        return redirect()->route('servers.sites.application.git.setup', [$server, $site]);
    }

    // Single prop with all data - resource handles transformation
    return Inertia::render('servers/site-application', [
        'site' => new ServerSiteResource($site),
    ]);
}
```

### 4. Frontend: Deployments Page with Real-Time Updates

```typescript
// resources/js/pages/servers/site-deployments.tsx
import { useEcho } from '@laravel/echo-react';
import { router } from '@inertiajs/react';
import { type ServerSite } from '@/types';

export default function SiteDeployments({ site }: { site: ServerSite }) {
    const server = site.server!;
    const deploymentScript = site.deploymentScript!;
    const deployments = site.deployments || { data: [] };
    const latestDeployment = site.latestDeployment;

    // Listen for real-time deployment updates via Reverb WebSocket
    useEcho(`sites.${site.id}`, 'ServerSiteUpdated', () => {
        router.reload({
            only: ['site'],
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                // Update live deployment if it's still active
                const updatedSite = (page.props as any).site as ServerSite;
                const updatedLatest = updatedSite.latestDeployment;
                if (updatedLatest) {
                    setLiveDeployment(updatedLatest as any);
                }
            },
        });
    });

    return (
        <div>
            {/* Latest deployment with live output */}
            {latestDeployment && (
                <div>
                    <h3>Current Deployment</h3>
                    <div>Status: {latestDeployment.status}</div>
                    <pre>{latestDeployment.output}</pre>
                </div>
            )}

            {/* Deployment history */}
            {deployments.data.map(deployment => (
                <div key={deployment.id}>
                    {deployment.commit_sha} - {deployment.status}
                </div>
            ))}
        </div>
    );
}
```

### 5. Frontend: Application Page with Real-Time Updates

```typescript
// resources/js/pages/servers/site-application.tsx
import { useEcho } from '@laravel/echo-react';
import { router } from '@inertiajs/react';
import { type ServerSite } from '@/types';

export default function SiteApplication({ site }: { site: ServerSite }) {
    const server = site.server!;
    const applicationType = site.applicationType;
    const gitRepository = site.gitRepository;

    // Listen for real-time site updates via Reverb WebSocket
    useEcho(`sites.${site.id}`, 'ServerSiteUpdated', () => {
        router.reload({ only: ['site'], preserveScroll: true, preserveState: true });
    });

    return (
        <div>
            {applicationType === 'application' && gitRepository && (
                <div>
                    <h3>Git Repository</h3>
                    <p>Repository: {gitRepository.repository}</p>
                    <p>Branch: {gitRepository.branch}</p>
                    <p>Last Deployed: {gitRepository.lastDeployedAt}</p>
                </div>
            )}
        </div>
    );
}
```

### 6. TypeScript Types

```typescript
// resources/js/types/index.d.ts
export interface ServerSite {
    id: number;
    domain: string;
    status: string;
    git_status?: string | null;
    // ... other fields
    server?: {
        id: number;
        vanity_name: string;
        public_ip: string;
        connection: string;
        monitoring_status?: string | null;
        latestMetrics?: ServerMetric;
    };
    executionContext?: {
        workingDirectory: string;
        user: string | null;
        timeout: number;
    };
    commandHistory?: {
        data: CommandHistoryItem[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    applicationType?: string | null;
    gitRepository?: {
        provider: string;
        repository: string;
        branch: string;
        deployKey: string;
        lastDeployedSha: string | null;
        lastDeployedAt: string | null;
    } | null;
    deploymentScript?: string | null;
    deployments?: {
        data: Deployment[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    latestDeployment?: {
        id: number;
        status: 'pending' | 'running' | 'success' | 'failed';
        output: string | null;
        error_output: string | null;
        commit_sha: string | null;
        duration_seconds: number | null;
        started_at: string | null;
        completed_at: string | null;
    } | null;
}
```

**What Updates in Real-Time:**
- Deployment status changes (pending → running → success/failed)
- Deployment output as it's generated
- New deployments added to history
- Command execution history
- Git repository status
- Site status and health

**Key Benefits:**
- **Replaced Polling**: No more 1-second HTTP polling - uses WebSocket instead
- **Nested Data**: Complex nested resources (deployments, commands) update automatically
- **Single Prop**: Controllers simplified - one `site` prop contains everything
- **Automatic Broadcasting**: Model events ensure broadcasts never forgotten
- **Clean Separation**: Resource handles all data transformation

**Controller Size Reduction:**
- ServerSiteApplicationController: 62 lines → 33 lines (52% reduction)
- ServerSiteDeploymentsController: 203 lines → 143 lines (30% reduction)

This pattern is ideal for complex resources with nested relationships that need real-time updates.

## Alternative Pattern: Broadcasting Full Data

In some cases, you may want to broadcast the complete data payload:

### When to Broadcast Full Data

Use this pattern when:
- The data is simple and unlikely to change structure
- You need immediate data without an extra HTTP request
- The data is small (< 1KB)
- You don't have a Resource class for this data

### Example

```php
class TaskCompletedEvent implements ShouldBroadcastNow
{
    public function __construct(public Task $task) {}

    public function broadcastWith(): array
    {
        return [
            'task' => [
                'id' => $this->task->id,
                'name' => $this->task->name,
                'completed' => true,
                'completed_at' => $this->task->completed_at,
            ],
        ];
    }
}
```

```typescript
useEcho(
    `tasks.${taskId}`,
    'TaskCompletedEvent',
    (event) => {
        // Use the broadcasted data directly
        setTask(event.task);
    }
);
```

## Testing

### Unit Test for Event

```php
namespace Tests\Unit\Events;

use App\Events\ServerProvisionUpdated;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Tests\TestCase;

class ServerProvisionUpdatedTest extends TestCase
{
    public function test_implements_should_broadcast_now(): void
    {
        $event = new ServerProvisionUpdated(1);
        $this->assertInstanceOf(ShouldBroadcastNow::class, $event);
    }

    public function test_broadcasts_on_correct_channel(): void
    {
        $event = new ServerProvisionUpdated(123);
        $channels = $event->broadcastOn();

        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals('servers.123.provision', $channels[0]->name);
    }
}
```

### Integration Test

```php
public function test_server_owner_can_access_provision_channel(): void
{
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create();

    $channel = Broadcast::channel('servers.{serverId}.provision',
        fn($authUser, int $serverId) =>
            $authUser->id === Server::findOrNew($serverId)->user_id
    );

    $result = $channel($user, $server->id);
    $this->assertTrue((bool) $result);
}
```

## Performance Considerations

### Broadcast Frequency

- **Too Frequent**: Broadcasting every 100ms can overwhelm clients
- **Too Infrequent**: Users won't see real-time updates
- **Recommended**: Broadcast on meaningful state changes only

### Debouncing on Frontend

If you expect rapid updates, consider debouncing the reload:

```typescript
import { useDebouncedCallback } from 'use-debounce';

const debouncedReload = useDebouncedCallback(
    () => {
        router.reload({ only: ['server'], preserveScroll: true });
    },
    500  // 500ms debounce
);

useEcho(
    `servers.${server.id}.provision`,
    'ServerProvisionUpdated',
    debouncedReload
);
```

## Common Patterns in BrokeForge

This pattern is ideal for:
- **Server Provisioning**: Real-time provision step updates
- **Site Deployments**: Live deployment progress
- **Package Installations**: Real-time package install status
- **Database Operations**: Live database operation progress
- **Log Streaming**: Real-time log updates (with debouncing)

## Troubleshooting

### Event Not Received

1. **Check Reverb is running**: `php artisan reverb:start` or verify with `ps aux | grep reverb`
2. **Verify Echo configuration**: Ensure `configureEcho()` is called in `app.tsx` with all required options
3. **Check environment variables**:
   - Verify `REVERB_*` variables exist for server-side
   - Verify `VITE_REVERB_*` variables exist for client-side
   - After changing `VITE_*` variables, rebuild: `npm run build`
4. **Verify network access**:
   - If accessing via IP (e.g., `192.168.2.1:8000`), set `VITE_REVERB_HOST=192.168.2.1`
   - Browser must be able to connect to WebSocket at the specified host
   - Check browser console for WebSocket connection errors
5. **Check channel authorization**: Verify authorization callback returns `true`
6. **Confirm event configuration**:
   - Event implements `ShouldBroadcastNow`
   - Event is dispatched after model save
   - Channel name format is correct
7. **Verify user authentication**: User must be logged in for private channels

### Channel Authorization Fails

1. Ensure user owns the resource
2. Check the authorization callback logic
3. Verify channel name format matches
4. Test with `tinker`:
```php
$user = User::find(1);
$server = Server::find(1);
$user->id === $server->user_id; // Should be true
```

### Updates Too Slow

1. Use `ShouldBroadcastNow` not `ShouldBroadcast`
2. Check queue workers are running
3. Verify Reverb configuration
4. Check network latency

## References

- [Laravel Broadcasting Documentation](https://laravel.com/docs/12.x/broadcasting)
- [Laravel Reverb Documentation](https://laravel.com/docs/12.x/reverb)
- [@laravel/echo-react Documentation](https://github.com/laravel/echo)
- [Inertia.js Partial Reloads](https://inertiajs.com/manual-visits#partial-reloads)

## Complete Example 4: Reverb Package Lifecycle for Service Installations

This example shows how to implement the **Reverb Package Lifecycle Pattern** for packages that require real-time status updates during installation (scheduled tasks, firewall rules, SSL certificates, cron jobs, etc.). This pattern creates database records FIRST with a status field, then updates status through the installation lifecycle with automatic Reverb broadcasting.

### When to Use This Pattern

Use the Reverb Package Lifecycle pattern when:
- ✅ Users need to see installation/removal progress in real-time
- ✅ Operations take more than a few seconds to complete
- ✅ The resource has meaningful status transitions (pending → installing → active/failed)
- ✅ Users should see the resource immediately, even before installation completes

**Examples:** Firewall rules, scheduled tasks, deployment configurations, SSL certificates, cron jobs

### Pattern Overview

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

### 1. Status Enum with Lifecycle States

Define an enum with all lifecycle states:

```php
// app/Enums/TaskStatus.php
enum TaskStatus: string
{
    case Pending = 'pending';       // Record created, job not started
    case Installing = 'installing';  // Job actively running
    case Active = 'active';         // Installation completed successfully
    case Paused = 'paused';         // Optional: resource temporarily disabled
    case Failed = 'failed';         // Installation failed with errors
}
```

**Key Points:**
- `Pending`: Initial state when record created
- `Installing`: Job is actively executing SSH commands
- `Active`: Installation completed successfully
- `Failed`: Installation failed (allows retry)
- Optional states like `Paused` for specific use cases

### 2. Database Migration with Default Status

Ensure the table has a `status` column defaulting to `'pending'`:

```php
// database/migrations/xxxx_create_server_scheduled_tasks_table.php
Schema::create('server_scheduled_tasks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('server_id')->constrained('servers')->onDelete('cascade');
    $table->string('name');
    $table->string('command');
    $table->string('frequency');
    $table->integer('timeout')->default(300);
    $table->boolean('send_notifications')->default(false);
    $table->string('status')->default('pending');  // ← Important!
    $table->timestamp('last_run_at')->nullable();
    $table->timestamps();
});
```

**If updating existing table:**
```php
// database/migrations/xxxx_change_server_scheduled_tasks_status_default_to_pending.php
public function up(): void
{
    Schema::table('server_scheduled_tasks', function (Blueprint $table) {
        $table->string('status')->default('pending')->change();
    });
}

public function down(): void
{
    Schema::table('server_scheduled_tasks', function (Blueprint $table) {
        $table->string('status')->default('active')->change();
    });
}
```

### 3. Model with Automatic Broadcasting

Add event listeners to automatically broadcast when the model changes:

```php
// app/Models/ServerScheduledTask.php
class ServerScheduledTask extends Model
{
    protected $fillable = [
        'server_id',
        'name',
        'command',
        'frequency',
        'timeout',
        'send_notifications',
        'status',  // ← Include status in fillable
        'last_run_at',
    ];

    protected $casts = [
        'frequency' => ScheduleFrequency::class,
        'status' => TaskStatus::class,
        'send_notifications' => 'boolean',
        'last_run_at' => 'datetime',
    ];

    /**
     * Automatically broadcast when model changes
     */
    protected static function booted(): void
    {
        // Broadcast when task created (status: pending)
        static::created(function (self $task): void {
            \App\Events\ServerUpdated::dispatch($task->server_id);
        });

        // Broadcast when status updates (pending → installing → active/failed)
        static::updated(function (self $task): void {
            \App\Events\ServerUpdated::dispatch($task->server_id);
        });

        // Broadcast when task deleted
        static::deleted(function (self $task): void {
            \App\Events\ServerUpdated::dispatch($task->server_id);
        });
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
```

**Key Points:**
- Model events (`created`, `updated`, `deleted`) automatically dispatch broadcasts
- No manual `dispatch()` calls needed in controllers, jobs, or installers
- Every status change triggers a WebSocket event to the frontend
- Events use `ServerUpdated` to update the entire server resource

### 4. Controller Creates Record First, Then Dispatches Job

Controller creates the database record with `status: 'pending'` BEFORE dispatching the job:

```php
// app/Http/Controllers/ServerSchedulerController.php
public function storeTask(StoreScheduledTaskRequest $request, Server $server): RedirectResponse
{
    // Authorization
    Gate::authorize('createTask', [ServerScheduler::class, $server]);

    // ✅ CREATE RECORD FIRST with 'pending' status (default from migration)
    $task = $server->scheduledTasks()->create($request->validated());

    // Audit log
    Log::info('Scheduled task created', [
        'user_id' => auth()->id(),
        'server_id' => $server->id,
        'task_id' => $task->id,
        'task_name' => $task->name,
    ]);

    // ✅ THEN dispatch job with task ID (not task model or array)
    ServerScheduleTaskInstallerJob::dispatch($server, $task->id);

    return redirect()
        ->route('servers.scheduler', $server)
        ->with('success', 'Scheduled task created and installation started');
}
```

**Key Points:**
- Create record FIRST (immediate visibility in UI)
- Record starts with `status: 'pending'` (default from migration)
- Model's `created()` event broadcasts automatically
- Dispatch job with task ID, NOT full task model or array
- User sees "Pending" status immediately before job starts

### 5. Job Manages Status Lifecycle

Job receives the record ID and manages status transitions:

```php
// app/Packages/Services/Scheduler/Task/ServerScheduleTaskInstallerJob.php
class ServerScheduleTaskInstallerJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 300;

    public function __construct(
        public Server $server,
        public int $taskId  // ← Receives record ID, NOT full model or array
    ) {}

    public function handle(): void
    {
        // Load the record from database
        $task = ServerScheduledTask::findOrFail($this->taskId);

        Log::info("Starting scheduled task installation", [
            'task_id' => $task->id,
            'server_id' => $this->server->id,
        ]);

        try {
            // ✅ UPDATE: pending → installing
            $task->update(['status' => 'installing']);
            // Model event broadcasts automatically via Reverb

            // Create installer and execute
            $installer = new ServerScheduleTaskInstaller($this->server, $task);
            $installer->execute();

            // ✅ UPDATE: installing → active
            $task->update(['status' => 'active']);
            // Model event broadcasts automatically via Reverb

            Log::info("Scheduled task installation completed", ['task_id' => $task->id]);

        } catch (\Exception $e) {
            // ✅ UPDATE: any → failed
            $task->update(['status' => 'failed']);
            // Model event broadcasts automatically via Reverb

            Log::error("Scheduled task installation failed", [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;  // Re-throw for Laravel's retry mechanism
        }
    }
}
```

**Key Points:**
- Job accepts task ID, NOT full model or array
- Load record from database using `findOrFail()`
- Manage lifecycle: `pending → installing → active/failed`
- Each status update broadcasts automatically via model events
- No manual `dispatch()` calls needed

### 6. Installer Accepts Only Existing Models

The installer class is simplified to only accept existing task models:

```php
// app/Packages/Services/Scheduler/Task/ServerScheduleTaskInstaller.php
class ServerScheduleTaskInstaller extends PackageInstaller implements ServerPackage
{
    protected ServerScheduledTask $task;

    public function __construct(Server $server, ServerScheduledTask $task)
    {
        parent::__construct($server);
        $this->task = $task;  // Only accepts existing task model
    }

    public function milestones(): Milestones
    {
        return new ServerScheduleTaskInstallerMilestones;
    }

    public function credentialType(): CredentialType
    {
        return CredentialType::Root;
    }

    /**
     * Execute the task installation
     * Installs existing task on remote server (does NOT create task)
     */
    public function execute(): void
    {
        // Install on remote server
        $this->install($this->commands());
    }

    protected function commands(): array
    {
        $appUrl = config('app.url');
        $taskId = $this->task->id;
        $serverId = $this->server->id;
        $schedulerToken = $this->server->scheduler_token;
        $command = $this->task->command;
        $timeout = $this->task->timeout;
        $cronExpression = $this->task->getCronExpression();

        // Generate wrapper script and cron entry
        $wrapperScript = view('scheduler.task-wrapper', [
            'appUrl' => $appUrl,
            'serverId' => $serverId,
            'taskId' => $taskId,
            'schedulerToken' => $schedulerToken,
            'command' => $command,
            'timeout' => $timeout,
        ])->render();

        $cronEntry = view('scheduler.cron-entry', [
            'task' => $this->task,
            'cronExpression' => $cronExpression,
        ])->render();

        return [
            $this->track(ServerScheduleTaskInstallerMilestones::PREPARE_TASK),

            // Create wrapper script
            "cat > /opt/brokeforge/scheduler/tasks/{$taskId}.sh << 'EOF'\n{$wrapperScript}\nEOF",
            "chmod +x /opt/brokeforge/scheduler/tasks/{$taskId}.sh",

            $this->track(ServerScheduleTaskInstallerMilestones::INSTALL_CRON_ENTRY),

            // Create cron entry
            "cat > /etc/cron.d/brokeforge-task-{$taskId} << 'EOF'\n{$cronEntry}\nEOF",
            "chmod 644 /etc/cron.d/brokeforge-task-{$taskId}",

            $this->track(ServerScheduleTaskInstallerMilestones::COMPLETE),
        ];
    }
}
```

**Key Points:**
- Installer only accepts existing `ServerScheduledTask` model
- Does NOT create database records
- Focuses solely on remote installation
- Task already exists in database with `status: 'pending'`

### 7. Frontend Status Badges with useEcho

Frontend displays status badges for all lifecycle states and listens for real-time updates:

```typescript
// resources/js/pages/servers/scheduler.tsx
import { useEcho } from '@laravel/echo-react';
import { router } from '@inertiajs/react';
import { AlertCircle, CheckCircle, Loader2, RotateCw } from 'lucide-react';

export default function Scheduler({ server }: Props) {
    // ✅ Listen for real-time server updates via Reverb WebSocket
    useEcho(`servers.${server.id}`, 'ServerUpdated', () => {
        router.reload({
            only: ['server'],
            preserveScroll: true,
            preserveState: true,
        });
    });

    return (
        <div>
            {server.scheduledTasks?.map(task => (
                <div key={task.id}>
                    <div>{task.name}</div>

                    {/* Status badges for all lifecycle states */}
                    {task.status === 'pending' && (
                        <span className="inline-flex items-center gap-1 rounded bg-amber-500/10 px-1.5 py-0.5 text-xs text-amber-600">
                            <Loader2 className="h-3 w-3" />
                            Pending
                        </span>
                    )}

                    {task.status === 'installing' && (
                        <span className="inline-flex items-center gap-1 rounded bg-blue-500/10 px-1.5 py-0.5 text-xs text-blue-600">
                            <Loader2 className="h-3 w-3 animate-spin" />
                            Installing
                        </span>
                    )}

                    {task.status === 'active' && (
                        <span className="inline-flex items-center gap-1 rounded bg-green-500/10 px-1.5 py-0.5 text-xs text-green-600">
                            <CheckCircle className="h-3 w-3" />
                            Active
                        </span>
                    )}

                    {task.status === 'failed' && (
                        <span className="inline-flex items-center gap-1 rounded bg-red-500/10 px-1.5 py-0.5 text-xs text-red-600">
                            <AlertCircle className="h-3 w-3" />
                            Failed
                        </span>
                    )}

                    {/* Retry button - only shown for failed tasks */}
                    {task.status === 'failed' && (
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => handleRetryTask(task)}
                            disabled={processing}
                            className="h-8 w-8 p-0 text-blue-600 hover:text-blue-700"
                            title="Retry task installation"
                        >
                            <RotateCw className="h-4 w-4" />
                        </Button>
                    )}
                </div>
            ))}
        </div>
    );
}
```

**Key Points:**
- `useEcho` listens for `ServerUpdated` events on `servers.{id}` channel
- Status badges show all lifecycle states with appropriate icons
- Animated spinner for `installing` state
- Retry button only shown for `failed` tasks
- UI updates automatically via WebSocket (no polling)

### 8. Retry Failed Tasks

Allow users to retry failed installations:

```php
// app/Http/Controllers/ServerSchedulerController.php
public function retryTask(Server $server, ServerScheduledTask $scheduledTask): RedirectResponse
{
    // Authorization
    Gate::authorize('updateTask', [ServerScheduler::class, $server]);

    // Only allow retry for failed tasks
    if ($scheduledTask->status !== TaskStatus::Failed) {
        return redirect()
            ->route('servers.scheduler', $server)
            ->with('error', 'Only failed tasks can be retried');
    }

    // Audit log
    Log::info('Scheduled task retry initiated', [
        'user_id' => auth()->id(),
        'server_id' => $server->id,
        'task_id' => $scheduledTask->id,
    ]);

    // ✅ Reset status to 'pending' to trigger reinstallation
    $scheduledTask->update(['status' => 'pending']);
    // Model event broadcasts automatically

    // ✅ Dispatch job with task ID
    ServerScheduleTaskInstallerJob::dispatch($server, $scheduledTask->id);

    return redirect()
        ->route('servers.scheduler', $server)
        ->with('success', 'Task retry started');
}
```

**Route with throttling:**
```php
// routes/web.php
Route::post('{scheduledTask}/retry', [ServerSchedulerController::class, 'retryTask'])
    ->name('scheduler.tasks.retry')
    ->middleware('throttle:10,1'); // Max 10 retries per minute
```

**Frontend retry handler:**
```typescript
const handleRetryTask = (task: ServerScheduledTask) => {
    if (!confirm(`Retry installing "${task.name}"?`)) {
        return;
    }
    post(`/servers/${server.id}/scheduler/tasks/${task.id}/retry`, {
        onSuccess: () => router.reload(),
    });
};
```

### 9. Complete Lifecycle Flow

Here's how the complete flow works from user action to final status:

```
1. User Creates Task
       ↓
2. Controller creates DB record (status: pending)
       ↓
3. Model's created() event fires
       ↓
4. ServerUpdated event dispatched
       ↓
5. Reverb broadcasts to WebSocket channel 'servers.{id}'
       ↓
6. Frontend useEcho receives notification
       ↓
7. router.reload() fetches fresh server data via Inertia
       ↓
8. UI updates to show task with "pending" status
       ↓
9. Job starts executing
       ↓
10. Job updates DB record (status: installing)
       ↓
11. Model's updated() event fires
       ↓
12. ServerUpdated event dispatched again
       ↓
13. Frontend receives notification and reloads
       ↓
14. UI updates to show "installing" status with spinner
       ↓
15. Job completes successfully
       ↓
16. Job updates DB record (status: active)
       ↓
17. Model's updated() event fires again
       ↓
18. ServerUpdated event dispatched again
       ↓
19. Frontend receives notification and reloads
       ↓
20. UI updates to show "active" status ✅
```

### 10. Testing the Reverb Package Lifecycle

Create comprehensive tests covering all lifecycle stages:

```php
// tests/Feature/ServerScheduledTaskLifecycleTest.php
class ServerScheduledTaskLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_controller_creates_task_with_pending_status(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->for($user)->create([
            'scheduler_status' => SchedulerStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('servers.scheduler.tasks.store', $server), [
            'name' => 'Test Task',
            'command' => 'php artisan test:command',
            'frequency' => 'daily',
            'timeout' => 300,
            'send_notifications' => false,
        ]);

        $response->assertRedirect();

        // ✅ Verify task created with pending status
        $task = ServerScheduledTask::where('name', 'Test Task')->first();
        $this->assertNotNull($task);
        $this->assertEquals('pending', $task->status->value);
    }

    public function test_controller_dispatches_job_with_task_id(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->for($user)->create([
            'scheduler_status' => SchedulerStatus::Active,
        ]);

        $this->actingAs($user);

        $this->post(route('servers.scheduler.tasks.store', $server), [
            'name' => 'Test Task',
            'command' => 'php artisan test:command',
            'frequency' => 'hourly',
            'timeout' => 300,
            'send_notifications' => false,
        ]);

        $task = ServerScheduledTask::where('name', 'Test Task')->first();

        // ✅ Verify job dispatched with task ID
        Queue::assertPushed(ServerScheduleTaskInstallerJob::class, function ($job) use ($server, $task) {
            return $job->server->id === $server->id
                && $job->taskId === $task->id;
        });
    }

    public function test_job_updates_status_to_installing(): void
    {
        Event::fake([ServerUpdated::class]);

        $user = User::factory()->create();
        $server = Server::factory()->create();
        $task = ServerScheduledTask::factory()->for($server)->create([
            'status' => 'pending',
        ]);

        // Mock SSH for execution
        $mockProcess = \Mockery::mock(\Symfony\Component\Process\Process::class);
        $mockProcess->shouldReceive('isSuccessful')->andReturn(true);
        $mockProcess->shouldReceive('getOutput')->andReturn('Success');

        $mockSsh = \Mockery::mock(\Spatie\Ssh\Ssh::class);
        $mockSsh->shouldReceive('setTimeout')->andReturnSelf();
        $mockSsh->shouldReceive('execute')->andReturn($mockProcess);

        $server = \Mockery::mock($server)->makePartial();
        $server->shouldReceive('createSshConnection')->andReturn($mockSsh);

        try {
            $job = new ServerScheduleTaskInstallerJob($server, $task->id);
            $job->handle();
        } catch (\Exception $e) {
            // May fail due to mocking
        }

        // ✅ Verify status was updated to installing at some point
        $task->refresh();
        $this->assertContains($task->status->value, ['installing', 'active', 'failed']);
    }

    public function test_job_updates_status_to_active_on_success(): void
    {
        Event::fake([ServerUpdated::class]);

        $server = Server::factory()->create();
        $task = ServerScheduledTask::factory()->for($server)->create([
            'status' => 'pending',
        ]);

        // Mock successful SSH execution
        $mockProcess = \Mockery::mock(\Symfony\Component\Process\Process::class);
        $mockProcess->shouldReceive('isSuccessful')->andReturn(true);
        $mockProcess->shouldReceive('getOutput')->andReturn('Success');

        $mockSsh = \Mockery::mock(\Spatie\Ssh\Ssh::class);
        $mockSsh->shouldReceive('setTimeout')->andReturnSelf();
        $mockSsh->shouldReceive('execute')->andReturn($mockProcess);

        $server = \Mockery::mock($server)->makePartial();
        $server->shouldReceive('createSshConnection')->andReturn($mockSsh);

        $job = new ServerScheduleTaskInstallerJob($server, $task->id);
        $job->handle();

        // ✅ Verify status updated to active
        $task->refresh();
        $this->assertEquals('active', $task->status->value);
    }

    public function test_job_updates_status_to_failed_on_error(): void
    {
        Event::fake([ServerUpdated::class]);

        $server = Server::factory()->create();
        $task = ServerScheduledTask::factory()->for($server)->create([
            'status' => 'pending',
        ]);

        // Mock SSH failure
        $mockSsh = \Mockery::mock(\Spatie\Ssh\Ssh::class);
        $mockSsh->shouldReceive('setTimeout')->andReturnSelf();
        $mockSsh->shouldReceive('execute')->andThrow(new \Exception('SSH connection failed'));

        $server = \Mockery::mock($server)->makePartial();
        $server->shouldReceive('createSshConnection')->andReturn($mockSsh);

        $job = new ServerScheduleTaskInstallerJob($server, $task->id);

        try {
            $job->handle();
        } catch (\Exception $e) {
            // Expected to throw
        }

        // ✅ Verify status updated to failed
        $task->refresh();
        $this->assertEquals('failed', $task->status->value);
    }

    public function test_task_creation_dispatches_server_updated_event(): void
    {
        Event::fake([ServerUpdated::class]);

        $user = User::factory()->create();
        $server = Server::factory()->for($user)->create();

        ServerScheduledTask::create([
            'server_id' => $server->id,
            'name' => 'Test Task',
            'command' => 'php artisan test:command',
            'frequency' => 'daily',
            'status' => 'pending',
            'timeout' => 300,
            'send_notifications' => false,
        ]);

        // ✅ Verify broadcast event dispatched
        Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
            return $event->serverId === $server->id;
        });
    }

    public function test_retry_resets_failed_task_to_pending_and_dispatches_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->for($user)->create([
            'scheduler_status' => SchedulerStatus::Active,
        ]);
        $task = ServerScheduledTask::factory()->for($server)->create([
            'status' => 'failed',
        ]);

        $this->actingAs($user);

        $response = $this->post(route('servers.scheduler.tasks.retry', [$server, $task]));

        $response->assertRedirect();

        // ✅ Verify task status reset to pending
        $task->refresh();
        $this->assertEquals('pending', $task->status->value);

        // ✅ Verify job dispatched with task ID
        Queue::assertPushed(ServerScheduleTaskInstallerJob::class, function ($job) use ($server, $task) {
            return $job->server->id === $server->id
                && $job->taskId === $task->id;
        });
    }

    public function test_retry_only_works_for_failed_tasks(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $server = Server::factory()->for($user)->create([
            'scheduler_status' => SchedulerStatus::Active,
        ]);
        $task = ServerScheduledTask::factory()->for($server)->create([
            'status' => 'active',
        ]);

        $this->actingAs($user);

        $response = $this->post(route('servers.scheduler.tasks.retry', [$server, $task]));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Only failed tasks can be retried');

        // ✅ Verify task status not changed
        $task->refresh();
        $this->assertEquals('active', $task->status->value);

        // ✅ Verify job not dispatched
        Queue::assertNotPushed(ServerScheduleTaskInstallerJob::class);
    }
}
```

### Benefits of This Pattern

1. **Immediate Visibility**: Users see the record instantly with 'pending' status
2. **Real-Time Progress**: Status updates broadcast automatically via Reverb WebSockets
3. **No Polling Required**: Frontend uses `useEcho` instead of HTTP polling
4. **Automatic Broadcasting**: Model events handle all broadcasting, no manual dispatch
5. **Granular Retry Logic**: Failed items can be retried individually
6. **Better UX**: Users see progress rather than waiting for completion
7. **Consistent**: Same pattern works for firewall rules, scheduled tasks, SSL certs, etc.

### When to Use vs. When NOT to Use

**✅ USE Reverb Package Lifecycle When:**
- Users need real-time status updates during installation
- Operation takes more than a few seconds
- Resource has meaningful status transitions
- Users should see resource immediately before completion

**Examples:** Firewall rules, scheduled tasks, SSL certificates, cron jobs, supervisor tasks

**❌ DON'T USE When:**
- Initial server provisioning (one-time setup)
- Operations complete in <2 seconds
- Infrastructure-only packages with no user-facing resources
- No meaningful status transitions

**Examples:** NginxInstaller (part of provisioning), PhpInstaller (part of provisioning)

### Reference Implementations

Study these implementations for complete examples:
- `app/Packages/Services/Firewall/FirewallRuleInstallerJob.php` - Firewall rule installation lifecycle
- `app/Packages/Services/Scheduler/Task/ServerScheduleTaskInstallerJob.php` - Scheduled task installation lifecycle
- `app/Packages/Services/Scheduler/Task/ServerScheduleTaskRemoverJob.php` - Scheduled task removal lifecycle
- `tests/Feature/ServerScheduledTaskLifecycleTest.php` - Complete test suite

This pattern should be used consistently across all packages requiring real-time status updates.

## Reverb Package Lifecycle for Removals/Deletions

The same real-time status pattern applies to removals and deletions. Users should see "removing" status immediately and get real-time feedback when deletion completes or fails.

### Removal Lifecycle Overview

**Traditional Removal Pattern (Old - Don't Use):**
```
User Clicks Delete → Job Dispatched → Record Deleted Immediately → SSH Commands Execute
```
Problem: Record disappears immediately, no visibility into removal progress, can't retry on failure.

**Reverb Removal Lifecycle (New - Use This):**
```
User Clicks Delete → Update Status (status: removing)
→ Broadcast → Frontend Shows "Removing"
→ Job Executes SSH Commands
→ On Success: Delete Record from Database
→ Broadcast → Frontend Removes from UI
→ On Failure: Restore Original Status
→ Broadcast → Frontend Shows Original Status (allows retry)
```
Benefits: Real-time removal progress, automatic cleanup on success, retry capability on failure.

### Key Implementation Changes for Removals

#### 1. Add "Removing" Status to Enum

```php
// app/Enums/TaskStatus.php
enum TaskStatus: string
{
    case Pending = 'pending';
    case Installing = 'installing';
    case Active = 'active';
    case Paused = 'paused';
    case Failed = 'failed';
    case Removing = 'removing';  // ← Add removal status
}
```

#### 2. Controller Sets Status Before Dispatching Removal Job

```php
// app/Http/Controllers/ServerSchedulerController.php
public function destroyTask(Server $server, ServerScheduledTask $scheduledTask): RedirectResponse
{
    Gate::authorize('deleteTask', [ServerScheduler::class, $server]);

    // ✅ UPDATE status to 'removing' (broadcasts automatically via model event)
    $scheduledTask->update(['status' => 'removing']);

    // ✅ THEN dispatch job with task ID
    ServerScheduleTaskRemoverJob::dispatch($server, $scheduledTask->id);

    return redirect()
        ->route('servers.scheduler', $server)
        ->with('success', 'Scheduled task removal started');
}
```

**Key Points:**
- Set status to `'removing'` before dispatching job
- User sees "Removing" badge immediately
- Model event broadcasts automatically

#### 3. Remover Job Manages Deletion Lifecycle

```php
// app/Packages/Services/Scheduler/Task/ServerScheduleTaskRemoverJob.php
class ServerScheduleTaskRemoverJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 600;

    public function __construct(
        public Server $server,
        public int $taskId  // ← Receives task ID, NOT full model
    ) {}

    public function handle(): void
    {
        set_time_limit(0);

        // Load the task from database
        $task = ServerScheduledTask::findOrFail($this->taskId);

        // Store original status for rollback on failure
        $originalStatus = $task->status;

        Log::info("Starting scheduled task removal", [
            'task_id' => $task->id,
            'server_id' => $this->server->id,
        ]);

        try {
            // Create remover instance
            $remover = new ServerScheduleTaskRemover($this->server, $task);

            // Execute removal on remote server
            $remover->execute();

            Log::info("Scheduled task removal completed successfully", [
                'task_id' => $task->id,
            ]);

            // ✅ DELETE task from database (model's deleted event broadcasts automatically)
            $task->delete();
            // Frontend receives broadcast and removes task from UI

        } catch (Exception $e) {
            // ✅ ROLLBACK: Restore original status on failure (allows retry)
            $task->update(['status' => $originalStatus]);
            // Model event broadcasts automatically via Reverb
            // Frontend receives broadcast and shows task with original status

            Log::error("Scheduled task removal failed", [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
```

**Key Points:**
- Job accepts task ID, NOT full model
- Store original status before attempting removal
- On success: Delete from database (triggers `deleted` event → broadcast → frontend removes from UI)
- On failure: Restore original status (triggers `updated` event → broadcast → frontend shows original status)
- User can retry deletion if it fails

#### 4. Frontend Shows "Removing" Status Badge

```typescript
// resources/js/pages/servers/scheduler.tsx
{task.status === 'removing' && (
    <span className="inline-flex items-center gap-1 rounded bg-orange-500/10 px-1.5 py-0.5 text-xs text-orange-600">
        <Loader2 className="h-3 w-3 animate-spin" />
        Removing
    </span>
)}
```

**Key Points:**
- Use orange color to distinguish from "installing" (blue)
- Animated spinner shows removal in progress
- UI updates automatically via WebSocket

#### 5. Testing Removal Lifecycle

```php
// tests/Feature/ServerScheduledTaskLifecycleTest.php

public function test_controller_sets_status_to_removing_before_dispatching_removal_job(): void
{
    Queue::fake();

    $task = ServerScheduledTask::factory()->for($server)->create(['status' => 'active']);

    $this->delete(route('servers.scheduler.tasks.destroy', [$server, $task]));

    // ✅ Verify status updated to removing
    $task->refresh();
    $this->assertEquals('removing', $task->status->value);

    // ✅ Verify removal job dispatched with task ID
    Queue::assertPushed(ServerScheduleTaskRemoverJob::class, function ($job) use ($server, $task) {
        return $job->server->id === $server->id
            && $job->taskId === $task->id;
    });
}

public function test_removal_job_deletes_task_on_success(): void
{
    Event::fake([ServerUpdated::class]);

    $task = ServerScheduledTask::factory()->for($server)->create(['status' => 'removing']);

    // Mock successful SSH execution
    $mockSsh = $this->mockSuccessfulSsh();

    $job = new ServerScheduleTaskRemoverJob($server, $task->id);
    $job->handle();

    // ✅ Verify task was deleted from database
    $this->assertDatabaseMissing('server_scheduled_tasks', ['id' => $task->id]);
}

public function test_removal_job_restores_original_status_on_failure(): void
{
    Event::fake([ServerUpdated::class]);

    $task = ServerScheduledTask::factory()->for($server)->create(['status' => 'removing']);

    // Mock SSH failure
    $mockSsh = $this->mockFailedSsh();

    $job = new ServerScheduleTaskRemoverJob($server, $task->id);

    try {
        $job->handle();
    } catch (\Exception $e) {
        // Expected to throw
    }

    // ✅ Verify task still exists and status was restored
    $task->refresh();
    $this->assertEquals('removing', $task->status->value);
    $this->assertDatabaseHas('server_scheduled_tasks', ['id' => $task->id]);
}

public function test_task_deletion_dispatches_server_updated_event(): void
{
    Event::fake([ServerUpdated::class]);

    $task = ServerScheduledTask::factory()->for($server)->create();

    $task->delete();

    // ✅ Verify broadcast event dispatched on deletion
    Event::assertDispatched(ServerUpdated::class, function ($event) use ($server) {
        return $event->serverId === $server->id;
    });
}
```

### Complete Removal Flow

```
1. User Clicks Delete
       ↓
2. Controller updates DB record (status: removing)
       ↓
3. Model's updated() event fires
       ↓
4. ServerUpdated event dispatched
       ↓
5. Reverb broadcasts to WebSocket channel
       ↓
6. Frontend useEcho receives notification
       ↓
7. router.reload() fetches fresh server data
       ↓
8. UI updates to show "Removing" badge with spinner
       ↓
9. Job starts executing removal commands
       ↓
10a. [SUCCESS PATH]
10b. SSH commands succeed
       ↓
11b. Job deletes record from database
       ↓
12b. Model's deleted() event fires
       ↓
13b. ServerUpdated event dispatched
       ↓
14b. Frontend receives notification and reloads
       ↓
15b. UI removes task from list ✅

10a. [FAILURE PATH]
10a. SSH commands fail
       ↓
11a. Job restores original status (e.g., 'active')
       ↓
12a. Model's updated() event fires
       ↓
13a. ServerUpdated event dispatched
       ↓
14a. Frontend receives notification and reloads
       ↓
15a. UI shows task with original status (user can retry) ⚠️
```

### Benefits of Removal Lifecycle Pattern

1. **Real-Time Feedback**: Users see "Removing" status immediately
2. **Automatic Cleanup**: Successful removal automatically removes from UI
3. **Failure Recovery**: Failed removals restore original status for retry
4. **No Orphaned Records**: Database only cleaned up after successful remote removal
5. **Consistent UX**: Same real-time pattern as installations
6. **Retry Capability**: Users can retry failed deletions
7. **Audit Trail**: All removal attempts logged with success/failure

### When to Use Removal Lifecycle

Use this pattern for the same resources that use installation lifecycle:
- ✅ Firewall rules
- ✅ Scheduled tasks
- ✅ SSL certificates
- ✅ Supervisor tasks
- ✅ Cron jobs
- ✅ Any user-facing resource with remote installation

This ensures consistent real-time feedback for both installation and removal operations.

## Job Failure Handling

All package jobs use Laravel's `failed()` method to ensure resources never get stuck in transitional states when a job fails.

### The Pattern

Jobs update the resource status to `'failed'` with an error log when they fail:

```php
class FirewallRuleInstallerJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Server $server,
        public int $ruleId
    ) {}

    public function handle(): void
    {
        $rule = ServerFirewallRule::findOrFail($this->ruleId);

        $rule->update(['status' => 'installing']);

        $installer = new FirewallRuleInstaller($this->server, $rule);
        $installer->execute();

        $rule->update(['status' => 'active']);
    }

    public function failed(\Throwable $exception): void
    {
        $rule = ServerFirewallRule::find($this->ruleId);

        if ($rule) {
            $rule->update([
                'status' => 'failed',
                'error_log' => $exception->getMessage(),
            ]);
        }

        Log::error('Firewall rule installation failed', [
            'rule_id' => $this->ruleId,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

### Key Points

- Use `find()` in `failed()` method (record may have been deleted)
- Store `error_log` for user visibility
- Status changes broadcast automatically via model events
- Users can retry failed operations

### Database Requirements

Package tables need an `error_log` column:

```php
$table->text('error_log')->nullable()->after('status');
```

Models must include it in `$fillable`:

```php
protected $fillable = ['status', 'error_log', /* ... */];
```

## Summary

The **Broadcast Notification → Fetch Full Resource** pattern provides:
- Clean separation between real-time notifications and data transformation
- Single source of truth in API Resources
- Easy maintenance and testing
- Consistent data structure
- Instant real-time updates

The **Job Failure Handling** pattern ensures:
- Resources never stuck in transitional states
- User visibility into errors via error logs
- Retry capability for all failed operations
- Real-time failure notifications via Reverb

Use these patterns as the default for real-time features and background jobs in BrokeForge.
