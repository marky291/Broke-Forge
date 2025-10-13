# Laravel 12 Reverb Real-Time Provision Steps Implementation

## Project Overview

Replace polling on the provision page with Laravel Reverb for real-time updates. Backend broadcasts a notification when `$server->provision` step data changes, and frontend fetches the full `ServerProvisioningResource` to update the UI.

### The Pattern: Broadcast Notification → Fetch Full Resource

**Why This Approach?**
- ✅ Single source of truth in `ServerProvisioningResource`
- ✅ No data transformation duplication
- ✅ Consistent data structure across all updates
- ✅ Easy to maintain and extend
- ✅ Instant updates without polling overhead

---

## Implementation Checklist

### Phase 1: Backend - Event & Broadcasting

- [ ] **Create ServerProvisionUpdated Event**
  - File: `app/Events/ServerProvisionUpdated.php`
  - Implements `ShouldBroadcastNow` for immediate broadcasting
  - Uses `PrivateChannel` for security
  - Minimal payload with `broadcastWith()`

- [ ] **Add Channel Authorization**
  - File: `routes/channels.php`
  - Add: `servers.{serverId}.provision` channel
  - Authorization: User must own the server

- [ ] **Update ProvisionCallbackController**
  - File: `app/Http/Controllers/ProvisionCallbackController.php`
  - Add 5 broadcast points:
    - After `$server->provision->put($step, $status)` (line 36-37)
    - After provision_status = Failed (line 44-45)
    - After clearing provision steps (line 64-66)
    - After provision->put step 4 complete (line 114)
    - Before dispatching NginxInstallerJob (line 120)

- [ ] **Update NginxInstallerJob**
  - File: `app/Packages/Services/Nginx/NginxInstallerJob.php`
  - Add 3 broadcast points (only when `isProvisioningServer`):
    - After provision_status = Installing (line 47)
    - After provision_status = Completed (line 56)
    - After provision_status = Failed (line 61)

### Phase 2: Frontend - React Integration

- [ ] **Update Provisioning Page**
  - File: `resources/js/pages/servers/provisioning.tsx`
  - Remove polling logic (lines 71-94)
  - Add `useEcho` hook from `@laravel/echo-react`
  - Listen to `servers.${server.id}.provision` channel
  - Use `router.reload()` with `preserveScroll` and `preserveState`
  - Keep auto-redirect when provision_status === 'completed'

### Phase 3: Documentation

- [ ] **Create Reverb Pattern Guide**
  - File: `docs/reverb-real-time-pattern.md`
  - Document the recommended pattern
  - Include backend and frontend examples
  - Explain when to use this vs broadcasting full data
  - Add code snippets from this implementation

- [ ] **Update Package README**
  - File: `app/packages/README.md`
  - Add "Real-Time Updates with Laravel Reverb" section
  - Document when to broadcast in packages
  - Show Laravel 12 pattern with `ShouldBroadcastNow`
  - Include example of broadcasting in Job classes
  - Reference the pattern guide

### Phase 4: Testing

- [ ] **Create Unit Test for Event**
  - File: `tests/Unit/Events/ServerProvisionUpdatedTest.php`
  - Test implements `ShouldBroadcastNow`
  - Test broadcasts on correct private channel
  - Test payload structure

- [ ] **Update Feature Test**
  - File: `tests/Feature/ProvisionCallbackControllerTest.php`
  - Add `Event::fake()` to existing tests
  - Assert `ServerProvisionUpdated` is dispatched
  - Verify correct server ID in event

- [ ] **Create Integration Test**
  - File: `tests/Feature/ServerProvisionRealtimeTest.php`
  - Test provision step update broadcasts event
  - Test channel authorization for server owner
  - Test unauthorized users cannot access channel

- [ ] **Run Test Suite**
  - Run specific tests for this feature
  - Verify all tests pass
  - Check for any regressions

- [ ] **Run Laravel Pint**
  - Format all PHP files
  - Ensure code style compliance

---

## Technical Implementation Details

### Backend Event Structure

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

### Channel Authorization

```php
// routes/channels.php
Broadcast::channel('servers.{serverId}.provision', function (User $user, int $serverId) {
    return $user->id === Server::findOrNew($serverId)->user_id;
});
```

### Broadcasting in Controllers/Jobs

```php
use App\Events\ServerProvisionUpdated;

// After updating provision data
$server->provision->put($step, $status);
$server->save();
ServerProvisionUpdated::dispatch($server->id);
```

### Frontend React Hook

```typescript
import { useEcho } from '@laravel/echo-react';

// Listen to private channel
useEcho(
    `servers.${server.id}.provision`,
    'ServerProvisionUpdated',
    (e) => {
        router.reload({
            only: ['server'],
            preserveScroll: true,
            preserveState: true,
        });
    }
);

// Auto-redirect when complete
useEffect(() => {
    if (server.provision_status === 'completed') {
        router.visit(showServer(server.id).url);
    }
}, [server.provision_status, server.id]);
```

---

## Files to Create

1. `app/Events/ServerProvisionUpdated.php` - Broadcast event
2. `docs/reverb-real-time-pattern.md` - Pattern documentation
3. `tests/Unit/Events/ServerProvisionUpdatedTest.php` - Unit tests
4. `tests/Feature/ServerProvisionRealtimeTest.php` - Integration tests

## Files to Modify

1. `routes/channels.php` - Add channel authorization
2. `app/Http/Controllers/ProvisionCallbackController.php` - Add 5 broadcasts
3. `app/Packages/Services/Nginx/NginxInstallerJob.php` - Add 3 broadcasts
4. `resources/js/pages/servers/provisioning.tsx` - Replace polling with useEcho
5. `app/packages/README.md` - Add Reverb section
6. `tests/Feature/ProvisionCallbackControllerTest.php` - Assert broadcasts

---

## Expected Benefits

### Performance Improvements
- ⚡ **Instant Updates**: No 2-3 second polling delay
- 📉 **Reduced Load**: No repeated HTTP requests every 2-3 seconds
- 🎯 **Efficient**: Updates only when data actually changes
- 🚀 **Scalable**: WebSocket connections scale better than polling

### User Experience
- ✨ **Real-time**: Steps update immediately as they progress
- 📜 **Smooth**: No scroll jumps (preserveScroll: true)
- 🔄 **Seamless**: State preserved (preserveState: true)
- 💫 **Responsive**: Feels instant and professional

### Code Quality
- 🎯 **Single Source of Truth**: `ServerProvisioningResource` only
- 🔧 **Maintainable**: No duplicated transformation logic
- ✅ **Type Safe**: TypeScript support in React
- 📚 **Documented**: Clear pattern for future features
- 🧪 **Tested**: Comprehensive test coverage

---

## Laravel 12 Best Practices Applied

1. ✅ **ShouldBroadcastNow** - Immediate broadcasting without queue
2. ✅ **PrivateChannel** - Proper security with authorization
3. ✅ **broadcastWith()** - Control payload, minimal data sent
4. ✅ **Channel Authorization** - In `routes/channels.php`
5. ✅ **Constructor Property Promotion** - PHP 8+ feature
6. ✅ **@laravel/echo-react** - Official React hooks
7. ✅ **useEcho Hook** - Modern React pattern
8. ✅ **Type Hints** - Full type safety
9. ✅ **Event::fake()** - Proper testing approach
10. ✅ **Feature Tests** - Test actual behavior

---

## Pattern for Future Features

This implementation establishes a reusable pattern for:
- **Deployment progress** - Real-time deployment status updates
- **Site metrics** - Live server/site metric updates
- **Package installation** - Real-time package install progress
- **Database operations** - Live database operation status
- **Any long-running operations** - Generic real-time progress pattern

---

## Testing Strategy

### Unit Tests
- Event structure and interfaces
- Channel naming
- Payload structure

### Feature Tests
- Event dispatching
- Channel authorization
- Integration with controllers/jobs

### Manual Testing
1. Start Reverb server: `php artisan reverb:start`
2. Create new server
3. Watch provision page update in real-time
4. Verify no polling requests in Network tab
5. Verify smooth UX (no scroll jumps)
6. Test with multiple browser tabs

---

## Deployment Checklist

- [ ] Verify Reverb is configured in production
- [ ] Check `BROADCAST_CONNECTION=reverb` in `.env`
- [ ] Ensure Reverb server is running (Supervisor)
- [ ] Test WebSocket connections work
- [ ] Verify channel authorization works
- [ ] Monitor for any errors in logs
- [ ] Confirm performance improvements

---

## Completion Criteria

Implementation is complete when:
- ✅ All files created and modified
- ✅ All tests passing
- ✅ Code formatted with Pint
- ✅ Documentation complete
- ✅ Manual testing successful
- ✅ No polling requests in browser Network tab
- ✅ Real-time updates working smoothly
- ✅ Auto-redirect when provisioning completes

---

**Status**: 🔴 Not Started
**Last Updated**: 2025-10-13
**Assigned To**: Claude Code
**Priority**: High
