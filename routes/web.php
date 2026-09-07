<?php

use Illuminate\Support\Facades\Route;
use SalvatoreCervone\LogOperations\Http\Controllers\DashboardController;

/*
|--------------------------------------------------------------------------
| Log Operations Web Dashboard Routes
|--------------------------------------------------------------------------
|
| Rotta web autonoma per l'interfaccia grafica dei log.
| Non richiede configurazioni npm / Vite nel progetto ospitante.
|
*/

if (config('logoperations.dashboard.enabled', true)) {
    $dashboardRoute = config('logoperations.dashboard.route', 'logoperations');
    $middleware = config('logoperations.dashboard.middleware', ['web']);

    Route::middleware($middleware)
        ->prefix($dashboardRoute)
        ->group(function () {
            Route::get('/', [DashboardController::class, 'index'])->name('logoperations.dashboard');
        });
}
