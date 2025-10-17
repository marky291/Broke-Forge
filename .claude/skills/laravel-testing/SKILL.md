---
name: Laravel 12 Test Writer
description: Use this skill when creating tests, writing tests, creating test coverage, or testing code. Automatically invoked for creating and maintaining unit tests, HTTP tests, and Inertia tests following Laravel 12 and PHPUnit best practices for BrokeForge. Also triggered by prompts like "write tests for [URL]", "test [URL/endpoint]", "write inertia tests for modal [ModalName]", "create tests for [component/page/controller]", or any variation requesting test creation for URLs, pages, controllers, services, or frontend components.
allowed-tools: Bash(php artisan*), Bash(vendor/bin/pint*), Read, Writyese, Edit, Glob, Grep, mcp__laravel-boost__*
---

# Laravel 12 Test Writer

## Context
You are writing tests for BrokeForge, a Laravel 12 application using PHPUnit. This skill ensures all tests follow project conventions and best practices for both unit tests and HTTP/feature tests.

## Core Principles

### Test Framework
- **ALWAYS use PHPUnit** - Never use Pest
- **NEVER write performance tests** - Only write unit, feature/HTTP, and Inertia tests
- Create unit tests: `php artisan make:test --unit --no-interaction ClassName/MethodTest`
- Create HTTP/feature tests: `php artisan make:test --no-interaction FeatureNameTest`
- All tests extend `Tests\TestCase`
- **NEVER use setUp() or tearDown() methods** - Handle setup inline within each test

### Test Types
- **Unit Tests** (`tests/Unit/`): Test isolated classes/methods (services, models, helpers)
- **HTTP Tests** (`tests/Feature/`): Test backend HTTP logic - controllers, API endpoints, routes, validation
- **Inertia Tests** (`tests/Inertia/`): Test frontend pages and what users see - Inertia props, component rendering, user experience
- **HTTP tests focus on backend behavior** - status codes, database changes, redirects, validation
- **Inertia tests focus on frontend data** - component props, form submissions through Inertia, user-facing data structures
- **Always create and maintain ALL THREE types** when working on the codebase
- Most tests should be feature/Inertia tests as they provide greater confidence

### Database Configuration
- **ALWAYS use the real test database** - Configuration in `phpunit.xml`
- **DO NOT mock database operations** - Use factories and the test database
- Always use `RefreshDatabase` trait to ensure clean state

### Speed Requirements
- **Each test must complete in < 1 second**
- Use factories efficiently
- Run tests before completing work - **tests must pass before work is 100% complete**

### Mocking Policy
- **ALWAYS mock SSH connection when SSH is involved**
- **Never mock database operations** - Use real database with factories
- Use real objects and factories for everything else

### Factory Usage
- **Always use factories** to create test models
- Check factory for custom states before manually setting attributes
- Use `fake()` or `$this->faker` following existing conventions

## Workflow

### 1. Analyze Code Under Test
- Read the class/method/endpoint being tested
- **If it's a URL/page users visit:**
  - Create **HTTP test** (`tests/Feature/`) for backend behavior (validation, database, redirects)
  - Create **Inertia test** (`tests/Inertia/`) for frontend data (props, components, user experience)
- **If it's backend logic (service, model method, helper) → Create unit test**
- Identify all code paths (happy, failure, edge cases)
- Check for SSH usage (look for `$server->ssh()`)

### 2. Check Existing Tests
- Look for sibling tests to understand conventions
- Verify factory usage and mocking patterns

### 3. Create Test Files

**IMPORTANT: Directory Structure Matching**

#### Unit Tests
- Test directory structure must mirror the class directory structure (excluding `app/` root)
- Example: `/app/Packages/Services/Firewall/FirewallService.php` → `tests/Unit/Packages/Services/Firewall/FirewallServiceTest.php`
- The `app/` folder is the root - do not include it in the test path

#### HTTP/Feature Tests
- Test directory structure mirrors controller structure
- Example: `/app/Http/Controllers/ServerController.php` → `tests/Feature/Http/Controllers/ServerControllerTest.php`
- Focus on backend behavior: validation, database changes, redirects, status codes

#### Inertia Tests
- **Test directory structure must mirror the URL path**
- URL: `/dashboard` → `tests/Inertia/DashboardTest.php`
- URL: `/settings/profile` → `tests/Inertia/Settings/ProfileTest.php`
- URL: `/servers/{id}` → `tests/Inertia/Servers/ShowTest.php`
- URL: `/servers` → `tests/Inertia/Servers/IndexTest.php`
- Focus on frontend data: Inertia props, component rendering, user-facing data structures

**For Unit Tests (backend logic):**
```bash
php artisan make:test --unit --no-interaction Path/To/ClassNameTest
```

**For HTTP/Feature Tests (backend behavior):**
```bash
php artisan make:test --no-interaction Http/Controllers/ControllerNameTest
```

**For Inertia Tests (frontend pages):**
```bash
php artisan make:test --no-interaction Inertia/PageNameTest
# OR for nested routes
php artisan make:test --no-interaction Inertia/Section/PageNameTest
```

### 4. Write Comprehensive Tests

#### For HTTP Tests
Cover:
- ✅ Happy path (successful responses)
- ❌ Failure paths (error handling, validation errors)
- 🔒 Authorization (guest/user access control)
- 💾 Database operations (creates, updates, deletes)
- 🔄 Redirects and status codes
- ⚠️ Server limits and business rules

#### For Inertia Tests
Cover:
- 📄 Component rendering (correct Inertia component)
- 📊 Props structure (required data present)
- 👤 User authentication state
- 📝 Form submissions through Inertia
- ❌ Validation error display
- 🎨 Empty states (no data scenarios)
- 🔗 Data relationships (nested objects, counts)
- ✅ Success/error flash messages

### 5. Run Tests
```bash
# Run specific test
php artisan test --filter=ClassNameTest

# Run all tests in a file
php artisan test tests/Feature/ExampleTest.php
```

### 6. Format Code
```bash
vendor/bin/pint --dirty
```

### 7. Verify Completion
- ✅ All new tests pass
- ✅ All existing tests still pass
- ✅ Tests complete in < 1s
- ✅ Code formatted with Pint

## Unit Test Structure

**IMPORTANT: No setUp() or tearDown() - setup inline in each test**

```php
<?php

namespace Tests\Unit\Packages\Services;

use App\Models\Server;
use App\Packages\Services\Example\ExampleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ExampleServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that service processes valid input successfully.
     */
    public function test_processes_valid_input_successfully(): void
    {
        // Arrange - setup inline, not in setUp()
        $server = Server::factory()->create(['status' => 'active']);
        $service = new ExampleService();

        // Act
        $result = $service->process($server);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'status' => 'active',
        ]);
    }

    /**
     * Test that service throws exception for invalid input.
     */
    public function test_throws_exception_for_invalid_input(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // Arrange - inline
        $service = new ExampleService();

        // Act & Assert
        $service->process(null);
    }
}
```

## HTTP Test Structure

**Use for testing URLs and what users see on the frontend**

```php
<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user can view their servers on the servers page.
     */
    public function test_user_can_view_their_servers(): void
    {
        // Arrange - inline setup
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'name' => 'Production Server',
        ]);

        // Act - visit the URL
        $response = $this->actingAs($user)
            ->get('/servers');

        // Assert - verify what's displayed on the page
        $response->assertStatus(200);
        $response->assertSee('Production Server');
        $response->assertSee($server->public_ip);
    }

    /**
     * Test server page displays correct information.
     */
    public function test_server_page_displays_correct_information(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create([
            'user_id' => $user->id,
            'name' => 'My Server',
            'public_ip' => '192.168.1.100',
            'status' => 'active',
        ]);

        // Act - visit the server detail URL
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}");

        // Assert - verify the frontend displays correct data
        $response->assertStatus(200);
        $response->assertSee('My Server');
        $response->assertSee('192.168.1.100');
        $response->assertSee('active');
    }

    /**
     * Test user cannot view other users servers.
     */
    public function test_user_cannot_view_other_users_servers(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);

        // Act
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}");

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test user can create a server.
     */
    public function test_user_can_create_server(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)
            ->post('/servers', [
                'name' => 'Test Server',
                'public_ip' => '192.168.1.1',
                'ssh_port' => 22,
            ]);

        // Assert
        $response->assertStatus(201);
        $this->assertDatabaseHas('servers', [
            'name' => 'Test Server',
            'user_id' => $user->id,
        ]);
    }

    /**
     * Test server creation validates required fields.
     */
    public function test_server_creation_validates_required_fields(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)
            ->post('/servers', []);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'public_ip']);
    }
}
```

## Inertia Test Structure

**Use for testing Inertia pages and frontend data structures**

```php
<?php

namespace Tests\Inertia;

use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test dashboard renders correct Inertia component.
     */
    public function test_dashboard_renders_correct_component(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);

        // Act
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert - verify Inertia component
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
        );
    }

    /**
     * Test dashboard provides server data in Inertia props.
     */
    public function test_dashboard_provides_server_data_in_props(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);
        Server::factory()->create([
            'user_id' => $user->id,
            'vanity_name' => 'Test Server',
            'public_ip' => '192.168.1.100',
        ]);

        // Act
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert - verify Inertia props structure
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('dashboard.servers', 1)
            ->where('dashboard.servers.0.name', 'Test Server')
            ->where('dashboard.servers.0.public_ip', '192.168.1.100')
        );
    }

    /**
     * Test Inertia form submission creates resource.
     */
    public function test_inertia_form_submission_creates_server(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);

        // Act - simulate Inertia form POST
        $response = $this->actingAs($user)
            ->post('/servers', [
                'vanity_name' => 'New Server',
                'public_ip' => '10.0.0.1',
                'ssh_port' => 22,
                'php_version' => '8.4',
            ]);

        // Assert - redirects with success message
        $response->assertStatus(302);
        $response->assertSessionHas('success', 'Server created');

        // Verify database
        $this->assertDatabaseHas('servers', [
            'vanity_name' => 'New Server',
            'user_id' => $user->id,
        ]);
    }

    /**
     * Test Inertia form validation errors are returned.
     */
    public function test_inertia_form_validation_errors_returned(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);

        // Act - submit invalid data
        $response = $this->actingAs($user)
            ->post('/servers', [
                'vanity_name' => '',
                'public_ip' => 'invalid-ip',
            ]);

        // Assert - validation errors in session
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['vanity_name', 'public_ip', 'ssh_port', 'php_version']);
    }

    /**
     * Test Inertia receives user authentication state.
     */
    public function test_inertia_receives_user_authentication_state(): void
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'email_verified_at' => now(),
        ]);

        // Act
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert - user data shared with Inertia
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('auth.user')
            ->where('auth.user.name', 'John Doe')
            ->where('auth.user.email', 'john@example.com')
        );
    }

    /**
     * Test empty state when no data exists.
     */
    public function test_dashboard_shows_empty_state(): void
    {
        // Arrange
        $user = User::factory()->create(['email_verified_at' => now()]);

        // Act
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert - empty arrays for empty state
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('dashboard.servers', 0)
            ->has('dashboard.sites', 0)
        );
    }
}
```

## Common Inertia Test Assertions

### Testing Inertia Components
```php
// Test correct component is rendered
$response->assertInertia(fn ($page) => $page
    ->component('dashboard')
);

// Test component with path
$response->assertInertia(fn ($page) => $page
    ->component('servers/show')
);
```

### Testing Inertia Props
```php
// Test prop exists
$response->assertInertia(fn ($page) => $page
    ->has('servers')
);

// Test prop value
$response->assertInertia(fn ($page) => $page
    ->where('server.name', 'Production Server')
);

// Test nested prop
$response->assertInertia(fn ($page) => $page
    ->where('dashboard.servers.0.name', 'Test Server')
);

// Test array count
$response->assertInertia(fn ($page) => $page
    ->has('servers', 5)
);

// Test prop structure
$response->assertInertia(fn ($page) => $page
    ->has('server', fn ($server) => $server
        ->has('id')
        ->has('name')
        ->has('public_ip')
        ->etc()
    )
);
```

### Testing Authentication State
```php
// Test auth user is shared
$response->assertInertia(fn ($page) => $page
    ->has('auth.user')
    ->where('auth.user.email', 'user@example.com')
);
```

### Testing Flash Messages
```php
// Test success flash message
$response->assertSessionHas('success', 'Server created');

// Test error flash message
$response->assertSessionHas('error', 'Server limit reached');

// Test validation errors
$response->assertSessionHasErrors(['field']);
```

## Common HTTP Test Assertions

### Testing Frontend Content (What Users See)
```php
// Test visible text on the page
$response->assertSee('Welcome to BrokeForge');
$response->assertSee($server->name);
$response->assertDontSee('Hidden Content');

// Test for specific HTML/elements
$response->assertSeeInOrder(['First', 'Second', 'Third']);
$response->assertSeeText('Exact text without HTML');

// Test view data (Inertia props)
$response->assertViewHas('server', $server);
$response->assertViewHas('servers', function ($servers) {
    return $servers->count() === 3;
});
```

### Status Codes
```php
$response->assertStatus(200);
$response->assertOk();
$response->assertCreated(); // 201
$response->assertNoContent(); // 204
$response->assertNotFound(); // 404
$response->assertForbidden(); // 403
$response->assertUnauthorized(); // 401
$response->assertUnprocessable(); // 422
```

### JSON API Responses
```php
$response->assertJson(['key' => 'value']);
$response->assertJsonPath('data.id', 1);
$response->assertJsonStructure(['data' => ['id', 'name']]);
$response->assertJsonValidationErrors(['field']);
```

### Redirects & Views
```php
$response->assertRedirect('/path');
$response->assertViewIs('view.name');
```

### Database & Session
```php
$this->assertDatabaseHas('table', ['field' => 'value']);
$this->assertDatabaseMissing('table', ['field' => 'value']);
$response->assertSessionHas('key', 'value');
$response->assertSessionHasErrors(['field']);
```

## SSH Connection Mocking

```php
use Mockery;

// Mock the ssh() method on Server
$server = Mockery::mock(Server::class)->makePartial();
$mockSsh = Mockery::mock(\Spatie\Ssh\Ssh::class);

$server->shouldReceive('ssh')
    ->with('root')  // or 'brokeforge'
    ->andReturn($mockSsh);

$mockSsh->shouldReceive('execute')
    ->with('some command')
    ->andReturn('command output');
```

## Authentication in Tests

```php
// Authenticate as specific user
$user = User::factory()->create();
$response = $this->actingAs($user)->get('/dashboard');

// Test guest (unauthenticated)
$response = $this->get('/admin');
$response->assertRedirect('/login');
```

## Testing JSON APIs

```php
$response = $this->postJson('/api/servers', [
    'name' => 'API Server',
]);

$response->assertStatus(201);
$response->assertJson([
    'data' => [
        'name' => 'API Server',
    ],
]);
```

## Validation Before Completion

**CRITICAL: Work is NOT complete until:**

1. ✅ All new tests written and pass
2. ✅ All existing tests still pass
3. ✅ Tests complete in < 1s
4. ✅ Code formatted with Pint
5. ✅ All three test types created (Unit, HTTP, Inertia when applicable)
6. ✅ All paths covered (happy, failure, edge, authorization)
7. ✅ Frontend content tested (Inertia props, components, UX)
8. ✅ No setUp() or tearDown() methods used
9. ✅ Inertia tests in correct directory matching URL structure

## Complete Example: Service + Controller + Frontend Tests

### Unit Test: Service Logic (Backend)
```php
<?php

namespace Tests\Unit\Packages\Services;

use App\Models\Server;
use App\Packages\Services\Firewall\FirewallService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class FirewallServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_firewall_rule_successfully(): void
    {
        // Arrange - inline setup
        $server = Server::factory()->create();

        // Mock SSH
        $mockServer = Mockery::mock(Server::class)->makePartial();
        $mockSsh = Mockery::mock(\Spatie\Ssh\Ssh::class);

        $mockServer->shouldReceive('ssh')
            ->with('root')
            ->andReturn($mockSsh);

        $mockSsh->shouldReceive('execute')
            ->andReturn('Rule added');

        $service = new FirewallService();

        // Act
        $result = $service->addRule($mockServer, '80', 'tcp');

        // Assert
        $this->assertTrue($result);
    }

    public function test_throws_exception_for_invalid_port(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // Arrange
        $server = Server::factory()->create();
        $service = new FirewallService();

        // Act & Assert
        $service->addRule($server, 'invalid', 'tcp');
    }
}
```

### Feature Test: HTTP Endpoint + Frontend Display
```php
<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\ServerFirewallRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FirewallManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test firewall page displays existing rules.
     */
    public function test_firewall_page_displays_existing_rules(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);
        $rule = ServerFirewallRule::factory()->create([
            'server_id' => $server->id,
            'port' => '80',
            'protocol' => 'tcp',
        ]);

        // Act - visit the firewall URL
        $response = $this->actingAs($user)
            ->get("/servers/{$server->id}/firewall");

        // Assert - verify frontend displays the rule
        $response->assertStatus(200);
        $response->assertSee('80');
        $response->assertSee('tcp');
    }

    /**
     * Test user can create firewall rule via POST.
     */
    public function test_user_can_create_firewall_rule(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/firewall", [
                'port' => '443',
                'protocol' => 'tcp',
                'source' => 'anywhere',
            ]);

        // Assert
        $response->assertStatus(201);
        $this->assertDatabaseHas('server_firewall_rules', [
            'server_id' => $server->id,
            'port' => '443',
        ]);
    }

    /**
     * Test user cannot create rule for other users server.
     */
    public function test_user_cannot_create_rule_for_other_users_server(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $otherUser->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/firewall", [
                'port' => '80',
                'protocol' => 'tcp',
            ]);

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test validates required fields.
     */
    public function test_validates_required_fields(): void
    {
        // Arrange
        $user = User::factory()->create();
        $server = Server::factory()->create(['user_id' => $user->id]);

        // Act
        $response = $this->actingAs($user)
            ->post("/servers/{$server->id}/firewall", []);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['port', 'protocol']);
    }
}
```

## After Test Creation

1. Run the specific tests to verify they pass
2. Run full test suite: `php artisan test`
3. Ask user if they want to see test coverage report
4. **IMPORTANT**: Work is not complete until all tests pass

## Remember

- **Three test types required:**
  - **Unit tests** (`tests/Unit/`) - backend logic (services, models, helpers)
  - **HTTP tests** (`tests/Feature/`) - backend behavior (validation, database, redirects)
  - **Inertia tests** (`tests/Inertia/`) - frontend data (props, components, UX)
- **Inertia test organization:**
  - Directory structure mirrors URL path: `/dashboard` → `tests/Inertia/DashboardTest.php`
  - Nested routes: `/settings/profile` → `tests/Inertia/Settings/ProfileTest.php`
  - Test component rendering, props structure, form submissions, user authentication
- **NEVER use setUp() or tearDown()** - handle setup inline in each test
- **Create all three test types** when working on user-facing pages
- **Tests must pass** before work is 100% complete
- **Speed limit** - tests must complete in < 1s
- **Real database always** - use factories, never mock DB
- **Mock SSH connections** - use `$server->ssh()` partial mocks
- **Cover all paths** - happy, failure, edge, authorization, frontend display
- **Run tests immediately** after writing
- **Format with Pint** before completion
- **Most tests should be feature/Inertia tests** - they provide greater confidence
