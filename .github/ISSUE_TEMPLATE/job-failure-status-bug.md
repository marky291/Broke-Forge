# Jobs fail to update status when encountering errors, leaving records stuck in transitional states

## Problem Description

When package installation/removal jobs fail due to errors, timeouts, or exceptions, the database record status is not updated to `failed`, leaving records stuck in transitional states (`installing`, `uninstalling`, `updating`). This prevents users from seeing failure conditions on the frontend and taking corrective action.

**User Impact:**
- Records appear to be "in progress" indefinitely after job failure
- Users cannot retry failed operations without manual database intervention
- No visibility into what went wrong or why the operation failed
- Frontend shows loading/processing states that never resolve

## Root Cause Analysis

The current implementation has several critical issues:

1. **Missing Laravel `failed()` method**: None of the 34+ package jobs implement Laravel's `failed(Throwable $exception)` method, which is the framework's intended way to handle job failures that occur outside the normal execution flow.

2. **Unreliable try-catch blocks**: Jobs rely solely on try-catch blocks to update status on failure, but this approach fails in several scenarios:
   - Job timeout (exceeds `$timeout = 600` seconds)
   - Fatal PHP errors (memory exhaustion, segfaults)
   - Database connection failures during the catch block
   - Queue worker crashes or restarts

3. **Inconsistent error handling**:
   - **Installer jobs** set status to `Failed` on error (correct approach)
   - **Remover jobs** try to restore `originalStatus` on error (inconsistent and confusing)

4. **No error message storage**: When jobs do fail, the error details are only logged, not stored in the database for user visibility.

## Evidence from Production Logs

```
[2025-10-21 11:00:15] local.ERROR: Failed to execute command: DEBIAN_FRONTEND=noninteractive apt-get remove -y --purge mysql-server mysql-client mysql-common mysql-server-core-* mysql-client-core-*
Error Output: E: Could not get lock /var/lib/dpkg/lock-frontend. It is held by process 585668 (apt-get)
E: Unable to acquire the dpkg frontend lock (/var/lib/dpkg/lock-frontend), is another process using it?

[2025-10-21 11:00:15] local.ERROR: MySQL database removal failed {"database_id":8,"server_id":5,"error":"Command failed..."}

[2025-10-21 11:00:15] local.ERROR: Queue job failed: App\Packages\Services\Database\MySQL\MySqlRemoverJob
```

**Result**: The MySQL database record (ID: 8) remained in `uninstalling` status indefinitely. The Redis database (ID: 9) completed successfully, but the MySQL job failure left the record in an inconsistent state.

## Affected Components

All package jobs following the Reverb Package Lifecycle Pattern are affected (34+ jobs total):

### Database Services
- `MySqlInstallerJob.php` / `MySqlRemoverJob.php` / `MySqlUpdaterJob.php`
- `MariaDbInstallerJob.php` / `MariaDbRemoverJob.php` / `MariaDbUpdaterJob.php`
- `PostgreSqlInstallerJob.php` / `PostgreSqlRemoverJob.php` / `PostgreSqlUpdaterJob.php`
- `RedisInstallerJob.php` / `RedisRemoverJob.php` / `RedisUpdaterJob.php`

### PHP Services
- `PhpInstallerJob.php` / `PhpRemoverJob.php`

### Firewall Services
- `FirewallInstallerJob.php`
- `FirewallRuleInstallerJob.php` / `FirewallRuleUninstallerJob.php`

### Scheduler Services
- `ServerSchedulerInstallerJob.php` / `ServerSchedulerRemoverJob.php`
- `ServerScheduleTaskInstallerJob.php` / `ServerScheduleTaskRemoverJob.php`

### Supervisor Services
- `SupervisorInstallerJob.php` / `SupervisorRemoverJob.php`
- `SupervisorTaskInstallerJob.php` / `SupervisorTaskRemoverJob.php`

### Site Services
- `SiteRemoverJob.php`
- `SiteGitDeploymentJob.php`
- `ProvisionedSiteInstallerJob.php`
- `GitRepositoryInstallerJob.php`

### Monitoring Services
- `ServerMonitoringInstallerJob.php` / `ServerMonitoringRemoverJob.php`
- `ServerMonitoringTimerUpdaterJob.php`

### Other Services
- `NginxInstallerJob.php`

## Current Implementation (Example: MySqlRemoverJob)

```php
public function handle(): void
{
    $database = ServerDatabase::findOrFail($this->databaseId);
    $originalStatus = $database->status;

    try {
        $database->update(['status' => 'uninstalling']);
        $remover = new MySqlRemover($this->server);
        $remover->execute();
        $database->delete();
    } catch (Exception $e) {
        // ❌ This only executes if exception is caught
        // Does NOT execute on timeout, fatal errors, or worker crashes
        $database->update(['status' => $originalStatus]);
        Log::error('MySQL database removal failed', [...]);
        throw $e;
    }
}

// ❌ MISSING: failed() method as safety net
```

## Proposed Solution

Implement Laravel's `failed()` method across all package jobs to ensure reliable status updates in all failure scenarios.

### Technical Approach

1. **Create Base Trait** (`app/Packages/Base/Traits/HandlesJobFailure.php`):
   ```php
   trait HandlesJobFailure
   {
       abstract protected function getRecordId(): int;
       abstract protected function getModelClass(): string;

       public function failed(Throwable $exception): void
       {
           $model = $this->getModelClass()::find($this->getRecordId());

           if ($model) {
               $model->update([
                   'status' => 'failed',
                   'error_message' => $exception->getMessage(),
               ]);
           }

           Log::error('Job failed: ' . static::class, [
               'record_id' => $this->getRecordId(),
               'error' => $exception->getMessage(),
           ]);
       }
   }
   ```

2. **Update All Package Jobs**:
   - Add `use HandlesJobFailure` trait
   - Implement `getRecordId()` and `getModelClass()` methods
   - Implement `failed()` method using trait logic
   - Keep existing try-catch for immediate error handling
   - Standardize all jobs to set status to `failed` (not restore original status)

3. **Database Schema Updates**:
   - Add `error_message` text column (nullable) to tables missing it:
     - `server_databases`
     - `server_phps`
     - `server_scheduled_tasks`
     - `server_supervisor_tasks`
     - `server_firewall_rules`
     - Any other tables with status tracking

4. **Standardize Status Values**:
   - Ensure all relevant enums have `Failed` case
   - Update remover jobs to use `failed` status instead of restoring original

### Example Updated Implementation

```php
class MySqlRemoverJob implements ShouldQueue
{
    use Queueable, HandlesJobFailure;

    public $timeout = 600;

    public function __construct(
        public Server $server,
        public int $databaseId
    ) {}

    protected function getRecordId(): int
    {
        return $this->databaseId;
    }

    protected function getModelClass(): string
    {
        return ServerDatabase::class;
    }

    public function handle(): void
    {
        $database = ServerDatabase::findOrFail($this->databaseId);

        try {
            $database->update(['status' => DatabaseStatus::Uninstalling]);
            $remover = new MySqlRemover($this->server);
            $remover->execute();
            $database->delete();
        } catch (Exception $e) {
            // Immediate error handling
            $database->update([
                'status' => DatabaseStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);
            Log::error('MySQL database removal failed', [...]);
            throw $e;
        }
    }

    // ✅ Safety net for timeouts, fatal errors, worker crashes
    public function failed(Throwable $exception): void
    {
        $database = ServerDatabase::find($this->databaseId);

        if ($database) {
            $database->update([
                'status' => DatabaseStatus::Failed,
                'error_message' => $exception->getMessage(),
            ]);
        }

        Log::error('MySQL removal job failed', [
            'database_id' => $this->databaseId,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

## Implementation Requirements

### 1. Database Migrations
- [ ] Create migration to add `error_message` column to all relevant tables
- [ ] Ensure all status enums include `Failed` case

### 2. Code Updates
- [ ] Create `HandlesJobFailure` trait (optional - can implement directly in each job)
- [ ] Update all 34+ package jobs to implement `failed()` method
- [ ] Standardize remover jobs to set `failed` status
- [ ] Update models to include `error_message` in `$fillable`

### 3. Frontend Updates
- [ ] Update React components to display `failed` status badge
- [ ] Show `error_message` in user-friendly format
- [ ] Add retry button for failed operations
- [ ] Ensure Reverb updates reflect failed states in real-time

### 4. Comprehensive Test Coverage

#### Unit Tests (create/update for each job type)
- [ ] `tests/Unit/Packages/Services/Database/MySQL/MySqlInstallerJobTest.php`
  - Test `failed()` method updates status to `failed`
  - Test `failed()` stores error message in database
  - Test `failed()` is called when job times out
  - Test `failed()` handles missing records gracefully

- [ ] Repeat for all other job types (34+ jobs)

#### Feature/HTTP Tests (update existing controller tests)
- [ ] `tests/Feature/Http/Controllers/ServerDatabaseControllerTest.php`
  - Test creating database returns record with `pending` status
  - Test failed job updates database status to `failed` via API
  - Test `error_message` is accessible in API response
  - Test retry endpoint for failed installations

#### Integration Tests (new)
- [ ] `tests/Feature/Packages/Services/Database/MySQL/MySqlJobFailureTest.php`
  - Test installer job failure updates status correctly
  - Test remover job failure updates status correctly
  - Test job timeout triggers `failed()` method
  - Test SSH connection failure is handled
  - Test package manager errors update status to `failed`
  - Test multiple retry attempts maintain status tracking correctly

#### Inertia Tests (update existing page tests)
- [ ] `tests/Feature/Inertia/Servers/DatabasesTest.php`
  - Test databases page displays `failed` status badge with correct styling
  - Test failed database shows error message in UI
  - Test retry button appears for failed installations
  - Test real-time Reverb updates show status changes from `installing` → `failed`
  - Test failed status prevents new operations until resolved/retried
  - Test clearing error message when retrying operation

- [ ] Repeat for all pages displaying job statuses (firewall, PHP, scheduler, supervisor, sites)

### Test Coverage Goals
- 100% coverage for all `failed()` method implementations
- All controller actions that dispatch jobs must test failure scenarios
- All Inertia pages must test failed state rendering
- Integration tests must simulate real failure conditions (SSH errors, timeouts, package manager errors)

## Acceptance Criteria

- [ ] All 34+ package jobs implement `failed()` method
- [ ] Database schema includes `error_message` column on all relevant tables
- [ ] Jobs consistently set status to `failed` (no more "restore original status")
- [ ] Frontend displays failed states with error messages and retry options
- [ ] All unit tests pass with 100% coverage of failure handling code
- [ ] All feature/HTTP tests verify API returns failed status correctly
- [ ] All integration tests verify real failure scenarios are handled
- [ ] All Inertia tests verify frontend displays failed states correctly
- [ ] Manual testing confirms jobs stuck in transitional states now update to `failed`
- [ ] Manual testing of timeout scenarios confirms `failed()` is triggered

## Related Files

- Jobs: `app/Packages/Services/*/` (all `*Job.php` files)
- Models: `app/Models/ServerDatabase.php`, `app/Models/ServerPhp.php`, `app/Models/ServerScheduledTask.php`, etc.
- Controllers: `app/Http/Controllers/Server*Controller.php`
- Enums: `app/Enums/DatabaseStatus.php`, `app/Enums/PhpStatus.php`, `app/Enums/TaskStatus.php`, etc.
- Frontend: `resources/js/pages/servers/*.tsx`

## Additional Notes

This bug affects the core reliability of the BrokeForge package lifecycle system. Users experiencing failed operations will have no visibility into failures, leading to confusion and potential data inconsistency. Implementing the `failed()` method is a Laravel best practice that should have been included in the original Reverb Package Lifecycle Pattern implementation.

The fix should maintain consistency with the existing Reverb pattern: model events automatically broadcast changes, and the frontend uses `useEcho()` + `router.reload()` for real-time updates.
