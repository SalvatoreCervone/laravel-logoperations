<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Order;
use App\Models\Invoice;
use App\Models\OrderItem;
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

// Gruppo rotte API Demo monitorate selettivamente con alias 'log.operations'
Route::middleware('log.operations')->group(function () {
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

    // Casistica 6: Operazione Multi-Modello (Auto-Discovery Eloquent: Order + Invoice + User)
    Route::post('/api/demo/multi-model-checkout', function (Request $request) {
        $count = Order::count() + 1;
        $order = Order::create([
            'reference' => 'ORD-2026-' . str_pad($count, 3, '0', STR_PAD_LEFT),
            'customer_name' => $request->input('customer_name', 'Acme Corp Spa'),
            'amount' => 450.00,
            'status' => 'confirmed',
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'INV-2026-' . str_pad($count, 3, '0', STR_PAD_LEFT),
            'total' => 450.00,
            'status' => 'issued',
        ]);

        $user = User::first();
        if ($user) {
            $user->touch();
        }

        return response()->json([
            'success' => true,
            'message' => "Operazione Multi-Modello completata! Toccati 3 modelli: Order #{$order->id}, Invoice #{$invoice->id}, User #{$user?->id}.",
            'order_id' => $order->id,
            'invoice_id' => $invoice->id,
            'user_id' => $user?->id,
        ]);
    });

    // Casistica 7: Ordine con 10 Elementi Connessi (Test visualizzazione Nested-5)
    Route::post('/api/demo/orders-with-10-items', function (Request $request) {
        $count = Order::count() + 1;
        $order = Order::create([
            'reference' => 'ORD-BULK-' . str_pad($count, 3, '0', STR_PAD_LEFT),
            'customer_name' => $request->input('customer_name', 'Fornitore Elettronica Spa'),
            'amount' => 1250.00,
            'status' => 'processing',
        ]);

        $items = [];
        $prodotti = [
            'Monitor 27" 4K', 'Tastiera Meccanica RGB', 'Mouse Ergonomico',
            'Cuffie Wireless ANC', 'Docking Station USB-C', 'Webcam 1080p Pro',
            'Stand Portatile Alluminio', 'Cavo Thunderbolt 4', 'Tappetino XXL',
            'Alimentatore GaN 100W'
        ];

        foreach ($prodotti as $prodotto) {
            $item = OrderItem::create([
                'order_id' => $order->id,
                'product_name' => $prodotto,
                'quantity' => rand(1, 3),
                'unit_price' => rand(25, 250) + 0.99,
            ]);
            $items[] = $item->id;
        }

        return response()->json([
            'success' => true,
            'message' => "Creato Ordine #{$order->id} con 10 elementi OrderItem connessi!",
            'order_id' => $order->id,
            'order_reference' => $order->reference,
            'items_count' => count($items),
            'item_ids' => $items,
        ]);
    });

    /*
    |--------------------------------------------------------------------------
    | Rotte Demo per Storyboard & Audit Trail (Fase 5)
    |--------------------------------------------------------------------------
    */
    Route::get('/api/demo/orders-list', function () {
        if (Order::count() === 0) {
            $first = Order::create([
                'reference' => 'ORD-2026-001',
                'customer_name' => 'Mario Rossi',
                'amount' => 249.99,
                'status' => 'confirmed',
            ]);
            $first->logStep('Ordine inizializzato nel sistema (Seed Demo)', [
                'note' => 'Record di esempio per testare la timeline di vita dell\'entità',
            ]);
        }
        return response()->json(Order::orderBy('id', 'desc')->get());
    });

    Route::get('/api/demo/orders/{order}', function (Order $order) {
        return response()->json([
            'order' => $order,
            'message' => 'Dettaglio ordine visualizzato. Subject rilevato automaticamente via Route Model Binding.',
        ]);
    });

    Route::post('/api/demo/orders/{order}/add-item', function (Request $request, Order $order) {
        // Crea solo l'articolo figlio. Tramite $logParents = ['order'] in OrderItem,
        // l'evento viene automaticamente propagato e collegato anche alla Storyboard di Order!
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_name' => $request->input('product_name', 'Nuovo Articolo Aggiunto'),
            'quantity' => (int) $request->input('quantity', 1),
            'unit_price' => (float) $request->input('unit_price', 75.00),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Aggiunto Articolo #{$item->id} all'Ordine #{$order->id} (propagato automaticamente via \$logParents)!",
            'item_id' => $item->id,
            'order_id' => $order->id,
        ]);
    });

    Route::put('/api/demo/orders/{order}', function (Request $request, Order $order) {
        $order->update($request->only('status', 'amount', 'customer_name'));
        return response()->json([
            'order' => $order,
            'message' => 'Stato ordine aggiornato! Evento PUT registrato sulla timeline del subject.',
        ]);
    });

    Route::post('/api/demo/orders/{order}/checkpoint', function (Request $request, Order $order) {
        $label = $request->input('label', 'Spedizione presa in carico dal corriere');
        $payload = $request->input('payload', [
            'corriere' => 'GLS Express',
            'tracking' => 'GLS-' . rand(100000, 999999),
            'colli' => 1,
            'peso_kg' => 2.4,
        ]);

        $log = $order->logStep($label, $payload);

        return response()->json([
            'success' => true,
            'log' => $log,
            'message' => 'Checkpoint registrato direttamente con $order->logStep()!',
        ]);
    });

    Route::post('/api/demo/orders/{order}/fail', function (Request $request, Order $order) {
        return response()->json([
            'success' => false,
            'error' => 'Transazione di pagamento respinta per superamento plafond.',
            'order_reference' => $order->reference,
        ], 400);
    });

    Route::post('/api/demo/orders-create', function (Request $request) {
        $count = Order::count() + 1;
        $order = Order::create([
            'reference' => 'ORD-2026-' . str_pad($count, 3, '0', STR_PAD_LEFT),
            'customer_name' => $request->input('customer_name', 'Cliente Demo ' . $count),
            'amount' => $request->input('amount', rand(80, 850) + 0.50),
            'status' => 'pending',
        ]);

        $order->logStep('Ordine creato nel sistema', [
            'creato_da' => Auth::user()?->name ?: 'Utente Anonimo',
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'order' => $order,
            'message' => "Nuovo ordine {$order->reference} creato!",
        ]);
    });
});
