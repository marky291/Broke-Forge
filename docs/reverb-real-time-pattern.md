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

## Summary

The **Broadcast Notification → Fetch Full Resource** pattern provides:
- Clean separation between real-time notifications and data transformation
- Single source of truth in API Resources
- Easy maintenance and testing
- Consistent data structure
- Instant real-time updates

Use this pattern as the default for real-time features in BrokeForge.
