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

    // Dettaglio singolo log
    Route::get('/{id}', [LogOperationsController::class, 'show'])
        ->where('id', '[0-9]+');
};

$prefix = config('logoperations.api_prefix', 'api/logoperations');

Route::prefix($prefix)
    ->middleware('api')
    ->group($routeGroup);

// Alias retrocompatibile se il prefisso principale è differente
if ($prefix !== 'api/log-operations') {
    Route::prefix('api/log-operations')
        ->middleware('api')
        ->group($routeGroup);
}
