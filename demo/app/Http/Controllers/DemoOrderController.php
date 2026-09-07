<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Order;
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

        // 5. Creazione ed associazione del Modello Eloquent Order alla Storyboard
        $orderCount = Order::count() + 1;
        $order = Order::create([
            'reference' => 'ORD-2026-' . str_pad($orderCount, 3, '0', STR_PAD_LEFT),
            'customer_name' => Auth::user()?->name ?: 'Cliente #' . $userId,
            'amount' => $totale,
            'status' => 'confirmed',
        ]);

        // Associa esplicitamente l'entità target al log dell'operazione corrente
        LogOperations::setSubject($order);

        // Aggiunge un checkpoint applicativo direttamente sulla storyboard del modello
        $order->logStep('Ordine confermato e registrato nel database', [
            'articoli' => count($articoli),
            'totale' => $totale,
            'gateway' => 'carta_credito',
        ]);

        return response()->json([
            'success'   => true,
            'order'     => $order,
            'ordine_id' => $order->reference,
            'totale'    => $totale,
            'sconto'    => $scontoInfo,
            'pagamento' => $pagamento,
            'message'   => "Ordine {$order->reference} elaborato con successo e registrato nella Storyboard!",
        ]);
    }
}
