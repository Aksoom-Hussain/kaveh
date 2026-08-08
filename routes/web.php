<?php

use Kaveh\Server\Http\Controllers\AlertController;
use Kaveh\Server\Http\Controllers\AuthController;
use Kaveh\Server\Http\Controllers\DashboardController;
use Kaveh\Server\Http\Controllers\MetricsController;
use Kaveh\Server\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

/*
| Loaded inside KavehServiceProvider::registerRoutes() with:
| domain + prefix(config('kaveh.path')) + middleware(['web']) + as('kaveh.')
*/

Route::get('/', fn () => auth()->check()
    ? redirect()->route('kaveh.dashboard')
    : redirect()->route('kaveh.login')
);

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('kaveh')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('api/metrics')->name('metrics.')->group(function () {
        Route::get('/pulse', [MetricsController::class, 'pulse'])->name('pulse');
        Route::get('/events', [MetricsController::class, 'events'])->name('events');
    });
    Route::get('/events', [DashboardController::class, 'events'])->name('events.index');
    Route::get('/events/{event}', [DashboardController::class, 'showEvent'])->name('events.show');

    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::post('/projects/{project}/keys', [ProjectController::class, 'createKey'])->name('projects.keys.create');
    Route::delete('/projects/{project}/keys/{apiKey}', [ProjectController::class, 'revokeKey'])->name('projects.keys.revoke');

    Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::post('/alerts', [AlertController::class, 'store'])->name('alerts.store');
    Route::post('/alerts/{alert}/toggle', [AlertController::class, 'toggle'])->name('alerts.toggle');
});
