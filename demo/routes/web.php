<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;

use App\Http\Controllers\DemoOrderController;

/*
|--------------------------------------------------------------------------
| Rotte Playground & Demo Live
|--------------------------------------------------------------------------
*/

// Homepage del Playground con Dashboard incorporata
Route::get('/', function () {
    $currentUser = Auth::user();
    $users = User::all();
    return view('welcome', compact('currentUser', 'users'));
});

// Casistica 1: Creazione Ordine (Successo 200 con Controller e funzioni interne)
Route::post('/api/demo/orders', [DemoOrderController::class, 'createOrder']);

// Casistica 2: Errore 500 con Eccezione (Test Stack Trace)
Route::post('/api/demo/payment-fail', function (Request $request, PaymentService $paymentService) {
    // Forza eccezione per testare la cattura dello stack trace e dei parametri
    $paymentService->processPayment(250.00, 'bonifico', true);
});

// Casistica 3: Transazione DB non chiusa (Test Rollback ciclico automatico)
Route::post('/api/demo/unfinished-transaction', function (Request $request) {
    // Apertura transazione DB deliberatamente lasciata pendente
    DB::beginTransaction();

    // Query simulata all'interno della transazione
    DB::table('users')->where('id', 1)->update(['updated_at' => now()]);

    // Ritorna errore HTTP 400 senza fare né commit né rollback
    // Il middleware deve rilevare DB::transactionLevel() > 0 ed eseguire rollback automatico
    return response()->json([
        'success' => false,
        'error'   => 'Errore di validazione durante il checkout.',
        'note'    => 'Una transazione DB è stata lasciata aperta. Il middleware deve aver eseguito il rollback automatico!',
    ], 400);
});

// Casistica 4: Richiesta Lenta (Test duration_ms)
Route::get('/api/demo/slow-request', function () {
    // Simula elaborazione pesante da 1.2 secondi
    usleep(1200000);

    return response()->json([
        'success' => true,
        'message' => 'Elaborazione complessa terminata.',
        'simulated_delay' => '1.2s',
    ]);
});

// Casistica 5: Switch Utente Attivo
Route::post('/api/demo/login-as', function (Request $request) {
    $userId = $request->input('user_id');
    if ($userId) {
        $user = User::find($userId);
        if ($user) {
            Auth::login($user);
            return response()->json(['success' => true, 'user' => $user]);
        }
    } else {
        Auth::logout();
        return response()->json(['success' => true, 'user' => null]);
    }

    return response()->json(['success' => false, 'message' => 'Utente non trovato'], 404);
});
