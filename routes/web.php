<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GitHubRepositoriesController;
use App\Http\Controllers\GitHubWebhookController;
use App\Http\Controllers\ProvisionCallbackController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\ServerDatabaseController;
use App\Http\Controllers\ServerFileExplorerController;
use App\Http\Controllers\ServerFirewallController;
use App\Http\Controllers\ServerMonitorController;
use App\Http\Controllers\ServerMonitoringController;
use App\Http\Controllers\ServerNodeController;
use App\Http\Controllers\ServerPhpController;
use App\Http\Controllers\ServerProvisioningController;
use App\Http\Controllers\ServerSchedulerController;
use App\Http\Controllers\ServerSettingsController;
use App\Http\Controllers\ServerSiteCommandsController;
use App\Http\Controllers\ServerSiteDeploymentsController;
use App\Http\Controllers\ServerSiteEnvironmentController;
use App\Http\Controllers\ServerSiteGitController;
use App\Http\Controllers\ServerSiteGitRepositoryController;
use App\Http\Controllers\ServerSiteInstallationController;
use App\Http\Controllers\ServerSitesController;
use App\Http\Controllers\ServerSiteSettingsController;
use App\Http\Controllers\ServerSupervisorController;
use App\Http\Controllers\SourceProviderController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

/*
|--------------------------------------------------------------------------
| Provisioning Routes (Public with signed URLs)
|--------------------------------------------------------------------------
*/

// Public endpoint to fetch provisioning installer script
Route::get('servers/{server}/provision', [ServerProvisioningController::class, 'provision'])
    ->name('servers.provision');

// Provisioning step callback with signed URL verification
Route::post('servers/{server}/provision/step', [ProvisionCallbackController::class, 'step'])
    ->middleware('signed')
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
    ->name('servers.provision.step');

/*
|--------------------------------------------------------------------------
| Webhook Routes (Public)
|--------------------------------------------------------------------------
*/

// GitHub webhook endpoint for auto-deploy (eager load server to avoid N+1)
Route::post('webhooks/github/{site}', GitHubWebhookController::class)
    ->name('webhooks.github')
    ->scopeBindings();

/*
|--------------------------------------------------------------------------
| API Routes (Public)
|--------------------------------------------------------------------------
*/

// Server monitoring metrics submission from remote servers
Route::post('api/servers/{server}/metrics', [ServerMonitoringController::class, 'storeMetrics'])
    ->middleware([
        \App\Http\Middleware\ValidateMonitoringToken::class,
        'throttle:'.config('monitoring.rate_limit').',1',
    ])
    ->name('api.servers.metrics.store');

// Server scheduler task run submission from remote servers
Route::post('api/servers/{server}/scheduler/runs', [ServerSchedulerController::class, 'storeTaskRun'])
    ->middleware([
        \App\Http\Middleware\ValidateSchedulerToken::class,
        'throttle:'.config('scheduler.rate_limit').',1',
    ])
    ->name('api.servers.scheduler.runs.store');

Route::get('/test', function () {
    return \App\Http\Resources\ServerResource::make(\App\Models\Server::find(18));
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

/*
|--------------------------------------------------------------------------
| Server Management Routes (Authenticated)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    // Source Provider OAuth Routes
    Route::prefix('source-providers')->name('source-providers.')->group(function () {
        Route::get('github/callback', [SourceProviderController::class, 'callbackGitHub'])
            ->name('github.callback');
    });

    // Server CRUD (except index and create - modal-based creation)
    Route::resource('servers', ServerController::class)
        ->except(['index', 'create'])
        ->scoped(); // Enable implicit route model binding

    // Server-specific routes grouped by prefix with scoped bindings
    Route::prefix('servers/{server}')->name('servers.')->scopeBindings()->group(function () {
        // Provisioning workflow
        Route::get('provisioning/setup', [ServerProvisioningController::class, 'show'])
            ->name('provisioning');

        // Provision services
        Route::prefix('provision')->name('provision.')->group(function () {
            Route::post('retry', [ServerProvisioningController::class, 'retry'])
                ->name('retry');
            Route::get('services', [ServerProvisioningController::class, 'services'])
                ->name('services');
            Route::post('services', [ServerProvisioningController::class, 'storeServices'])
                ->name('services.store');
            Route::get('events', [ServerProvisioningController::class, 'events'])
                ->name('events');
        });

        // Services management (databases + cache/queue)
        Route::get('services', [ServerDatabaseController::class, 'services'])
            ->name('services');

        // Database detail page
        Route::get('databases/{database}', [ServerDatabaseController::class, 'show'])
            ->name('databases.show');

        // Database Schema management
        Route::post('databases/{database}/schemas', [\App\Http\Controllers\ServerDatabaseSchemaController::class, 'store'])
            ->name('databases.schemas.store');
        Route::delete('databases/{database}/schemas/{schema}', [\App\Http\Controllers\ServerDatabaseSchemaController::class, 'destroy'])
            ->name('databases.schemas.destroy');

        // Database User management
        Route::post('databases/{database}/users', [\App\Http\Controllers\ServerDatabaseUserController::class, 'store'])
            ->name('databases.users.store');
        Route::patch('databases/{database}/users/{user}', [\App\Http\Controllers\ServerDatabaseUserController::class, 'update'])
            ->name('databases.users.update');
        Route::post('databases/{database}/users/{user}/retry', [\App\Http\Controllers\ServerDatabaseUserController::class, 'retry'])
            ->name('databases.users.retry');
        Route::post('databases/{database}/users/{user}/cancel-update', [\App\Http\Controllers\ServerDatabaseUserController::class, 'cancelUpdate'])
            ->name('databases.users.cancel-update');
        Route::delete('databases/{database}/users/{user}', [\App\Http\Controllers\ServerDatabaseUserController::class, 'destroy'])
            ->name('databases.users.destroy');

        // Database CRUD endpoints
        Route::post('databases', [ServerDatabaseController::class, 'store'])
            ->name('databases.install');
        Route::patch('databases/{database}', [ServerDatabaseController::class, 'update'])
            ->name('databases.update');
        Route::post('databases/{database}/retry', [ServerDatabaseController::class, 'retry'])
            ->name('databases.retry')
            ->scopeBindings();
        Route::delete('databases/{database}', [ServerDatabaseController::class, 'destroy'])
            ->name('databases.uninstall');

        // PHP management
        Route::get('php', [ServerPhpController::class, 'index'])
            ->name('php');
        Route::post('php', [ServerPhpController::class, 'store'])
            ->name('php.store');
        Route::post('php/install', [ServerPhpController::class, 'install'])
            ->name('php.install');
        Route::patch('php/{php}/set-cli-default', [ServerPhpController::class, 'setCliDefault'])
            ->name('php.set-cli-default');
        Route::patch('php/{php}/set-site-default', [ServerPhpController::class, 'setSiteDefault'])
            ->name('php.set-site-default');
        Route::post('php/{php}/retry', [ServerPhpController::class, 'retry'])
            ->name('php.retry');
        Route::delete('php/{php}', [ServerPhpController::class, 'destroy'])
            ->name('php.destroy');

        // Node.js management
        Route::get('node', [ServerNodeController::class, 'index'])
            ->name('node');
        Route::post('node/install', [ServerNodeController::class, 'install'])
            ->name('node.install');
        Route::patch('node/{node}/set-default', [ServerNodeController::class, 'setDefault'])
            ->name('node.set-default');
        Route::post('node/{node}/retry', [ServerNodeController::class, 'retry'])
            ->name('node.retry');
        Route::delete('node/{node}', [ServerNodeController::class, 'destroy'])
            ->name('node.destroy');
        Route::post('composer/update', [ServerNodeController::class, 'updateComposer'])
            ->name('composer.update');
        Route::post('composer/retry', [ServerNodeController::class, 'retryComposer'])
            ->name('composer.retry');

        // Server credentials
        Route::get('deploy-key', [ServerSitesController::class, 'deployKey'])
            ->name('deploy-key');

        // GitHub repositories
        Route::get('github/repositories', [GitHubRepositoriesController::class, 'index'])
            ->name('github.repositories');
        Route::get('github/repositories/{owner}/{repo}/branches', [GitHubRepositoriesController::class, 'branches'])
            ->name('github.branches');
        Route::get('github/repositories/{owner}/{repo}/permissions', [GitHubRepositoriesController::class, 'permissions'])
            ->name('github.permissions');

        // Sites management
        Route::prefix('sites')->scopeBindings()->group(function () {
            Route::get('/', [ServerSitesController::class, 'index'])
                ->name('sites');
            Route::post('/', [ServerSitesController::class, 'store'])
                ->name('sites.store');
            Route::get('{site}/installing', [ServerSiteInstallationController::class, 'show'])
                ->name('sites.installing');
            Route::post('{site}/deploy-key', [ServerSitesController::class, 'generateDeployKey'])
                ->name('sites.deploy-key.generate');
            Route::get('{site}/commands', ServerSiteCommandsController::class)
                ->name('sites.commands');
            Route::post('{site}/commands', [ServerSiteCommandsController::class, 'store'])
                ->name('sites.commands.execute');
            Route::get('{site}/deployments', [ServerSiteDeploymentsController::class, 'show'])
                ->name('sites.deployments');
            Route::put('{site}/deployments', [ServerSiteDeploymentsController::class, 'update'])
                ->name('sites.deployments.update');
            Route::post('{site}/deployments', [ServerSiteDeploymentsController::class, 'deploy'])
                ->name('sites.deployments.deploy');
            Route::post('{site}/deployments/{deployment}/rollback', [ServerSiteDeploymentsController::class, 'rollback'])
                ->name('sites.deployments.rollback');
            Route::post('{site}/deployments/auto-deploy', [ServerSiteDeploymentsController::class, 'toggleAutoDeploy'])
                ->name('sites.deployments.auto-deploy');
            Route::get('{site}/deployments/{deployment}/status', [ServerSiteDeploymentsController::class, 'status'])
                ->name('sites.deployments.status');
            Route::get('{site}/deployments/{deployment}/stream', [ServerSiteDeploymentsController::class, 'streamLog'])
                ->name('sites.deployments.stream');
            Route::get('{site}/settings', [ServerSiteSettingsController::class, 'show'])
                ->name('sites.settings');
            Route::get('{site}/settings/git/setup', [ServerSiteGitRepositoryController::class, 'show'])
                ->name('sites.settings.git.setup');
            Route::post('{site}/settings/git/setup', [ServerSiteGitRepositoryController::class, 'store'])
                ->name('sites.settings.git.store');
            Route::post('{site}/git/cancel', [ServerSiteGitController::class, 'cancel'])
                ->name('sites.git.cancel');
            Route::patch('{site}/set-default', [ServerSitesController::class, 'setDefault'])
                ->name('sites.set-default');
            Route::patch('{site}/unset-default', [ServerSitesController::class, 'unsetDefault'])
                ->name('sites.unset-default');
            Route::post('{site}/uninstall', [ServerSitesController::class, 'uninstall'])
                ->name('sites.uninstall')
                ->middleware('throttle:5,1');
            Route::post('{site}/retry-installation', [ServerSitesController::class, 'retryInstallation'])
                ->name('sites.retry-installation');
            Route::delete('{site}', [ServerSitesController::class, 'destroy'])
                ->name('sites.destroy')
                ->middleware('throttle:5,1');
            Route::get('{site}/explorer', [ServerFileExplorerController::class, 'show'])
                ->name('sites.explorer');
            Route::prefix('{site}/files')->name('sites.files.')->group(function () {
                Route::get('/', [ServerFileExplorerController::class, 'index'])
                    ->name('index');
                Route::post('upload', [ServerFileExplorerController::class, 'store'])
                    ->name('upload');
                Route::get('download', [ServerFileExplorerController::class, 'download'])
                    ->name('download');
                Route::delete('delete', [ServerFileExplorerController::class, 'destroy'])
                    ->name('delete');
            });
            Route::get('{site}/environment', [ServerSiteEnvironmentController::class, 'edit'])
                ->name('sites.environment.edit');
            Route::put('{site}/environment', [ServerSiteEnvironmentController::class, 'update'])
                ->name('sites.environment.update');
            Route::get('{site}', [ServerSitesController::class, 'show'])
                ->name('sites.show');
            Route::post('/', [ServerSitesController::class, 'store'])
                ->name('sites.store');
        });

        // Firewall management
        Route::prefix('firewall')->group(function () {
            Route::get('/', [ServerFirewallController::class, 'index'])
                ->name('firewall');
            Route::get('/status', [ServerFirewallController::class, 'status'])
                ->name('firewall.status');
            Route::post('/', [ServerFirewallController::class, 'store'])
                ->name('firewall.store');
            Route::post('/{firewallRule}/retry', [ServerFirewallController::class, 'retry'])
                ->name('firewall.retry')
                ->withoutScopedBindings();
            Route::delete('/{rule}', [ServerFirewallController::class, 'destroy'])
                ->name('firewall.destroy');
        });

        // Monitoring management
        Route::prefix('monitoring')->group(function () {
            Route::get('/', [ServerMonitoringController::class, 'index'])
                ->name('monitoring');
            Route::post('install', [ServerMonitoringController::class, 'install'])
                ->name('monitoring.install');
            Route::post('uninstall', [ServerMonitoringController::class, 'uninstall'])
                ->name('monitoring.uninstall');
            Route::post('retry', [ServerMonitoringController::class, 'retry'])
                ->name('monitoring.retry');
            Route::post('update-interval', [ServerMonitoringController::class, 'updateInterval'])
                ->name('monitoring.update-interval');
            Route::get('metrics', [ServerMonitoringController::class, 'getMetrics'])
                ->name('monitoring.metrics');

            // Monitor management (alert triggers)
            Route::prefix('monitors')->group(function () {
                Route::get('/', [ServerMonitorController::class, 'index'])
                    ->name('monitors.index');
                Route::post('/', [ServerMonitorController::class, 'store'])
                    ->name('monitors.store');
                Route::put('{monitor}', [ServerMonitorController::class, 'update'])
                    ->name('monitors.update');
                Route::delete('{monitor}', [ServerMonitorController::class, 'destroy'])
                    ->name('monitors.destroy');
                Route::post('{monitor}/toggle', [ServerMonitorController::class, 'toggle'])
                    ->name('monitors.toggle');
            });
        });

        // Tasks management (unified scheduler + supervisor view)
        Route::get('tasks', [ServerSchedulerController::class, 'tasks'])
            ->name('tasks');

        // Scheduler management
        Route::prefix('scheduler')->middleware('throttle:60,1')->group(function () {
            Route::get('/', [ServerSchedulerController::class, 'index'])
                ->name('scheduler')
                ->withoutMiddleware('throttle:60,1');

            Route::post('install', [ServerSchedulerController::class, 'install'])
                ->name('scheduler.install')
                ->middleware('throttle:5,1'); // Max 5 installs per minute

            Route::post('uninstall', [ServerSchedulerController::class, 'uninstall'])
                ->name('scheduler.uninstall')
                ->middleware('throttle:5,1'); // Max 5 uninstalls per minute

            // Task management - scoped to ensure task belongs to server
            Route::prefix('tasks')->scopeBindings()->group(function () {
                Route::post('/', [ServerSchedulerController::class, 'storeTask'])
                    ->name('scheduler.tasks.store')
                    ->middleware('throttle:20,1'); // Max 20 task creations per minute

                Route::put('{scheduledTask}', [ServerSchedulerController::class, 'updateTask'])
                    ->name('scheduler.tasks.update');

                Route::delete('{scheduledTask}', [ServerSchedulerController::class, 'destroyTask'])
                    ->name('scheduler.tasks.destroy');

                Route::post('{scheduledTask}/toggle', [ServerSchedulerController::class, 'toggleTask'])
                    ->name('scheduler.tasks.toggle');

                Route::post('{scheduledTask}/retry', [ServerSchedulerController::class, 'retryTask'])
                    ->name('scheduler.tasks.retry')
                    ->middleware('throttle:10,1'); // Max 10 retries per minute

                Route::post('{scheduledTask}/run', [ServerSchedulerController::class, 'runTask'])
                    ->name('scheduler.tasks.run')
                    ->middleware('throttle:10,1'); // Max 10 manual runs per minute

                Route::get('{scheduledTask}/runs', [ServerSchedulerController::class, 'getTaskRuns'])
                    ->name('scheduler.tasks.runs')
                    ->withoutMiddleware('throttle:60,1');

                Route::get('{scheduledTask}/activity', [ServerSchedulerController::class, 'showTaskActivity'])
                    ->name('scheduler.tasks.activity');
            });
        });

        // Supervisor management
        Route::prefix('supervisor')->middleware('throttle:60,1')->group(function () {
            Route::post('install', [ServerSupervisorController::class, 'install'])
                ->name('supervisor.install')
                ->middleware('throttle:5,1');

            Route::post('uninstall', [ServerSupervisorController::class, 'uninstall'])
                ->name('supervisor.uninstall')
                ->middleware('throttle:5,1');

            // Task management - scoped to ensure task belongs to server
            Route::prefix('tasks')->scopeBindings()->group(function () {
                Route::post('/', [ServerSupervisorController::class, 'storeTask'])
                    ->name('supervisor.tasks.store')
                    ->middleware('throttle:20,1');

                Route::put('{supervisorTask}', [ServerSupervisorController::class, 'updateTask'])
                    ->name('supervisor.tasks.update')
                    ->middleware('throttle:20,1');

                Route::delete('{supervisorTask}', [ServerSupervisorController::class, 'destroyTask'])
                    ->name('supervisor.tasks.destroy');

                Route::post('{supervisorTask}/toggle', [ServerSupervisorController::class, 'toggleTask'])
                    ->name('supervisor.tasks.toggle');

                Route::post('{supervisorTask}/restart', [ServerSupervisorController::class, 'restartTask'])
                    ->name('supervisor.tasks.restart');

                Route::post('{supervisorTask}/retry', [ServerSupervisorController::class, 'retryTask'])
                    ->name('supervisor.tasks.retry')
                    ->middleware('throttle:10,1'); // Max 10 retries per minute

                Route::get('{supervisorTask}/logs', [ServerSupervisorController::class, 'showLogs'])
                    ->name('supervisor.tasks.logs');

                Route::get('{supervisorTask}/status', [ServerSupervisorController::class, 'showStatus'])
                    ->name('supervisor.tasks.status');
            });
        });

        // Settings management
        Route::get('settings', [ServerSettingsController::class, 'index'])
            ->name('settings');
        Route::put('settings', [ServerSettingsController::class, 'update'])
            ->name('settings.update');
    });
});

/*
|--------------------------------------------------------------------------
| Stripe Webhook Routes
|--------------------------------------------------------------------------
*/

Route::post('stripe/webhook', [App\Http\Controllers\StripeWebhookController::class, 'handleWebhook'])
    ->name('cashier.webhook');

/*
|--------------------------------------------------------------------------
| Include Additional Route Files
|--------------------------------------------------------------------------
*/

require __DIR__.'/billing.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
