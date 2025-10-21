# Add Retry Functionality for Failed Package Installations/Removals

## Problem Statement

When package installation or removal jobs fail (status: `failed`), users have no way to retry the operation from the UI. Failed items remain in the list with a failed status badge, but users cannot click "Retry" to attempt the operation again.

**Current User Experience:**
1. User attempts to install MySQL database
2. Installation fails due to SSH timeout or package manager lock
3. Database record shows `failed` status with red badge
4. User has NO way to retry - must manually delete and recreate
5. This is frustrating and leads to data loss (error context is lost)

**Desired User Experience:**
1. User attempts to install MySQL database
2. Installation fails due to SSH timeout or package manager lock
3. Database record shows `failed` status with red badge
4. User clicks action menu (⋮) and selects "Retry Installation"
5. System resets status to `pending` and re-dispatches the job
6. User sees real-time status updates via Reverb as retry progresses

## Current State

**Retry Functionality EXISTS for:**
- ✅ **Scheduled Tasks** (`ServerScheduledTask`) - See `tasks.tsx:384-390` and `ServerSchedulerController:231-261`
- ✅ **Supervisor Tasks** (`ServerSupervisorTask`) - See `tasks.tsx:483-490` and `ServerSupervisorController:261-289`

**Retry Functionality MISSING for:**
- ❌ **Databases** (`ServerDatabase`) - MySQL, MariaDB, PostgreSQL, Redis
- ❌ **PHP Versions** (`ServerPhp`)
- ❌ **Firewall Rules** (`ServerFirewallRule`)
- ❌ **Other Package Jobs** (if any future packages are added)

## Dependency: Issue #2

**⚠️ IMPORTANT:** This feature depends on [Issue #2](https://github.com/marky291/Broke-Forge/issues/2) being fixed first.

Without proper `failed()` method implementation in jobs, items may not reliably show `failed` status, making retry impossible to trigger. Issue #2 ensures all jobs properly update status to `failed` when they encounter errors.

**Implementation Order:**
1. Fix Issue #2 first (add `failed()` method to all jobs)
2. Then implement this retry feature (add retry endpoints and UI)

## Reference Implementation Pattern

The existing retry implementation for Scheduled Tasks provides the pattern to follow:

### Backend Pattern (Controller)

```php
// Example: ServerSchedulerController.php:231-261
public function retryTask(Server $server, ServerScheduledTask $scheduledTask): RedirectResponse
{
    // 1. Authorization check
    $this->authorize('update', $server);

    // 2. Validate status (only allow retry for failed items)
    if ($scheduledTask->status !== TaskStatus::Failed) {
        return redirect()
            ->route('servers.tasks', $server)
            ->with('error', 'Only failed tasks can be retried');
    }

    // 3. Audit logging
    Log::info('Scheduled task retry initiated', [
        'user_id' => auth()->id(),
        'server_id' => $server->id,
        'task_id' => $scheduledTask->id,
        'task_name' => $scheduledTask->name,
        'command' => $scheduledTask->command,
        'ip_address' => request()->ip(),
    ]);

    // 4. Reset status to 'pending'
    $scheduledTask->update(['status' => 'pending']);

    // 5. Re-dispatch the installer job
    ServerScheduleTaskInstallerJob::dispatch($server, $scheduledTask->id);

    // 6. Redirect with success message
    return redirect()
        ->route('servers.tasks', $server)
        ->with('success', 'Task retry started');
}
```

### Frontend Pattern (CardList Actions)

```tsx
// Example: tasks.tsx:384-390
actions={(task) => {
    const actions: CardListAction[] = [];

    // Show "Retry Installation" action ONLY when status is 'failed'
    if (task.status === 'failed') {
        actions.push({
            label: 'Retry Installation',
            onClick: () => handleRetryScheduledTask(task),
            icon: <RotateCw className="h-4 w-4" />,
            disabled: processing,
        });
    }

    // ... other actions

    return actions;
}}
```

### Frontend Handler

```tsx
// Example: tasks.tsx:201-208
const handleRetryScheduledTask = (task: ServerScheduledTask) => {
    if (!confirm(`Retry installing "${task.name}"?`)) {
        return;
    }
    post(`/servers/${server.id}/scheduler/tasks/${task.id}/retry`, {
        onSuccess: () => router.reload(),
    });
};
```

## Implementation Requirements

### 1. Backend Implementation

#### Databases (ServerDatabaseController)

**Route:**
```php
Route::post('{database}/retry', [ServerDatabaseController::class, 'retry'])
    ->name('databases.retry')
    ->scopeBindings();
```

**Controller Method:**
```php
public function retry(Server $server, ServerDatabase $database): RedirectResponse
{
    $this->authorize('update', $server);

    if ($database->status !== DatabaseStatus::Failed) {
        return back()->with('error', 'Only failed databases can be retried');
    }

    Log::info('Database installation retry initiated', [
        'user_id' => auth()->id(),
        'server_id' => $server->id,
        'database_id' => $database->id,
        'database_type' => $database->type,
        'database_version' => $database->version,
        'ip_address' => request()->ip(),
    ]);

    $database->update([
        'status' => DatabaseStatus::Pending,
        'error_message' => null, // Clear previous error
    ]);

    // Re-dispatch appropriate installer job based on database type
    match($database->type) {
        DatabaseType::MySQL => MySqlInstallerJob::dispatch($server, $database->id),
        DatabaseType::MariaDB => MariaDbInstallerJob::dispatch($server, $database->id),
        DatabaseType::PostgreSQL => PostgreSqlInstallerJob::dispatch($server, $database->id),
        DatabaseType::Redis => RedisInstallerJob::dispatch($server, $database->id),
    };

    return back()->with('success', 'Database installation retry started');
}
```

#### PHP Versions (ServerPhpController)

**Route:**
```php
Route::post('{php}/retry', [ServerPhpController::class, 'retry'])
    ->name('php.retry')
    ->scopeBindings();
```

**Controller Method:**
```php
public function retry(Server $server, ServerPhp $php): RedirectResponse
{
    $this->authorize('update', $server);

    if ($php->status !== PhpStatus::Failed) {
        return back()->with('error', 'Only failed PHP installations can be retried');
    }

    Log::info('PHP installation retry initiated', [
        'user_id' => auth()->id(),
        'server_id' => $server->id,
        'php_id' => $php->id,
        'php_version' => $php->version,
        'ip_address' => request()->ip(),
    ]);

    $php->update([
        'status' => PhpStatus::Pending,
        'error_message' => null,
    ]);

    PhpInstallerJob::dispatch($server, $php->id);

    return back()->with('success', 'PHP installation retry started');
}
```

#### Firewall Rules (ServerFirewallController)

**Route:**
```php
Route::post('{firewallRule}/retry', [ServerFirewallController::class, 'retry'])
    ->name('firewall.retry')
    ->scopeBindings();
```

**Controller Method:**
```php
public function retry(Server $server, ServerFirewallRule $firewallRule): RedirectResponse
{
    $this->authorize('update', $server);

    if ($firewallRule->status !== FirewallRuleStatus::Failed) {
        return back()->with('error', 'Only failed firewall rules can be retried');
    }

    Log::info('Firewall rule installation retry initiated', [
        'user_id' => auth()->id(),
        'server_id' => $server->id,
        'rule_id' => $firewallRule->id,
        'rule_name' => $firewallRule->name,
        'port' => $firewallRule->port,
        'ip_address' => request()->ip(),
    ]);

    $firewallRule->update([
        'status' => FirewallRuleStatus::Pending,
        'error_message' => null,
    ]);

    FirewallRuleInstallerJob::dispatch($server, $firewallRule->id);

    return back()->with('success', 'Firewall rule installation retry started');
}
```

### 2. Frontend Implementation

#### Update Database Page (database-modern.tsx)

Currently uses `DatabaseStatusDisplay` component. Need to investigate and update to use CardList pattern or add retry to the status display component.

**Note:** The database page doesn't currently use CardList - it uses a custom `DatabaseStatusDisplay` component. This may need refactoring or the retry button added to that component specifically.

#### Update PHP Page (php.tsx)

**Add handler:**
```tsx
const handleRetryPhp = (php: ServerPhp) => {
    if (!confirm(`Retry installing PHP ${php.version}?`)) {
        return;
    }
    router.post(`/servers/${server.id}/php/${php.id}/retry`, {}, {
        onSuccess: () => router.reload(),
    });
};
```

**Update actions (line 240-272):**
```tsx
actions={(php) => {
    const actions: CardListAction[] = [];
    const isInTransition = php.status === 'pending' || php.status === 'installing' || php.status === 'removing';

    // Add retry action for failed PHP installations
    if (php.status === 'failed') {
        actions.push({
            label: 'Retry Installation',
            onClick: () => handleRetryPhp(php),
            icon: <RotateCw className="h-4 w-4" />,
            disabled: processing,
        });
    }

    // Existing actions...
    if (!php.is_cli_default) {
        actions.push({
            label: 'Set as CLI Default',
            onClick: () => handleSetCliDefault(php),
            disabled: isInTransition || php.status === 'failed',
        });
    }

    // ... rest of actions
}}
```

#### Update Firewall Page (firewall.tsx)

**Add handler:**
```tsx
const handleRetryFirewallRule = (rule: FirewallRule) => {
    if (!confirm(`Retry installing firewall rule "${rule.name}"?`)) {
        return;
    }
    router.post(`/servers/${server.id}/firewall/${rule.id}/retry`, {}, {
        onSuccess: () => router.reload(),
    });
};
```

**Update actions (line 194-209):**
```tsx
actions={(rule) => {
    const actions: CardListAction[] = [];
    const isInTransition = rule.status === 'pending' || rule.status === 'installing' || rule.status === 'removing';

    // Add retry action for failed firewall rules
    if (rule.status === 'failed') {
        actions.push({
            label: 'Retry Installation',
            onClick: () => handleRetryFirewallRule(rule),
            icon: <RotateCw className="h-4 w-4" />,
            disabled: processing,
        });
    }

    // Existing delete action
    if (rule.id && (!rule.status || rule.status === 'active' || rule.status === 'failed')) {
        actions.push({
            label: 'Delete Rule',
            onClick: () => handleDeleteRule(rule.id!),
            variant: 'destructive',
            icon: <Trash2 className="h-4 w-4" />,
            disabled: isInTransition,
        });
    }

    return actions;
}}
```

### 3. Comprehensive Test Coverage

#### HTTP/Feature Tests

**Database Retry Tests** (`tests/Feature/Http/Controllers/ServerDatabaseControllerTest.php`):
```php
test('user can retry failed database installation', function () {
    $server = Server::factory()->for($this->user)->create();
    $database = ServerDatabase::factory()->for($server)->create([
        'status' => DatabaseStatus::Failed,
        'error_message' => 'Connection timeout',
    ]);

    Queue::fake();

    $response = $this->actingAs($this->user)
        ->post(route('servers.databases.retry', [$server, $database]));

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Database installation retry started');

    $database->refresh();
    expect($database->status)->toBe(DatabaseStatus::Pending);
    expect($database->error_message)->toBeNull();

    Queue::assertPushed(MySqlInstallerJob::class);
});

test('user cannot retry non-failed database', function () {
    $server = Server::factory()->for($this->user)->create();
    $database = ServerDatabase::factory()->for($server)->create([
        'status' => DatabaseStatus::Active,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('servers.databases.retry', [$server, $database]));

    $response->assertRedirect();
    $response->assertSessionHas('error', 'Only failed databases can be retried');

    $database->refresh();
    expect($database->status)->toBe(DatabaseStatus::Active);
});

test('user cannot retry another users database', function () {
    $otherUser = User::factory()->create();
    $server = Server::factory()->for($otherUser)->create();
    $database = ServerDatabase::factory()->for($server)->create([
        'status' => DatabaseStatus::Failed,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('servers.databases.retry', [$server, $database]));

    $response->assertForbidden();
});
```

**PHP Retry Tests** (`tests/Feature/Http/Controllers/ServerPhpControllerTest.php`):
```php
test('user can retry failed php installation', function () {
    $server = Server::factory()->for($this->user)->create();
    $php = ServerPhp::factory()->for($server)->create([
        'status' => PhpStatus::Failed,
        'version' => '8.3',
        'error_message' => 'Package manager lock',
    ]);

    Queue::fake();

    $response = $this->actingAs($this->user)
        ->post(route('servers.php.retry', [$server, $php]));

    $response->assertRedirect();
    $response->assertSessionHas('success', 'PHP installation retry started');

    $php->refresh();
    expect($php->status)->toBe(PhpStatus::Pending);
    expect($php->error_message)->toBeNull();

    Queue::assertPushed(PhpInstallerJob::class);
});

test('user cannot retry non-failed php installation', function () {
    $server = Server::factory()->for($this->user)->create();
    $php = ServerPhp::factory()->for($server)->create([
        'status' => PhpStatus::Active,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('servers.php.retry', [$server, $php]));

    $response->assertRedirect();
    $response->assertSessionHas('error', 'Only failed PHP installations can be retried');
});

test('user cannot retry another users php installation', function () {
    $otherUser = User::factory()->create();
    $server = Server::factory()->for($otherUser)->create();
    $php = ServerPhp::factory()->for($server)->create([
        'status' => PhpStatus::Failed,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('servers.php.retry', [$server, $php]));

    $response->assertForbidden();
});
```

**Firewall Retry Tests** (`tests/Feature/Http/Controllers/ServerFirewallControllerTest.php`):
```php
test('user can retry failed firewall rule installation', function () {
    $server = Server::factory()->for($this->user)->create();
    $rule = ServerFirewallRule::factory()->for($server)->create([
        'status' => FirewallRuleStatus::Failed,
        'error_message' => 'SSH connection failed',
    ]);

    Queue::fake();

    $response = $this->actingAs($this->user)
        ->post(route('servers.firewall.retry', [$server, $rule]));

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Firewall rule installation retry started');

    $rule->refresh();
    expect($rule->status)->toBe(FirewallRuleStatus::Pending);
    expect($rule->error_message)->toBeNull();

    Queue::assertPushed(FirewallRuleInstallerJob::class);
});

test('user cannot retry non-failed firewall rule', function () {
    $server = Server::factory()->for($this->user)->create();
    $rule = ServerFirewallRule::factory()->for($server)->create([
        'status' => FirewallRuleStatus::Active,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('servers.firewall.retry', [$server, $rule]));

    $response->assertRedirect();
    $response->assertSessionHas('error', 'Only failed firewall rules can be retried');
});

test('user cannot retry another users firewall rule', function () {
    $otherUser = User::factory()->create();
    $server = Server::factory()->for($otherUser)->create();
    $rule = ServerFirewallRule::factory()->for($server)->create([
        'status' => FirewallRuleStatus::Failed,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('servers.firewall.retry', [$server, $rule]));

    $response->assertForbidden();
});
```

#### Inertia Tests

**PHP Page Retry UI Tests** (`tests/Feature/Inertia/Servers/PhpTest.php`):
```php
test('failed php installation shows retry button in action menu', function () {
    $server = Server::factory()->for($this->user)->create();
    ServerPhp::factory()->for($server)->create([
        'version' => '8.3',
        'status' => PhpStatus::Failed,
        'error_message' => 'Installation failed: package manager timeout',
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('servers.php', $server));

    $response->assertInertia(fn ($page) => $page
        ->component('servers/php')
        ->has('server.phps', 1)
        ->where('server.phps.0.status', 'failed')
        ->where('server.phps.0.error_message', 'Installation failed: package manager timeout')
    );
});

test('active php installation does not show retry button', function () {
    $server = Server::factory()->for($this->user)->create();
    ServerPhp::factory()->for($server)->create([
        'version' => '8.3',
        'status' => PhpStatus::Active,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('servers.php', $server));

    $response->assertInertia(fn ($page) => $page
        ->component('servers/php')
        ->has('server.phps', 1)
        ->where('server.phps.0.status', 'active')
    );
});

test('retry button triggers status change to pending', function () {
    $server = Server::factory()->for($this->user)->create();
    $php = ServerPhp::factory()->for($server)->create([
        'version' => '8.3',
        'status' => PhpStatus::Failed,
    ]);

    Queue::fake();

    $this->actingAs($this->user)
        ->post(route('servers.php.retry', [$server, $php]));

    $php->refresh();
    expect($php->status)->toBe(PhpStatus::Pending);
});
```

**Firewall Page Retry UI Tests** (`tests/Feature/Inertia/Servers/FirewallTest.php`):
```php
test('failed firewall rule shows retry button in action menu', function () {
    $server = Server::factory()->for($this->user)->create();
    $server->firewall_status = 'active';
    $server->save();

    ServerFirewallRule::factory()->for($server)->create([
        'name' => 'HTTP',
        'port' => '80',
        'status' => FirewallRuleStatus::Failed,
        'error_message' => 'UFW command failed',
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('servers.firewall', $server));

    $response->assertInertia(fn ($page) => $page
        ->component('servers/firewall')
        ->has('server.firewall.rules', 1)
        ->where('server.firewall.rules.0.status', 'failed')
        ->where('server.firewall.rules.0.error_message', 'UFW command failed')
    );
});

test('retry button clears error message on retry', function () {
    $server = Server::factory()->for($this->user)->create();
    $rule = ServerFirewallRule::factory()->for($server)->create([
        'status' => FirewallRuleStatus::Failed,
        'error_message' => 'Previous error',
    ]);

    Queue::fake();

    $this->actingAs($this->user)
        ->post(route('servers.firewall.retry', [$server, $rule]));

    $rule->refresh();
    expect($rule->status)->toBe(FirewallRuleStatus::Pending);
    expect($rule->error_message)->toBeNull();
});
```

## UI/UX Guidelines

### Retry Button Appearance

1. **Icon**: Use `<RotateCw className="h-4 w-4" />` from `lucide-react` (consistent with existing pattern)
2. **Label**: "Retry Installation" (or "Retry" for shorter menus)
3. **Placement**: First action in dropdown menu when status is `failed`
4. **Confirmation**: Show confirmation dialog before retrying (prevent accidental clicks)
5. **Disabled State**: Disable while `processing` is true (prevent double-clicks)

### Status Badge Behavior

1. **Failed Status**: Show red badge with "Failed" text
2. **After Retry**: Badge should immediately change to "Pending" (optimistic UI via Reverb)
3. **Real-time Updates**: Use Reverb WebSocket to update badge as job progresses: `pending` → `installing` → `active` or `failed`

### Error Message Display

1. **Show Error Context**: Display `error_message` field near the failed item (tooltip, subtitle, or expandable details)
2. **Clear on Retry**: Automatically clear `error_message` when user clicks retry
3. **Help Text**: Optionally show hint like "Installation failed. Click retry to try again."

## Acceptance Criteria

### Backend
- [ ] Retry endpoint added to `ServerDatabaseController` with proper authorization
- [ ] Retry endpoint added to `ServerPhpController` with proper authorization
- [ ] Retry endpoint added to `ServerFirewallController` with proper authorization
- [ ] Routes registered for all retry endpoints in `routes/web.php`
- [ ] All retry methods validate status is `failed` before allowing retry
- [ ] All retry methods clear `error_message` when resetting to `pending`
- [ ] All retry methods log audit trail with user info and IP address
- [ ] All retry methods re-dispatch appropriate installer job

### Frontend
- [ ] PHP page shows "Retry Installation" action for failed PHP installations
- [ ] Firewall page shows "Retry Installation" action for failed firewall rules
- [ ] Database page shows retry functionality (may require component refactor)
- [ ] All retry buttons use `RotateCw` icon consistently
- [ ] All retry buttons show confirmation dialog before executing
- [ ] All retry buttons disabled during processing
- [ ] Failed items display error message from `error_message` field

### Tests
- [ ] HTTP tests verify retry endpoint authorization (own servers only)
- [ ] HTTP tests verify retry only works for `failed` status
- [ ] HTTP tests verify retry resets status to `pending`
- [ ] HTTP tests verify retry clears `error_message`
- [ ] HTTP tests verify retry dispatches correct job
- [ ] Inertia tests verify failed items render with correct status
- [ ] Inertia tests verify retry button appears only for failed items
- [ ] Inertia tests verify retry triggers status change

### Manual Testing
- [ ] Create database, force it to fail, verify retry button appears
- [ ] Click retry, verify confirmation dialog appears
- [ ] Confirm retry, verify status changes to `pending` immediately
- [ ] Verify Reverb updates show status progression in real-time
- [ ] Verify retry succeeds and status becomes `active`
- [ ] Repeat for PHP installations
- [ ] Repeat for Firewall rules
- [ ] Verify authorization: cannot retry another user's failed items

## Related Files

### Backend
- `app/Http/Controllers/ServerDatabaseController.php`
- `app/Http/Controllers/ServerPhpController.php`
- `app/Http/Controllers/ServerFirewallController.php`
- `routes/web.php`

### Frontend
- `resources/js/pages/servers/database-modern.tsx`
- `resources/js/pages/servers/php.tsx`
- `resources/js/pages/servers/firewall.tsx`
- `resources/js/components/card-list.tsx`

### Tests
- `tests/Feature/Http/Controllers/ServerDatabaseControllerTest.php`
- `tests/Feature/Http/Controllers/ServerPhpControllerTest.php`
- `tests/Feature/Http/Controllers/ServerFirewallControllerTest.php`
- `tests/Feature/Inertia/Servers/PhpTest.php`
- `tests/Feature/Inertia/Servers/FirewallTest.php`
- `tests/Feature/Inertia/Servers/DatabasesTest.php` (if exists)

### Reference Implementation
- ✅ `resources/js/pages/servers/tasks.tsx` (lines 384-390, 483-490, 201-208, 261-268)
- ✅ `app/Http/Controllers/ServerSchedulerController.php` (lines 231-261)
- ✅ `app/Http/Controllers/ServerSupervisorController.php` (lines 261-289)
- ✅ `routes/web.php` (lines 299-300, 345-346)

## Additional Notes

### Why This Matters

Failed package installations are common in production environments:
- SSH connection timeouts
- Package manager locks (dpkg, apt-get)
- Transient network issues
- Resource exhaustion (disk space, memory)

Without retry functionality, users must:
1. Manually delete the failed record
2. Recreate it from scratch
3. Lose context about what failed and why
4. Risk repeating the same mistake

With retry functionality, users can:
1. See what failed and why (`error_message`)
2. Retry with one click
3. Track retry attempts in audit logs
4. Maintain historical context

### Future Enhancements

Once retry is implemented, consider:
- **Automatic Retry**: Optionally auto-retry failed jobs (with exponential backoff)
- **Retry History**: Track how many times an item has been retried
- **Smart Retry**: Detect error patterns and suggest fixes before retrying
- **Batch Retry**: Allow retrying multiple failed items at once
