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

### Benefits

- ✅ **Single Source of Truth**: All data transformation stays in one place (the Resource class)
- ✅ **No Data Duplication**: Avoid repeating transformation logic in events
- ✅ **Consistency**: UI always displays data in the same format
- ✅ **Easy to Maintain**: Changes to data structure happen in one location
- ✅ **Instant Updates**: Real-time without polling overhead
- ✅ **Type Safety**: Resources provide consistent TypeScript interfaces

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

Create an event that implements `ShouldBroadcastNow` for immediate broadcasting:

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServerProvisionUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $serverId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('servers.'.$this->serverId.'.provision'),
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
- Minimal payload: only IDs and timestamps
- No complex data transformation

### Backend: Channel Authorization

Define channel authorization in `routes/channels.php`:

```php
use App\Models\Server;

Broadcast::channel('servers.{serverId}.provision', function ($user, int $serverId) {
    return $user->id === Server::findOrNew($serverId)->user_id;
});
```

**Key Points:**
- Verify user owns the resource
- Use model query to check ownership
- Return `true` for authorized, `false` for denied

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

### Broadcasting from Jobs

For long-running jobs, broadcast at meaningful milestones:

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
                $this->server->update(['provision_status' => ProvisionStatus::Installing]);
                ServerProvisionUpdated::dispatch($this->server->id);
            }

            $installer = new NginxInstaller($this->server);
            $installer->execute($this->phpVersion);

            if ($this->isProvisioningServer) {
                $this->server->update(['provision_status' => ProvisionStatus::Completed]);
                ServerProvisionUpdated::dispatch($this->server->id);
            }
        } catch (Exception $e) {
            if ($this->isProvisioningServer) {
                $this->server->update(['provision_status' => ProvisionStatus::Failed]);
                ServerProvisionUpdated::dispatch($this->server->id);
            }
            throw $e;
        }
    }
}
```

### Broadcasting from Installer Classes

When installer classes update provision steps, broadcast after each update:

```php
class NginxInstaller extends PackageInstaller
{
    public function execute(PhpVersion $phpVersion): void
    {
        // Step 5: Firewall installation
        FirewallInstallerJob::dispatchSync($this->server);

        $this->server->provision->put(5, ProvisionStatus::Completed->value);
        $this->server->provision->put(6, ProvisionStatus::Installing->value);
        $this->server->save();
        ServerProvisionUpdated::dispatch($this->server->id);

        // Step 6: PHP installation
        PhpInstallerJob::dispatchSync($this->server, $phpVersion);

        $this->server->provision->put(6, ProvisionStatus::Completed->value);
        $this->server->provision->put(7, ProvisionStatus::Installing->value);
        $this->server->save();
        ServerProvisionUpdated::dispatch($this->server->id);

        // Continue with remaining steps...
    }
}
```

**Key Points:**
- Broadcast after saving the model changes
- Broadcast at each meaningful step transition
- Don't broadcast too frequently (avoid sub-second updates)
- Each broadcast triggers a frontend refresh

### Frontend: Listening with useEcho

Use the `useEcho` hook from `@laravel/echo-react`:

```typescript
import { useEcho } from '@laravel/echo-react';
import { router } from '@inertiajs/react';

export default function ProvisioningPage({ server }) {
    // Listen for real-time updates
    useEcho(
        `servers.${server.id}.provision`,
        'ServerProvisionUpdated',
        () => {
            router.reload({
                only: ['server'],  // Only reload server prop
                preserveScroll: true,
                preserveState: true,
            });
        }
    );

    // Auto-redirect when complete
    useEffect(() => {
        if (server.provision_status === 'completed') {
            router.visit(`/servers/${server.id}`);
        }
    }, [server.provision_status, server.id]);

    return (
        // Your component JSX
    );
}
```

**Key Points:**
- Channel format: `servers.${id}.provision` (no "private-" prefix)
- Event name: `ServerProvisionUpdated` (class name without namespace)
- Use `router.reload()` to fetch updated data
- Use `only` option to reload specific props
- Use `preserveScroll` and `preserveState` for smooth UX

## Complete Example: Server Provisioning

### 1. Backend: Event

```php
// app/Events/ServerProvisionUpdated.php
class ServerProvisionUpdated implements ShouldBroadcastNow
{
    public function __construct(public int $serverId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('servers.'.$this->serverId.'.provision')];
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

### 2. Backend: Controller

```php
// app/Http/Controllers/ProvisionCallbackController.php
use App\Events\ServerProvisionUpdated;

public function step(Request $request, Server $server): JsonResponse
{
    $step = (int) $request->input('step');
    $status = $request->input('status');

    // Update the provision step
    $server->provision->put($step, $status);
    $server->save();

    // Broadcast the change
    ServerProvisionUpdated::dispatch($server->id);

    return response()->json(['ok' => true]);
}
```

### 3. Backend: Channel Authorization

```php
// routes/channels.php
use App\Models\Server;

Broadcast::channel('servers.{serverId}.provision', function ($user, int $serverId) {
    return $user->id === Server::findOrNew($serverId)->user_id;
});
```

### 4. Backend: API Resource (Single Source of Truth)

```php
// app/Http/Resources/ServerProvisioningResource.php
class ServerProvisioningResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provision_status' => $this->provision_status->value,
            'steps' => [
                [
                    'step' => 1,
                    'name' => 'Waiting for connection',
                    'status' => $this->getStepStatus($this->provision, 1),
                ],
                // ... more steps
            ],
        ];
    }
}
```

### 5. Frontend: React Component

```typescript
// resources/js/pages/servers/provisioning.tsx
import { useEcho } from '@laravel/echo-react';
import { router } from '@inertiajs/react';

export default function ProvisioningPage({ server }) {
    // Listen for provision updates
    useEcho(
        `servers.${server.id}.provision`,
        'ServerProvisionUpdated',
        () => {
            router.reload({
                only: ['server'],
                preserveScroll: true,
                preserveState: true,
            });
        }
    );

    // Render provision steps
    return (
        <div>
            {server.steps.map((step, index) => (
                <div key={index}>
                    <h3>{step.name}</h3>
                    <p>{step.status.isCompleted ? '✓ Complete' : 'In Progress'}</p>
                </div>
            ))}
        </div>
    );
}
```

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
