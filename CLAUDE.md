# BrokeForge Project Guide

**Server management platform for automated provisioning and deployment with SSH-based configuration and real-time progress tracking.**

## Development Principles
- Use Laravel 12 best practices with Laravel Boost MCP
- Maintain minimal, clean implementations
- Write forward-thinking code without backward compatibility
- Add helpful docblocks for maintainability
- Validate server operations and self-correct on failure

## Architecture

### Package System (`app/Packages/`)
- Base classes: `PackageInstaller`, `PackageRemover`, `PackageManager`
- Structure: `Services/{Category}/{ServiceName}/`
- **Required pattern**: `execute()` for logic, `commands()` for SSH commands
- **Review existing packages before creating new ones**
- Credentials: `RootCredential`, `UserCredential`, `WorkerCredential`
- Progress tracking via `ProvisionEvent` records

### Controllers
- `ServerController` - Server CRUD
- `ServerSitesController` - Site management
- `ServerDatabaseController` - Database operations
- `ServerProvisioningController` - Provisioning workflow
- `ProvisionCallbackController` - Remote callbacks
- Each controller handles single domain concern

## Key Commands

```bash
# Development
composer dev              # Run server + queue + logs + vite
npm run build             # Production build
vendor/bin/pint --dirty   # Format PHP
npm run lint              # Fix JS/TS
npm run types             # TypeScript check

# Testing
php artisan test tests/Feature/ServerTest.php
php artisan test --filter=testName

# Database
php artisan migrate
php artisan migrate:fresh --seed

# Generators
php artisan make:model ModelName -mfc
php artisan make:controller ControllerName --resource
php artisan make:job JobName
```

## Request Flow
1. User → Inertia → Controller → Form Request → Job → Queue
2. Provisioning: Job → SSH commands → Milestone callbacks → UI updates
3. SSH: `spatie/ssh` with dynamic commands and signed callback URLs
4. Frontend: Inertia v2 + React 19 + TypeScript + Radix UI

## Package Guidelines

**Before creating packages:** Review existing packages in `app/Packages/Services/`

**Requirements:**
- Extend `PackageInstaller` or `PackageRemover`
- Implement `execute()` (logic) and `commands()` (SSH only)
- Structure: `Services/{Category}/{ServiceName}/{PackageName}Installer.php`
- Use existing credentials and enums
- Pass config via parameters, use Blade for templates

## Stack
- Laravel 12 (middleware in `bootstrap/app.php`)
- React 19 + TypeScript + Inertia v2
- Tailwind CSS v4 (`@import "tailwindcss"`)
- PHPUnit 11, spatie/ssh, Radix UI
- URL: `https://brokeforge.test`
- Database: SQLite (dev), MySQL (prod)

## Laravel Boost Guidelines

### Package Versions
- PHP 8.4.12, Laravel 12, Inertia v2, React 19, Tailwind v4, PHPUnit 11

### Key Conventions
- Follow existing code patterns in sibling files
- Use descriptive names (`isRegisteredForDiscounts` not `discount()`)
- Reuse existing components
- Don't create new base folders or change dependencies
- Create docs only when explicitly requested

### Laravel Boost Tools
- **search-docs**: Use FIRST for Laravel ecosystem docs (version-specific)
  - Pass multiple simple queries: `['routing', 'rate limiting']`
  - Don't include package names in queries
- **tinker**: Debug PHP and Eloquent queries
- **database-query**: Read-only database operations
- **browser-logs**: Read browser errors
- **get-absolute-url**: Get correct project URLs
- **list-artisan-commands**: Check command parameters

### PHP Standards
- Use constructor property promotion
- Always add return types and parameter types
- Use PHPDoc blocks, not inline comments
- Enum keys: TitleCase (`FavoritePerson`)
- Always use braces for control structures

### Inertia v2
- Components in `resources/js/Pages/`
- Use `Inertia::render()` not Blade views
- Features: polling, prefetching, deferred props, infinite scroll
- Forms: Use `<Form>` component or `useForm` hook
- Add skeletons for deferred props

### Laravel Best Practices
- Use `php artisan make:` with `--no-interaction`
- Eloquent over DB facade, use eager loading
- Form Requests for validation
- Named routes with `route()` helper
- `config()` not `env()` outside config files
- Queue long-running jobs with `ShouldQueue`
- Use factories in tests

### Laravel 12 Structure
- Middleware in `bootstrap/app.php`
- No `app/Http/Middleware/` or `Kernel.php`
- Commands auto-register from `app/Console/Commands/`
- Model casts in `casts()` method
- Column migrations must include all attributes

### Code Quality
- Run `vendor/bin/pint --dirty` before finalizing
- PHPUnit only (no Pest), run tests after changes
- Test happy/failure/edge cases
- `php artisan test --filter=testName`

### React + Inertia
- Use `<Link>` or `router.visit()` for navigation
- Forms: `<Form>` component with render props for errors/processing/success states

### Tailwind v4
- Import: `@import "tailwindcss";` (not `@tailwind`)
- Use gap utilities not margins
- Support dark mode with `dark:`
- Replaced: `bg-opacity-*` → `bg-black/*`, `flex-grow-*` → `grow-*`

## Important Reminders
- Do only what's asked
- Edit existing files rather than creating new ones
- Never create docs unless explicitly requested
