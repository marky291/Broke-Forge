<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\SourceProviderController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');

    Route::get('settings/source-providers', [SourceProviderController::class, 'index'])->name('source-providers.edit');
    Route::get('settings/source-providers/github/connect', [SourceProviderController::class, 'connectGitHub'])->name('source-providers.github.connect');
    Route::delete('settings/source-providers/github', [SourceProviderController::class, 'disconnectGitHub'])->name('source-providers.github.disconnect');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/appearance');
    })->name('appearance.edit');
});
