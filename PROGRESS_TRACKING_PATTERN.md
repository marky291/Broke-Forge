# Progress Tracking Pattern

A reusable pattern for implementing real-time progress tracking for async operations (install/uninstall/update) with visual progress bars and status polling.

## Overview

This pattern provides:
- Real-time progress updates via polling
- Visual progress bars with step counts and percentages
- Milestone labels showing current operation
- Automatic page refresh on completion
- Generic implementation for any async operation

## Architecture

### Backend Components

1. **Status Enum** - Defines operation states
2. **Milestones Class** - Tracks operation steps
3. **Package/Job** - Executes commands with milestone tracking
4. **Events Table** - Stores progress in database
5. **Status Endpoint** - Returns current progress via API

### Frontend Components

1. **Polling Hook** - Fetches status every 1.5s during operation
2. **Progress State** - Tracks current step/total/label
3. **Progress UI** - Displays progress bar and status
4. **Completion Handler** - Reloads page when done

## Implementation Guide

### Step 1: Define Status Enum

```php
// app/Enums/ResourceStatus.php
enum ResourceStatus: string
{
    case Pending = 'pending';
    case Installing = 'installing';
    case Active = 'active';
    case Failed = 'failed';
    case Uninstalling = 'uninstalling';
    case Stopped = 'stopped';
}
```

### Step 2: Create Milestones Classes

```php
// app/Packages/Services/Resource/ResourceInstallerMilestones.php
class ResourceInstallerMilestones extends Milestones
{
    public const UPDATE_PACKAGES = 'update_packages';
    public const INSTALL_DEPENDENCIES = 'install_dependencies';
    public const CONFIGURE_SERVICE = 'configure_service';
    public const START_SERVICE = 'start_service';
    public const VERIFY_INSTALLATION = 'verify_installation';
    public const INSTALLATION_COMPLETE = 'installation_complete';

    private const LABELS = [
        self::UPDATE_PACKAGES => 'Updating system packages',
        self::INSTALL_DEPENDENCIES => 'Installing dependencies',
        self::CONFIGURE_SERVICE => 'Configuring service',
        self::START_SERVICE => 'Starting service',
        self::VERIFY_INSTALLATION => 'Verifying installation',
        self::INSTALLATION_COMPLETE => 'Installation complete',
    ];

    protected function steps(): array
    {
        return array_keys(self::LABELS);
    }

    protected function labels(): array
    {
        return self::LABELS;
    }
}

// app/Packages/Services/Resource/ResourceRemoverMilestones.php
class ResourceRemoverMilestones extends Milestones
{
    public const STOP_SERVICE = 'stop_service';
    public const BACKUP_DATA = 'backup_data';
    public const REMOVE_PACKAGES = 'remove_packages';
    public const REMOVE_DATA = 'remove_data';
    public const CLEANUP = 'cleanup';
    public const UNINSTALLATION_COMPLETE = 'uninstallation_complete';

    private const LABELS = [
        self::STOP_SERVICE => 'Stopping service',
        self::BACKUP_DATA => 'Backing up data',
        self::REMOVE_PACKAGES => 'Removing packages',
        self::REMOVE_DATA => 'Removing data directories',
        self::CLEANUP => 'Cleaning up',
        self::UNINSTALLATION_COMPLETE => 'Uninstallation complete',
    ];

    protected function steps(): array
    {
        return array_keys(self::LABELS);
    }

    protected function labels(): array
    {
        return self::LABELS;
    }
}
```

### Step 3: Implement Package with Milestone Tracking

```php
// app/Packages/Services/Resource/ResourceInstaller.php
class ResourceInstaller extends PackageInstaller implements ServerPackage
{
    public function milestones(): Milestones
    {
        return new ResourceInstallerMilestones;
    }

    public function execute(): void
    {
        $this->install($this->commands());
    }

    protected function commands(): array
    {
        return [
            'apt-get update -y',
            $this->track(ResourceInstallerMilestones::UPDATE_PACKAGES),

            'apt-get install -y dependency1 dependency2',
            $this->track(ResourceInstallerMilestones::INSTALL_DEPENDENCIES),

            'configure-command',
            $this->track(ResourceInstallerMilestones::CONFIGURE_SERVICE),

            'systemctl enable --now service-name',
            $this->track(ResourceInstallerMilestones::START_SERVICE),

            'systemctl status service-name --no-pager',
            $this->track(ResourceInstallerMilestones::VERIFY_INSTALLATION),

            // Update resource status to active
            fn () => $this->server->resources()->latest()->first()?->update([
                'status' => ResourceStatus::Active->value,
            ]),

            $this->track(ResourceInstallerMilestones::INSTALLATION_COMPLETE),
        ];
    }
}

// app/Packages/Services/Resource/ResourceRemover.php
class ResourceRemover extends PackageRemover implements ServerPackage
{
    public function milestones(): Milestones
    {
        return new ResourceRemoverMilestones;
    }

    public function execute(): void
    {
        $this->remove($this->commands());
    }

    protected function commands(): array
    {
        return [
            'systemctl stop service-name',
            $this->track(ResourceRemoverMilestones::STOP_SERVICE),

            'backup-command',
            $this->track(ResourceRemoverMilestones::BACKUP_DATA),

            'apt-get remove -y --purge package-name',
            $this->track(ResourceRemoverMilestones::REMOVE_PACKAGES),

            'rm -rf /data/directory',
            $this->track(ResourceRemoverMilestones::REMOVE_DATA),

            'apt-get autoremove -y',
            $this->track(ResourceRemoverMilestones::CLEANUP),

            // Delete resource record
            fn () => $this->server->resources()->delete(),

            $this->track(ResourceRemoverMilestones::UNINSTALLATION_COMPLETE),
        ];
    }
}
```

### Step 4: Create Jobs

```php
// app/Packages/Services/Resource/ResourceInstallerJob.php
class ResourceInstallerJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public Server $server) {}

    public function handle(): void
    {
        Log::info("Starting resource installation for server #{$this->server->id}");

        try {
            $installer = new ResourceInstaller($this->server);
            $installer->execute();

            Log::info("Resource installation completed for server #{$this->server->id}");
        } catch (\Exception $e) {
            Log::error("Resource installation failed for server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update status to failed so UI can show error state
            $this->server->resources()->latest()->first()?->update([
                'status' => ResourceStatus::Failed->value,
            ]);

            throw $e;
        }
    }
}

// app/Packages/Services/Resource/ResourceRemoverJob.php
class ResourceRemoverJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public Server $server) {}

    public function handle(): void
    {
        Log::info("Starting resource removal for server #{$this->server->id}");

        try {
            $remover = new ResourceRemover($this->server);
            $remover->execute();

            Log::info("Resource removal completed for server #{$this->server->id}");
        } catch (\Exception $e) {
            Log::error("Resource removal failed for server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
```

### Step 5: Create Controller with Status Endpoint

```php
// app/Http/Controllers/ServerResourceController.php
class ServerResourceController extends Controller
{
    public function index(Server $server): Response
    {
        $resource = $server->resources()->latest()->first();

        return Inertia::render('servers/resource', [
            'server' => $server->only(['id', 'vanity_name', ...]),
            'installedResource' => $resource ? [
                'id' => $resource->id,
                'status' => $resource->status,
                'progress_step' => 0, // Will be filled by frontend polling
                'progress_total' => 0,
                'progress_label' => null,
                'created_at' => $resource->created_at?->toISOString(),
            ] : null,
        ]);
    }

    public function store(Request $request, Server $server): RedirectResponse
    {
        $resource = $server->resources()->create([
            'status' => ResourceStatus::Installing->value,
            // ... other fields
        ]);

        ResourceInstallerJob::dispatch($server);

        return back()->with('success', 'Resource installation started.');
    }

    public function destroy(Server $server): RedirectResponse
    {
        $resource = $server->resources()->first();

        if (!$resource) {
            return back()->with('error', 'No resource found to uninstall.');
        }

        $resource->update(['status' => ResourceStatus::Uninstalling->value]);

        ResourceRemoverJob::dispatch($server);

        return back()->with('success', 'Resource uninstallation started.');
    }

    // CRITICAL: Status endpoint for polling
    public function status(Server $server): JsonResponse
    {
        $resource = $server->resources()->latest()->first();

        // If no resource exists, it was uninstalled
        if (!$resource) {
            return response()->json([
                'status' => 'uninstalled',
                'resource' => null,
            ]);
        }

        // Get progress from the latest resource-related server event
        // IMPORTANT: Use orderBy('id', 'desc') not latest() to avoid timestamp ambiguity
        $latestEvent = $server->events()
            ->where('service_type', 'resource') // Match your service type
            ->orderBy('id', 'desc')
            ->first();

        $progressStep = $latestEvent?->current_step ?? 0;
        $progressTotal = $latestEvent?->total_steps ?? 0;
        $progressLabel = $latestEvent?->milestone ?? null;

        return response()->json([
            'status' => $resource->status, // Top-level for frontend
            'progress_step' => $progressStep,
            'progress_total' => $progressTotal,
            'progress_label' => $progressLabel,
            'resource' => [
                'id' => $resource->id,
                'status' => $resource->status,
                'progress_step' => $progressStep,
                'progress_total' => $progressTotal,
                'progress_label' => $progressLabel,
                'created_at' => $resource->created_at->toISOString(),
                'updated_at' => $resource->updated_at->toISOString(),
            ],
        ]);
    }
}
```

### Step 6: Add Routes

```php
// routes/web.php
Route::middleware(['auth'])->group(function () {
    Route::get('/servers/{server}/resource', [ServerResourceController::class, 'index']);
    Route::post('/servers/{server}/resource', [ServerResourceController::class, 'store']);
    Route::delete('/servers/{server}/resource', [ServerResourceController::class, 'destroy']);

    // Status endpoint for polling
    Route::get('/servers/{server}/resource/status', [ServerResourceController::class, 'status']);
});
```

### Step 7: Frontend Implementation

```tsx
// resources/js/pages/servers/resource.tsx
import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';

type InstalledResource = {
    id: number;
    status: string;
    progress_step?: number | null;
    progress_total?: number | null;
    progress_label?: string | null;
} | null;

export default function Resource({
    server,
    installedResource,
}: {
    server: { id: number };
    installedResource: InstalledResource;
}) {
    // Progress state
    const [progress, setProgress] = useState<{ step: number; total: number; label?: string } | null>(
        (installedResource?.status === 'installing' || installedResource?.status === 'uninstalling') &&
        installedResource?.progress_total
            ? {
                  step: installedResource.progress_step ?? 0,
                  total: installedResource.progress_total ?? 0,
                  label: installedResource.progress_label ?? undefined,
              }
            : null,
    );

    // Status flags
    const isInstalling = installedResource?.status === 'installing';
    const isUninstalling = installedResource?.status === 'uninstalling';
    const isProcessing = isInstalling || isUninstalling;

    // Polling effect
    useEffect(() => {
        if (!isProcessing) return;

        let cancelled = false;
        const id = window.setInterval(async () => {
            try {
                const res = await fetch(`/servers/${server.id}/resource/status`, {
                    headers: { Accept: 'application/json' }
                });

                if (!res.ok) return;

                const json = await res.json();

                if (cancelled) return;

                // Update progress bar
                if (json.progress_total) {
                    setProgress({
                        step: json.progress_step ?? 0,
                        total: json.progress_total ?? 0,
                        label: json.progress_label ?? undefined,
                    });
                }

                // Check for completion
                if (json.status === 'active' || json.status === 'failed' || json.status === 'uninstalled') {
                    window.clearInterval(id);
                    // Reload page data to show completion state
                    router.reload({ only: ['installedResource'] });
                }
            } catch {
                // Ignore transient errors
            }
        }, 1500);

        return () => {
            cancelled = true;
            window.clearInterval(id);
        };
    }, [isProcessing, server.id]);

    // Uninstall handler
    const handleUninstall = () => {
        if (confirm('Are you sure? This cannot be undone.')) {
            router.delete(`/servers/${server.id}/resource`, {
                preserveScroll: true,
                onSuccess: () => {
                    // Reload to show uninstalling status
                    router.reload({ only: ['installedResource'] });
                },
            });
        }
    };

    return (
        <>
            {installedResource && (
                <div>
                    {/* Status Display */}
                    <div>
                        Status: {installedResource.status}
                        {isProcessing && progress?.total && (
                            <span> ({progress.step}/{progress.total})</span>
                        )}
                    </div>

                    {/* Progress Bar */}
                    {isProcessing && (
                        <div className="mt-4">
                            <div className="mb-1 flex items-center justify-between">
                                <div className="text-sm text-muted-foreground">
                                    {progress?.label ||
                                        (isInstalling ? 'Running installation steps...' : 'Running uninstallation steps...')}
                                </div>
                                {progress?.total && (
                                    <div className="text-xs text-muted-foreground">
                                        {Math.floor(((progress.step ?? 0) / (progress.total ?? 1)) * 100)}%
                                    </div>
                                )}
                            </div>

                            {/* Progress Bar */}
                            <div className="h-2 w-full overflow-hidden rounded bg-muted">
                                <div
                                    className="h-full bg-primary transition-all"
                                    style={{
                                        width: progress?.total
                                            ? `${Math.floor(((progress.step ?? 0) / (progress.total ?? 1)) * 100)}%`
                                            : '25%',
                                    }}
                                />
                            </div>

                            <div className="mt-2 text-xs text-muted-foreground">
                                Do not close this page — we're {isInstalling ? 'installing' : 'uninstalling'}
                                the resource over SSH.
                            </div>
                        </div>
                    )}

                    {/* Actions (hidden during processing) */}
                    {!isProcessing && (
                        <button onClick={handleUninstall}>
                            Uninstall Resource
                        </button>
                    )}
                </div>
            )}
        </>
    );
}
```

## Key Implementation Details

### Backend Gotchas

1. **Use `orderBy('id', 'desc')` not `latest()`**
   - Multiple events can have same `created_at` timestamp
   - `latest()` ordering is ambiguous with same timestamps
   - Always use `->orderBy('id', 'desc')->first()`

2. **Status endpoint must return top-level status**
   ```php
   return response()->json([
       'status' => $resource->status, // Frontend checks this
       'progress_step' => $progressStep,
       'progress_total' => $progressTotal,
       'progress_label' => $progressLabel,
       'resource' => [...], // Nested resource data
   ]);
   ```

3. **Handle "uninstalled" state**
   ```php
   if (!$resource) {
       return response()->json([
           'status' => 'uninstalled',
           'resource' => null,
       ]);
   }
   ```

4. **Update status to failed on job exception**
   ```php
   catch (\Exception $e) {
       $this->server->resources()->latest()->first()?->update([
           'status' => ResourceStatus::Failed->value,
       ]);
       throw $e;
   }
   ```

### Frontend Gotchas

1. **Check correct completion statuses**
   ```typescript
   // Match your enum values exactly
   if (json.status === 'active' || json.status === 'failed' || json.status === 'uninstalled') {
       // NOT 'installed' or other values
   }
   ```

2. **Reload data after delete request**
   ```typescript
   router.delete(`/servers/${server.id}/resource`, {
       preserveScroll: true,
       onSuccess: () => {
           router.reload({ only: ['installedResource'] }); // Essential!
       },
   });
   ```

3. **Initialize progress from props**
   ```typescript
   const [progress, setProgress] = useState<{ step: number; total: number; label?: string } | null>(
       (installedResource?.status === 'installing' || installedResource?.status === 'uninstalling') &&
       installedResource?.progress_total
           ? { ... }
           : null,
   );
   ```

4. **Prevent password manager popups**
   ```tsx
   <input
       type="password"
       autoComplete="new-password" // Prevents save password popup
   />
   ```

## Testing Checklist

- [ ] Install resource - shows progress bar with steps
- [ ] Install resource - milestone labels update during installation
- [ ] Install resource - percentage reaches 100%
- [ ] Install resource - auto-refreshes when status becomes 'active'
- [ ] Install resource - polling stops after completion
- [ ] Failed installation - status updates to 'failed'
- [ ] Failed installation - can retry or delete
- [ ] Uninstall resource - shows progress bar with steps
- [ ] Uninstall resource - milestone labels update during removal
- [ ] Uninstall resource - auto-refreshes when resource deleted
- [ ] Uninstall resource - shows empty state after completion
- [ ] Page refresh during operation - progress persists
- [ ] Multiple rapid install/uninstall cycles work correctly
- [ ] Network interruption doesn't break polling (resumes automatically)

## Customization Points

### Change Polling Interval
```typescript
}, 1500); // Default 1.5 seconds - adjust as needed
```

### Change Service Type
```php
->where('service_type', 'your_service_type')
```

### Add Custom Statuses
```php
enum ResourceStatus: string
{
    // Add your custom statuses
    case Updating = 'updating';
    case Restarting = 'restarting';
}
```

### Custom Progress Bar Colors
```tsx
<div className="h-full bg-primary transition-all" /> // Change bg-primary
```

## Common Issues & Solutions

| Issue | Cause | Solution |
|-------|-------|----------|
| Progress stuck at X/Y | Wrong event returned from query | Use `orderBy('id', 'desc')` not `latest()` |
| Polling never stops | Wrong completion status | Check enum values match exactly |
| No progress bar on uninstall | Page data not reloaded | Add `router.reload()` in `onSuccess` |
| Password save popup | Missing autocomplete | Add `autoComplete="new-password"` |
| Progress resets on refresh | Progress not initialized from props | Initialize state from `installedResource` prop |

## Real-World Example

See implementation in:
- **Backend**: `app/Http/Controllers/ServerDatabaseController.php`
- **Frontend**: `resources/js/pages/servers/database.tsx`
- **Package**: `app/Packages/Services/Database/PostgreSQL/PostgreSqlInstaller.php`
- **Milestones**: `app/Packages/Services/Database/PostgreSQL/PostgreSqlInstallerMilestones.php`
