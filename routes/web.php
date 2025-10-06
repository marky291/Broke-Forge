<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GitHubWebhookController;
use App\Http\Controllers\ProvisionCallbackController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\ServerDatabaseController;
use App\Http\Controllers\ServerFileExplorerController;
use App\Http\Controllers\ServerFirewallController;
use App\Http\Controllers\ServerMonitoringController;
use App\Http\Controllers\ServerPhpController;
use App\Http\Controllers\ServerProvisioningController;
use App\Http\Controllers\ServerSchedulerController;
use App\Http\Controllers\ServerSettingsController;
use App\Http\Controllers\ServerSiteCommandsController;
use App\Http\Controllers\ServerSiteDeploymentsController;
use App\Http\Controllers\ServerSiteGitRepositoryController;
use App\Http\Controllers\ServerSitesController;
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

// Provisioning callback with signed URL verification
Route::post('servers/{server}/provision/callback/{status}', ProvisionCallbackController::class)
    ->middleware('signed')
    ->name('servers.provision.callback');

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
        // Source Provider Management
        Route::prefix('source-providers')->name('source-providers.')->group(function () {
            Route::get('github/connect', [SourceProviderController::class, 'connectGitHub'])
                ->name('github.connect');
            Route::delete('github', [SourceProviderController::class, 'disconnectGitHub'])
                ->name('github.disconnect');
        });

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

        // Database management
        Route::prefix('database')->group(function () {
            Route::get('/', [ServerDatabaseController::class, 'index'])
                ->name('database');
            Route::get('status', [ServerDatabaseController::class, 'status'])
                ->name('database.status');
            Route::post('/', [ServerDatabaseController::class, 'store'])
                ->name('database.install');
            Route::patch('/', [ServerDatabaseController::class, 'update'])
                ->name('database.update');
            Route::delete('/', [ServerDatabaseController::class, 'destroy'])
                ->name('database.uninstall');
        });

        // PHP management
        Route::get('php', [ServerPhpController::class, 'index'])
            ->name('php');
        Route::post('php', [ServerPhpController::class, 'store'])
            ->name('php.store');
        Route::post('php/install', [ServerPhpController::class, 'install'])
            ->name('php.install');

        // Sites management
        Route::prefix('sites')->scopeBindings()->group(function () {
            Route::get('/', [ServerSitesController::class, 'index'])
                ->name('sites');
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
            Route::post('{site}/deployments/auto-deploy', [ServerSiteDeploymentsController::class, 'toggleAutoDeploy'])
                ->name('sites.deployments.auto-deploy');
            Route::get('{site}/deployments/{deployment}/status', [ServerSiteDeploymentsController::class, 'status'])
                ->name('sites.deployments.status');
            Route::get('{site}/application', [ServerSiteGitRepositoryController::class, 'show'])
                ->name('sites.application');
            Route::post('{site}/application', [ServerSiteGitRepositoryController::class, 'store'])
                ->name('sites.application.store');
            Route::get('{site}/explorer', [ServerFileExplorerController::class, 'show'])
                ->name('sites.explorer');
            Route::prefix('{site}/files')->name('sites.files.')->group(function () {
                Route::get('/', [ServerFileExplorerController::class, 'index'])
                    ->name('index');
                Route::post('upload', [ServerFileExplorerController::class, 'store'])
                    ->name('upload');
                Route::get('download', [ServerFileExplorerController::class, 'download'])
                    ->name('download');
            });
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
            Route::post('update-interval', [ServerMonitoringController::class, 'updateInterval'])
                ->name('monitoring.update-interval');
            Route::get('metrics', [ServerMonitoringController::class, 'getMetrics'])
                ->name('monitoring.metrics');
        });

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

                Route::post('{scheduledTask}/run', [ServerSchedulerController::class, 'runTask'])
                    ->name('scheduler.tasks.run')
                    ->middleware('throttle:10,1'); // Max 10 manual runs per minute

                Route::get('{scheduledTask}/runs', [ServerSchedulerController::class, 'getTaskRuns'])
                    ->name('scheduler.tasks.runs')
                    ->withoutMiddleware('throttle:60,1');
            });
        });

        // Supervisor management
        Route::prefix('supervisor')->middleware('throttle:60,1')->group(function () {
            Route::get('/', [ServerSupervisorController::class, 'index'])
                ->name('supervisor')
                ->withoutMiddleware('throttle:60,1');

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

                Route::delete('{supervisorTask}', [ServerSupervisorController::class, 'destroyTask'])
                    ->name('supervisor.tasks.destroy');

                Route::post('{supervisorTask}/toggle', [ServerSupervisorController::class, 'toggleTask'])
                    ->name('supervisor.tasks.toggle');

                Route::post('{supervisorTask}/restart', [ServerSupervisorController::class, 'restartTask'])
                    ->name('supervisor.tasks.restart');
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
