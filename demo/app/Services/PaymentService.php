<?php

namespace App\Services;

use RuntimeException;

class PaymentService
{
    /**
     * Elabora una transazione di pagamento.
     */
    public function processPayment(float $importo, string $metodo = 'carta', bool $forzaErrore = false): array
    {
        if ($forzaErrore || $importo <= 0) {
            throw new RuntimeException("Transazione di pagamento rifiutata dalla banca per l'importo: €" . number_format($importo, 2));
        }

        return [
            'transazione_id' => 'TXN-' . strtoupper(uniqid()),
            'importo'        => $importo,
            'metodo'         => $metodo,
            'esito'          => 'APPROVATO',
            'circuito'       => 'Mastercard/Visa',
            'data'           => now()->toIso8601String(),
        ];
    }
}
