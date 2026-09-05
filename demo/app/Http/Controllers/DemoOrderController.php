<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\OrderService;
use App\Services\PaymentService;
use SalvatoreCervone\LogOperations\Facades\LogOperations;

class DemoOrderController extends Controller
{
    /**
     * Elabora la creazione di un ordine simulato (HTTP 200 OK).
     */
    public function createOrder(Request $request, OrderService $orderService, PaymentService $paymentService)
    {
        $userId = Auth::id() ?: ($request->input('cliente_id') ?: 1);

        $articoli = $request->input('articoli', [
            ['prodotto_id' => 10, 'nome' => 'Laptop Pro 16"', 'prezzo' => 1499.00, 'quantita' => 1],
            ['prodotto_id' => 22, 'nome' => 'Mouse Wireless', 'prezzo' => 49.90, 'quantita' => 2],
        ]);

        // 1. Verifica giacenza magazzino
        LogOperations::step('Verifica giacenza magazzino', [
            'totale_articoli' => count($articoli),
            'prodotto_primario' => $articoli[0]['nome'] ?? 'N/D',
        ]);
        $disponibile = LogOperations::trace([$orderService, 'verificaGiacenza'], function () use ($orderService) {
            return $orderService->verificaGiacenza(10, 1);
        }, 'Verifica giacenza magazzino');

        // 2. Calcolo sconto fedeltà
        LogOperations::step('Applicazione sconto fedeltà utente', [
            'user_id' => $userId,
            'regola' => 'Club VIP Gold',
        ]);
        $scontoInfo = LogOperations::trace([$orderService, 'applicaScontoFedelta'], function () use ($orderService, $userId) {
            return $orderService->applicaScontoFedelta((int) $userId, 1598.80);
        }, 'Applicazione sconto fedeltà utente');

        // 3. Calcolo totale ordine
        $totale = LogOperations::trace([$orderService, 'calcolaTotale'], function () use ($orderService, $articoli, $scontoInfo) {
            return $orderService->calcolaTotale($articoli, $scontoInfo['importo_sconto']);
        }, 'Calcolo totale carrello e imposte');

        // 4. Autorizzazione pagamento
        $pagamento = LogOperations::trace([$paymentService, 'processPayment'], function () use ($paymentService, $totale) {
            return $paymentService->processPayment($totale, 'carta_credito');
        }, 'Autorizzazione transazione Gateway Pagamento');

        return response()->json([
            'success'   => true,
            'ordine_id' => 'ORD-' . rand(10000, 99999),
            'totale'    => $totale,
            'sconto'    => $scontoInfo,
            'pagamento' => $pagamento,
            'message'   => 'Ordine elaborato con successo dal Controller!',
        ]);
    }
}
