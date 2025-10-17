# Model Test Coverage Plan

**Status:** 0/22 models have tests
**Priority:** High - Models are core to application logic

## Overview

This document outlines the plan to create comprehensive test coverage for all Eloquent models in BrokeForge. Currently, **no model tests exist**. Each model needs both **unit tests** (testing model methods, accessors, mutators) and potentially **feature tests** (testing model behavior in real scenarios).

---

## Priority 1: Core Business Models (Critical)

These models contain significant business logic and are critical to application functionality.

### 1. Server Model
- **File:** `app/Models/Server.php`
- **Test File:** `tests/Unit/Models/ServerTest.php`
- **Why Priority 1:** Central model with extensive business logic
- **Test Coverage Needed:**
  - ✅ `generateMonitoringToken()` - generates unique token and updates model
  - ✅ `generateSchedulerToken()` - generates unique token and updates model
  - ✅ `register($publicIp)` - creates or finds server by IP
  - ✅ `isConnected()` - checks connection status
  - ✅ `isDeleted()` - checks soft delete status
  - ✅ `ssh($user)` - creates SSH connection (already tested in Credential tests)
  - ✅ `detectOsInfo()` - detects and updates OS information (needs SSH mocking)
  - ✅ `isProvisioned()` - checks provision status
  - ✅ `schedulerIsActive/Installing/Failed()` - scheduler status checks
  - ✅ `monitoringIsActive/Installing/Failed()` - monitoring status checks
  - ✅ `supervisorIsActive/Installing/Failed()` - supervisor status checks
  - ✅ `generatePassword($length)` - static password generator
  - ✅ Model events (creating, created, updated, deleting, deleted)
  - ✅ Relationships: user, sites, credentials, events, firewall, metrics, databases, etc.
  - ✅ Encrypted casts (ssh_root_password, monitoring_token, scheduler_token)
  - ✅ Enum casts (connection, provider, provision_status, monitoring_status, etc.)

### 2. User Model
- **File:** `app/Models/User.php`
- **Test File:** `tests/Unit/Models/UserTest.php`
- **Why Priority 1:** Authentication and subscription logic
- **Test Coverage Needed:**
  - ✅ `githubProvider()` - fetches GitHub source provider
  - ✅ `hasGitHubConnected()` - checks GitHub connection
  - ✅ `canCreateServer()` - subscription-based server creation check
  - ✅ `getServerLimit()` - calculates server limit from subscription
  - ✅ `getCurrentPlanSlug()` - identifies current subscription plan
  - ✅ `getRemainingServerSlots()` - calculates remaining slots
  - ✅ `isOnTrial()` - trial status check
  - ✅ `hasActiveSubscription()` - subscription status
  - ✅ `getSubscriptionStatus()` - returns status label
  - ✅ `getCurrentPlanName()` - returns plan name
  - ✅ Relationships: servers, sourceProviders, paymentMethods, billingEvents
  - ✅ Billable trait integration (Laravel Cashier)
  - ✅ Password hashing cast

### 3. ServerSite Model
- **File:** `app/Models/ServerSite.php`
- **Test File:** `tests/Unit/Models/ServerSiteTest.php`
- **Why Priority 1:** Manages site deployments and Git
- **Test Coverage Needed:**
  - ✅ `canInstallGitRepository()` - checks if Git can be installed
  - ✅ `isGitProcessing()` - checks Git processing status
  - ✅ `getGitConfiguration()` - retrieves Git config from JSON
  - ✅ `getDeploymentScript()` - retrieves deployment script
  - ✅ `updateDeploymentScript($script)` - updates deployment script
  - ✅ `hasGitRepository()` - checks Git installation status
  - ✅ Accessors: `provisioned_at_human`, `last_deployed_at_human`
  - ✅ Relationships: server, deployments, latestDeployment, commandHistory, events
  - ✅ Encrypted casts (webhook_secret)
  - ✅ Array casts (configuration)
  - ✅ Enum casts (git_status)
  - ✅ Model events (created, updated, deleted)

### 4. ServerCredential Model
- **File:** `app/Models/ServerCredential.php`
- **Test File:** `tests/Unit/Models/ServerCredentialTest.php`
- **Why Priority 1:** SSH credential management
- **Test Coverage Needed:**
  - ✅ `getUsername()` - returns SSH username
  - ✅ `generateKeyPair($server, $user)` - static key generation method
  - ✅ Encrypted casts (private_key) - encryption/decryption
  - ✅ Constants: ROOT, BROKEFORGE
  - ✅ Relationship: server
  - ✅ Integration with SshKeyGenerator service

---

## Priority 2: Service-Related Models (High)

Models that manage server services and configurations.

### 5. ServerFirewall Model
- **File:** `app/Models/ServerFirewall.php`
- **Test File:** `tests/Unit/Models/ServerFirewallTest.php`
- **Test Coverage Needed:**
  - ✅ Relationships: server, rules
  - ✅ Status tracking
  - ✅ Model events if any

### 6. ServerFirewallRule Model
- **File:** `app/Models/ServerFirewallRule.php`
- **Test File:** `tests/Unit/Models/ServerFirewallRuleTest.php`
- **Test Coverage Needed:**
  - ✅ Relationships: firewall
  - ✅ Rule validation logic
  - ✅ Status lifecycle (pending → installing → active/failed)
  - ✅ Model events (Reverb broadcasts)

### 7. ServerDatabase Model
- **File:** `app/Models/ServerDatabase.php`
- **Test File:** `tests/Unit/Models/ServerDatabaseTest.php`
- **Test Coverage Needed:**
  - ✅ Relationships: server
  - ✅ Database type/version tracking
  - ✅ Status management
  - ✅ Encrypted credentials if any

### 8. ServerPhp Model
- **File:** `app/Models/ServerPhp.php`
- **Test File:** `tests/Unit/Models/ServerPhpTest.php`
- **Test Coverage Needed:**
  - ✅ Relationships: server, modules
  - ✅ Default PHP version logic (`is_cli_default`)
  - ✅ Version management
  - ✅ Status tracking

### 9. ServerPhpModule Model
- **File:** `app/Models/ServerPhpModule.php`
- **Test File:** `tests/Unit/Models/ServerPhpModuleTest.php`
- **Test Coverage Needed:**
  - ✅ Relationships: serverPhp
  - ✅ Module installation status
  - ✅ Module configuration

### 10. ServerReverseProxy Model
- **File:** `app/Models/ServerReverseProxy.php`
- **Test File:** `tests/Unit/Models/ServerReverseProxyTest.php`
- **Test Coverage Needed:**
  - ✅ Relationships: server
  - ✅ Nginx/proxy configuration
  - ✅ Status management

### 11. ServerScheduledTask Model
- **File:** `app/Models/ServerScheduledTask.php`
- **Test File:** `tests/Unit/Models/ServerScheduledTaskTest.php`
- **Test Coverage Needed:**
  - ✅ Relationships: server, taskRuns
  - ✅ Cron expression validation
  - ✅ Task execution tracking
  - ✅ Status lifecycle (pending → installing → active/failed)
  - ✅ Model events (Reverb broadcasts)

### 12. ServerScheduledTaskRun Model
- **File:** `app/Models/ServerScheduledTaskRun.php`
- **Test File:** `tests/Unit/Models/ServerScheduledTaskRunTest.php`
- **Test Coverage Needed:**
  - ✅ Relationships: server, scheduledTask
  - ✅ Run history tracking
  - ✅ Success/failure status
  - ✅ Output/error logging

### 13. ServerSupervisor Model
- **File:** `app/Models/ServerSupervisor.php`
- **Test File:** `tests/Unit/Models/ServerSupervisorTest.php`
- **Test Coverage Needed:**
  - ✅ Relationships: server
  - ✅ Supervisor configuration
  - ✅ Status management

### 14. ServerSupervisorTask Model
- **File:** `app/Models/ServerSupervisorTask.php`
- **Test File:** `tests/Unit/Models/ServerSupervisorTaskTest.php`
- **Test Coverage Needed:**
  - ✅ Relationships: server, supervisor
  - ✅ Queue worker configuration
  - ✅ Process management
  - ✅ Status lifecycle (pending → installing → active/failed)
  - ✅ Model events (Reverb broadcasts)

---

## Priority 3: Deployment & History Models (Medium)

Models that track deployments and historical data.

### 15. ServerDeployment Model
- **File:** `app/Models/ServerDeployment.php`
- **Test File:** `tests/Unit/Models/ServerDeploymentTest.php`
- **Test Coverage Needed:**
  - ✅ Relationships: serverSite
  - ✅ Deployment status tracking
  - ✅ Git commit SHA tracking
  - ✅ Deployment output/logs
  - ✅ Status lifecycle (pending → deploying → deployed/failed)
  - ✅ Model events (Reverb broadcasts)

### 16. ServerSiteCommandHistory Model
- **File:** `app/Models/ServerSiteCommandHistory.php`
- **Test File:** `tests/Unit/Models/ServerSiteCommandHistoryTest.php`
- **Test Coverage Needed:**
  - ✅ Relationships: serverSite
  - ✅ Command execution tracking
  - ✅ Output/error logging
  - ✅ Timestamp tracking

### 17. ServerEvent Model
- **File:** `app/Models/ServerEvent.php`
- **Test File:** `tests/Unit/Models/ServerEventTest.php`
- **Test Coverage Needed:**
  - ✅ Relationships: server, serverSite (polymorphic?)
  - ✅ Event type categorization
  - ✅ Event data storage
  - ✅ Timestamp tracking

### 18. ServerMetric Model
- **File:** `app/Models/ServerMetric.php`
- **Test File:** `tests/Unit/Models/ServerMetricTest.php`
- **Test Coverage Needed:**
  - ✅ Relationships: server
  - ✅ Metric data storage (CPU, memory, disk, etc.)
  - ✅ Time series data handling
  - ✅ Aggregation methods if any

---

## Priority 4: Billing & Auth Models (Medium)

### 19. SourceProvider Model
- **File:** `app/Models/SourceProvider.php`
- **Test File:** `tests/Unit/Models/SourceProviderTest.php`
- **Test Coverage Needed:**
  - ✅ Relationships: user
  - ✅ OAuth token storage (encrypted)
  - ✅ Provider type validation (GitHub, GitLab, etc.)
  - ✅ Token refresh logic if any

### 20. PaymentMethod Model
- **File:** `app/Models/PaymentMethod.php`
- **Test File:** `tests/Unit/Models/PaymentMethodTest.php`
- **Test Coverage Needed:**
  - ✅ Relationships: user
  - ✅ Stripe integration
  - ✅ Default payment method logic
  - ✅ Card details masking

### 21. BillingEvent Model
- **File:** `app/Models/BillingEvent.php`
- **Test File:** `tests/Unit/Models/BillingEventTest.php`
- **Test Coverage Needed:**
  - ✅ Relationships: user
  - ✅ Event type tracking (charge, refund, etc.)
  - ✅ Amount/currency handling
  - ✅ Stripe event ID tracking

### 22. SubscriptionPlan Model
- **File:** `app/Models/SubscriptionPlan.php`
- **Test File:** `tests/Unit/Models/SubscriptionPlanTest.php`
- **Test Coverage Needed:**
  - ✅ Plan configuration
  - ✅ Stripe price ID mapping
  - ✅ Server limits
  - ✅ Feature flags/limits

---

## Testing Guidelines

### Unit Test Structure (All Models)

Each model test should include:

1. **Relationship Tests**
   - Verify all `belongsTo`, `hasMany`, `hasOne` relationships return correct type
   - Test relationship constraints (where clauses, etc.)

2. **Accessor/Mutator Tests**
   - Test custom accessors (appended attributes)
   - Test attribute casting (dates, encrypted, enums, etc.)

3. **Business Logic Tests**
   - Test all public methods
   - Test static methods
   - Cover happy paths, edge cases, failures

4. **Model Event Tests**
   - Test `creating`, `created`, `updating`, `updated`, `deleting`, `deleted` events
   - Verify activity logging
   - Verify Reverb broadcasts (where applicable)

5. **Validation Tests** (if applicable)
   - Test unique constraints
   - Test required fields
   - Test enum values

### Feature Test Considerations

Some models may need **feature tests** for:
- Models that interact with external services (SSH, Stripe, GitHub API)
- Models with complex multi-step workflows
- Models that broadcast real-time updates

---

## Test Execution Order

1. **Start with Priority 1** - Core models (Server, User, ServerSite, ServerCredential)
2. **Move to Priority 2** - Service models (Firewall, Database, PHP, etc.)
3. **Then Priority 3** - Deployment/history models
4. **Finally Priority 4** - Billing/auth models

---

## Success Criteria

- ✅ Each model has a dedicated test file
- ✅ All public methods are tested
- ✅ All relationships are verified
- ✅ All accessors/mutators tested
- ✅ All casts verified (especially encrypted/enum)
- ✅ Model events tested
- ✅ Tests pass in < 1 second each
- ✅ Code coverage > 80% for model directory
- ✅ All tests formatted with Pint

---

## Notes

- **SSH Mocking:** Many models use `$server->ssh()` - use partial mocking like in existing Credential tests
- **Database:** Use `RefreshDatabase` trait and real database (not mocks)
- **Factories:** Use existing factories, check for custom states
- **Reverb Broadcasting:** Models with status lifecycle should test event broadcasting
- **Encrypted Fields:** Test both encryption and decryption of sensitive data
- **Enum Casts:** Verify enum casting works correctly

---

## Progress Tracking

Update this section as tests are completed:

- [ ] Priority 1: 0/4 complete
- [ ] Priority 2: 0/10 complete
- [ ] Priority 3: 0/4 complete
- [ ] Priority 4: 0/4 complete

**Overall: 0/22 models tested (0%)**