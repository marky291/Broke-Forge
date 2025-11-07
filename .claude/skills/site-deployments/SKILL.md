---
name: Site Deployments
description: Use this skill when working with site deployments, rollbacks, or deployment infrastructure. Automatically invoked for implementing deployment features, understanding the symlink-based deployment architecture, or troubleshooting deployment issues. Triggered by prompts like "how do deployments work", "implement deployment feature", "add rollback", or any deployment-related questions.
allowed-tools: Bash(php artisan*), Bash(vendor/bin/pint*), Read, Write, Edit, Glob, Grep, mcp__laravel-boost__*
---

# Site Deployments - Symlink-Based Architecture

## Overview

BrokeForge uses a **symlink-based deployment architecture** that enables:
- **Zero-downtime deployments** via atomic symlink swaps
- **Instant rollbacks** to any previous deployment
- **Automatic cleanup** of old deployments (keeps last 14)
- **Shared persistent data** across deployments (storage, .env, vendor, node_modules)

## Remote Directory Structure

Every site on a remote server follows this structure:

```
/home/brokeforge/
├── deployments/
│   └── {domain}/
│       ├── shared/
│       │   ├── storage/                  # Laravel storage (persists across deployments)
│       │   ├── .env                      # Environment file (persists across deployments)
│       │   ├── vendor/                   # Composer dependencies (persists)
│       │   └── node_modules/             # NPM dependencies (persists)
│       ├── 31072025-143022/              # Deployment timestamp (ddMMYYYY-HHMMSS)
│       │   ├── deployment.log            # Deployment log for this deployment
│       │   ├── public/
│       │   ├── app/
│       │   └── ... (code and symlinks)
│       ├── 31072025-154530/              # Another deployment
│       │   ├── deployment.log
│       │   └── ... (code and symlinks)
│       └── 01082025-091245/              # Active deployment
│           ├── deployment.log
│           └── ... (code and symlinks)
└── {domain} → deployments/{domain}/01082025-091245/  # Symlink to active deployment
```

**Nginx Configuration:**
- Document root: `/home/brokeforge/{domain}/public`
- The domain path itself is a symlink that points to the active deployment

**Within Each Deployment Directory:**
```
/home/brokeforge/deployments/{domain}/01082025-091245/
├── deployment.log                        # Deployment log (automatically deleted when pruned)
├── public/
├── app/
├── storage → ../shared/storage          # Symlink to shared
├── .env → ../shared/.env                # Symlink to shared
├── vendor → ../shared/vendor            # Symlink to shared
├── node_modules → ../shared/node_modules # Symlink to shared
└── ... (full git repository)
```

## Site Provisioning Flow

**File:** `app/Packages/Services/Sites/ProvisionedSiteInstaller.php`

### When a Site is Created:

1. **Creates directory structure:**
   ```bash
   mkdir -p /home/brokeforge/{domain}/deployments
   mkdir -p /home/brokeforge/{domain}/shared/storage
   mkdir -p /home/brokeforge/{domain}/shared/vendor
   mkdir -p /home/brokeforge/{domain}/shared/node_modules
   touch /home/brokeforge/{domain}/shared/.env
   ```

2. **Generates nginx config** pointing to `/home/brokeforge/{domain}/public`

3. **If Git repository configured:**
   - Generates deployment timestamp: `ddMMYYYY-HHMMSS` (e.g., `31072025-143022`)
   - Clones to `/home/brokeforge/deployments/{domain}/{timestamp}/`
   - Creates shared symlinks inside deployment:
     - `{timestamp}/storage → ../shared/storage`
     - `{timestamp}/.env → ../shared/.env`
     - `{timestamp}/vendor → ../shared/vendor`
     - `{timestamp}/node_modules → ../shared/node_modules`
   - Creates site symlink: `/home/brokeforge/{domain}` → `deployments/{domain}/{timestamp}`
   - Creates initial `ServerDeployment` record with:
     - `status: 'success'`
     - `deployment_path: '/home/brokeforge/deployments/{domain}/{timestamp}'`
     - `commit_sha` (captured from git)
   - Sets `site.active_deployment_id` to the initial deployment

4. **If no Git repository:**
   - Creates placeholder in `deployments/{domain}/{timestamp}/public/index.php`
   - Creates site symlink anyway for consistency

## Deployment Flow

**Files:**
- `app/Http/Controllers/ServerSiteDeploymentsController.php` - Controller
- `app/Packages/Services/Sites/Deployment/SiteGitDeploymentJob.php` - Job
- `app/Packages/Services/Sites/Deployment/SiteGitDeploymentInstaller.php` - Installer

### When User Clicks "Deploy Now":

**Step 1: Controller (`ServerSiteDeploymentsController@deploy`)**
```php
// ✅ CREATE RECORD FIRST (Reverb Package Lifecycle Pattern)
$deployment = ServerDeployment::create([
    'server_id' => $server->id,
    'server_site_id' => $site->id,
    'status' => 'pending',
    'deployment_script' => $site->getDeploymentScript(),
]);

// ✅ THEN dispatch job with deployment record
SiteGitDeploymentJob::dispatch($server, $deployment);
```

**Step 2: Job (`SiteGitDeploymentJob`)**
- Follows Reverb Package Lifecycle Pattern
- Updates status: `pending → updating → success/failed`
- Model events broadcast changes via Laravel Reverb
- Calls `SiteGitDeploymentInstaller->execute()`

**Step 3: Installer (`SiteGitDeploymentInstaller`)**

The installer executes these commands on the remote server:

```bash
# 1. Generate deployment timestamp (ddMMYYYY-HHMMSS)
timestamp=$(date +%d%m%Y-%H%M%S)  # Example: 31072025-143022

# 2. Create deployment directory for THIS deployment
mkdir -p /home/brokeforge/deployments/{domain}/{timestamp}

# 3. Clone repository to the new deployment directory
GIT_SSH_COMMAND="..." git clone -b {branch} {repository} \
  /home/brokeforge/deployments/{domain}/{timestamp}

# 4. Create shared directory symlinks
ln -sfn ../shared/storage /home/brokeforge/deployments/{domain}/{timestamp}/storage
ln -sfn ../shared/.env /home/brokeforge/deployments/{domain}/{timestamp}/.env
ln -sfn ../shared/vendor /home/brokeforge/deployments/{domain}/{timestamp}/vendor
ln -sfn ../shared/node_modules /home/brokeforge/deployments/{domain}/{timestamp}/node_modules

# 5. Run deployment script (default: "git fetch && git pull")
cd /home/brokeforge/deployments/{domain}/{timestamp}
{each line of deployment script}

# 6. Capture commit SHA
git rev-parse HEAD

# 7. ATOMIC SYMLINK SWAP (zero-downtime!)
ln -sfn deployments/{domain}/{timestamp} /home/brokeforge/{domain}

# 8. Reload PHP-FPM
sudo service php{version}-fpm reload

# 9. Update database
# - deployment.deployment_path = '/home/brokeforge/deployments/{domain}/{timestamp}'
# - deployment.status = 'success'
# - site.active_deployment_id = {deployment_id}
```

**Step 4: Auto-Prune Old Deployments**

After successful deployment, the installer automatically prunes old deployments:
- Keeps last **14** deployments
- Deletes deployment directories from remote: `rm -rf {old_deployment_path}`
- Nullifies `deployment_path` in database records (preserves history)

## Rollback Flow

**Files:**
- `app/Http/Controllers/ServerSiteDeploymentsController.php` - Controller (`rollback()`)
- `app/Packages/Services/Sites/Deployment/SiteDeploymentRollbackJob.php` - Job
- `app/Packages/Services/Sites/Deployment/SiteDeploymentRollbackInstaller.php` - Installer

### When User Clicks "Rollback":

**Step 1: Validation**
```php
// Check if deployment can be rolled back
if (!$deployment->canRollback()) {
    return error; // Failed or no deployment_path
}

// Prevent rolling back to current active deployment
if ($site->active_deployment_id === $deployment->id) {
    return error;
}
```

**Step 2: Dispatch Rollback Job**
```php
SiteDeploymentRollbackJob::dispatch($server, $site, $targetDeployment);
```

**Step 3: Installer Swaps Symlink**

The rollback installer executes:

```bash
# 1. Verify deployment directory exists (e.g., 31072025-143022)
test -d /home/brokeforge/deployments/{domain}/{target_timestamp}

# 2. ATOMIC SYMLINK SWAP (instant rollback!)
ln -sfn deployments/{domain}/{target_timestamp} /home/brokeforge/{domain}

# 3. Reload PHP-FPM
sudo service php{version}-fpm reload

# 4. Update database
# - site.active_deployment_id = {target_deployment_id}
# - site.last_deployed_at = now()
```

**Rollback is instant** - just a symlink swap! No code deployment needed.

## Database Schema

### `server_sites` Table
Key columns for deployments:
- `active_deployment_id` - Foreign key to currently active `ServerDeployment`
- `last_deployment_sha` - Git commit SHA of last deployment
- `last_deployed_at` - Timestamp of last deployment

### `server_deployments` Table
Key columns:
- `server_site_id` - Foreign key to `ServerSite`
- `status` - Enum: `pending`, `updating`, `success`, `failed`
- `deployment_script` - Script executed during deployment
- `deployment_path` - Full path on remote with timestamp (e.g., `/home/brokeforge/deployments/example.com/31072025-143022`)
- `log_file_path` - Path to deployment log inside deployment directory (e.g., `/home/brokeforge/deployments/example.com/31072025-143022/deployment.log`)
- `commit_sha` - Git commit hash deployed
- `branch` - Git branch deployed from
- `started_at`, `completed_at`, `duration_ms` - Timing info

## Frontend Integration

**File:** `resources/js/pages/servers/site-deployments.tsx`

### Real-Time Updates (Reverb Pattern)

```typescript
// Listen for site updates via Laravel Echo
useEcho()
    .private(`servers.sites.${site.id}`)
    .listen('ServerSiteUpdated', () => {
        router.reload({ only: ['site'] }); // Fetch fresh data
    });
```

### Deployment Type

```typescript
type Deployment = {
    id: number;
    status: 'pending' | 'updating' | 'success' | 'failed';
    deployment_path: string | null;
    commit_sha: string | null;
    branch: string | null;
    can_rollback: boolean;  // True if status=success AND deployment_path exists
    is_active: boolean;      // True if this is the active deployment
    // ... other fields
};
```

### UI Features

- **"Active" Badge** - Shows which deployment is currently live
- **"Rollback" Button** - Only shown when `can_rollback && !is_active`
- **"View Output" Button** - Streams deployment log from remote
- **Real-time status updates** - No page refresh needed

## Key Design Decisions

### Why Symlinks?

**Atomic Deployments:**
- `ln -sfn` is an atomic operation on Linux
- Site switches from old → new deployment instantly
- No partial state during deployment

**Instant Rollbacks:**
- Just swap symlink back to previous deployment
- No code redeployment needed
- Takes ~1 second

**Zero Downtime:**
- Nginx keeps serving old deployment during new deployment build
- Only switches when new deployment is ready
- Users never see downtime

### Why Fresh Clone Per Deployment?

**Isolation:**
- Each deployment is completely independent
- No shared state between deployments
- Can rollback without worrying about git state

**Simplicity:**
- No complex git worktree management
- No git history conflicts
- Each deployment is "clean slate"

**Trade-off:**
- Uses more disk space (mitigated by pruning old deployments)
- Takes longer than `git pull` (but more reliable)

### Why Shared Directories?

**Persistent Data:**
- `storage/` - User uploads, logs, cache must persist
- `.env` - Environment config must persist
- `vendor/` & `node_modules/` - Saves deployment time, disk space

**Symlink Approach:**
- Each deployment symlinks to shared directories
- All deployments see the same data
- Environment config only managed once

## Common Operations

### Manual Prune Command

```bash
php artisan deployments:prune --keep=14
php artisan deployments:prune --keep=5 --dry-run
php artisan deployments:prune --site-id=123
```

The command:
- Keeps last N successful deployments per site
- Deletes deployment directories from remote
- Preserves database records (nullifies `deployment_path`)

### Check Active Deployment

```php
$site = ServerSite::find(1);
$activeDeployment = $site->activeDeployment; // Relationship
$activePath = $activeDeployment->deployment_path;
```

### List Rollback-Eligible Deployments

```php
$rollbackable = $site->deployments()
    ->where('status', 'success')
    ->whereNotNull('deployment_path')
    ->where('id', '!=', $site->active_deployment_id)
    ->latest()
    ->get();
```

## Reference Implementations

**Site Provisioning:** `app/Packages/Services/Sites/ProvisionedSiteInstaller.php`
- Initial site setup with symlink structure
- Creates initial deployment record

**Deployment:** `app/Packages/Services/Sites/Deployment/SiteGitDeploymentInstaller.php`
- Versioned deployments with symlink swap
- Auto-pruning logic

**Rollback:** `app/Packages/Services/Sites/Deployment/SiteDeploymentRollbackInstaller.php`
- Simple symlink swap to previous deployment

**Controller:** `app/Http/Controllers/ServerSiteDeploymentsController.php`
- `deploy()` - Triggers new deployment
- `rollback()` - Triggers rollback

**Frontend:** `resources/js/pages/servers/site-deployments.tsx`
- Real-time deployment status
- Rollback UI

## Troubleshooting

### Deployment Directory Missing

**Issue:** `deployment_path` in database but directory deleted on remote

**Solution:**
- User cannot rollback to this deployment (`can_rollback = false`)
- Prune command will nullify the path
- Create new deployment instead

### Site Symlink Broken

**Issue:** `/home/brokeforge/{domain}` symlink points to non-existent directory

**Solution:**
- Site will fail to load (nginx 404/502)
- Rollback to most recent successful deployment with valid path
- Or redeploy from controller

### Shared Directory Symlink Issues

**Issue:** Deployment directory missing shared symlinks

**Solution:**
- Deployment installer automatically creates these
- If missing, manually create:
  ```bash
  cd /home/brokeforge/deployments/{domain}/{timestamp}
  ln -sfn ../shared/storage storage
  ln -sfn ../shared/.env .env
  ln -sfn ../shared/vendor vendor
  ln -sfn ../shared/node_modules node_modules
  ```

### Too Many Deployments

**Issue:** Disk space filling up

**Solution:**
- Auto-prune runs after each deployment (keeps 14)
- Manual prune: `php artisan deployments:prune --keep=5`
- Check: `du -sh /home/brokeforge/deployments/{domain}/*`

## Testing

When testing deployment features:

1. **Test Initial Site Provisioning:**
   - Verify directory structure created
   - Verify shared directories exist
   - Verify nginx config uses symlink path
   - Verify initial deployment record created

2. **Test Deployment Flow:**
   - Verify new deployment directory created
   - Verify shared symlinks created in deployment
   - Verify `current` symlink updated
   - Verify `active_deployment_id` updated
   - Verify old deployments pruned (if >14)

3. **Test Rollback:**
   - Verify symlink swapped to target deployment
   - Verify `active_deployment_id` updated
   - Verify PHP-FPM reloaded
   - Verify cannot rollback to current active

4. **Test Pruning:**
   - Create 20 deployments
   - Verify only last 14 kept
   - Verify `deployment_path` nullified for pruned
   - Verify database records preserved

## Best Practices

1. **Always use Reverb Package Lifecycle Pattern** for deployments
2. **Never manually modify `/home/brokeforge/{domain}` symlink** - let installer handle it
3. **Shared directories must be writable** by `brokeforge` user
4. **Keep deployment scripts idempotent** - can be run multiple times safely
5. **Test rollback before relying on it** - verify deployment paths exist
6. **Monitor disk space** - deployments use significant space
7. **Keep deployment retention reasonable** - 14 is good balance
8. **Deployment timestamps are in ddMMYYYY-HHMMSS format** - unique and human-readable
