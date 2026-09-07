<?php

use Illuminate\Support\Facades\Route;
use SalvatoreCervone\LogOperations\Http\Controllers\LogOperationsController;

/*
|--------------------------------------------------------------------------
| Log Operations API Routes
|--------------------------------------------------------------------------
|
| Rotte API per l'interrogazione dei log operazioni.
| Prefix: /api/log-operations
| Middleware: api (configurabile nel ServiceProvider)
|
| Queste rotte vengono automaticamente escluse dal middleware di logging
| per evitare loop di auto-tracciamento.
|
*/

$routeGroup = function () {
    // Lista paginata con filtri
    Route::get('/', [LogOperationsController::class, 'index']);

    // Statistiche KPI
    Route::get('/stats', [LogOperationsController::class, 'stats']);

    // Metadati per autocompletamento nei filtri
    Route::get('/http-codes', [LogOperationsController::class, 'httpCodes']);
    Route::get('/verbs', [LogOperationsController::class, 'verbs']);
    Route::get('/applications', [LogOperationsController::class, 'applications']);

    // Export streaming massivo O(1) memoria (CSV / JSON)
    Route::get('/export', [LogOperationsController::class, 'export']);

    // Dettaglio singolo log
    Route::get('/{id}', [LogOperationsController::class, 'show'])
        ->where('id', '[0-9]+');

    // Storyboard del record (timeline polimorfica di vita delle entità)
    Route::get('/storyboard', [LogOperationsController::class, 'storyboard']);
    Route::get('/storyboard/subjects', [LogOperationsController::class, 'subjects']);
    Route::get('/storyboard/{type}/{id}', [LogOperationsController::class, 'storyboardByRoute']);

    // Rotte Zero-Code Tracking Studio
    Route::prefix('studio')->group(function () {
        Route::get('/routes', [\SalvatoreCervone\LogOperations\Http\Controllers\TrackingRulesController::class, 'routesDiscovery']);
        Route::get('/classes', [\SalvatoreCervone\LogOperations\Http\Controllers\TrackingRulesController::class, 'classesDiscovery']);
        Route::get('/rules', [\SalvatoreCervone\LogOperations\Http\Controllers\TrackingRulesController::class, 'getRules']);
        Route::post('/rules', [\SalvatoreCervone\LogOperations\Http\Controllers\TrackingRulesController::class, 'saveRule']);
        Route::patch('/rules/{id}/toggle', [\SalvatoreCervone\LogOperations\Http\Controllers\TrackingRulesController::class, 'toggleRule']);
        Route::delete('/rules/{id}', [\SalvatoreCervone\LogOperations\Http\Controllers\TrackingRulesController::class, 'deleteRule']);
        Route::get('/users', [\SalvatoreCervone\LogOperations\Http\Controllers\TrackingRulesController::class, 'searchUsers']);
        Route::post('/user-session', [\SalvatoreCervone\LogOperations\Http\Controllers\TrackingRulesController::class, 'startUserSession']);
        Route::delete('/user-session/{id}', [\SalvatoreCervone\LogOperations\Http\Controllers\TrackingRulesController::class, 'stopUserSession']);
    });
};

$prefix = config('logoperations.api_prefix', 'api/logoperations');
$middleware = config('logoperations.api_middleware', ['api']);

Route::prefix($prefix)
    ->middleware($middleware)
    ->group($routeGroup);

// Alias retrocompatibile se il prefisso principale è differente
if ($prefix !== 'api/log-operations') {
    Route::prefix('api/log-operations')
        ->middleware($middleware)
        ->group($routeGroup);
}

