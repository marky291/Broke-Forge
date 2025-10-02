<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProvisionCallbackController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\ServerDatabaseController;
use App\Http\Controllers\ServerFileExplorerController;
use App\Http\Controllers\ServerFirewallController;
use App\Http\Controllers\ServerPhpController;
use App\Http\Controllers\ServerProvisioningController;
use App\Http\Controllers\ServerSettingsController;
use App\Http\Controllers\ServerSiteCommandsController;
use App\Http\Controllers\ServerSiteDeploymentsController;
use App\Http\Controllers\ServerSiteGitRepositoryController;
use App\Http\Controllers\ServerSitesController;
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

        // Database management
        Route::prefix('database')->group(function () {
            Route::get('/', [ServerDatabaseController::class, 'index'])
                ->name('database');
            Route::get('status', [ServerDatabaseController::class, 'status'])
                ->name('database.status');
            Route::post('/', [ServerDatabaseController::class, 'store'])
                ->name('database.install');
            Route::delete('/', [ServerDatabaseController::class, 'destroy'])
                ->name('database.uninstall');
        });

        // PHP management
        Route::get('php', [ServerPhpController::class, 'index'])
            ->name('php');
        Route::post('php', [ServerPhpController::class, 'store'])
            ->name('php.store');

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

        // Settings management
        Route::get('settings', [ServerSettingsController::class, 'index'])
            ->name('settings');
        Route::put('settings', [ServerSettingsController::class, 'update'])
            ->name('settings.update');
    });
});

/*
|--------------------------------------------------------------------------
| Include Additional Route Files
|--------------------------------------------------------------------------
*/

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
