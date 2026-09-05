<?php

namespace App\Services;

class OrderService
{
    /**
     * Calcola il totale degli articoli con eventuale sconto.
     */
    public function calcolaTotale(array $articoli, float $sconto = 0.0): float
    {
        $subtotale = 0.0;
        foreach ($articoli as $item) {
            $prezzo = (float) ($item['prezzo'] ?? 0);
            $qty = (int) ($item['quantita'] ?? 1);
            $subtotale += $prezzo * $qty;
        }

        return max(0.0, round($subtotale - $sconto, 2));
    }

    /**
     * Calcola lo sconto fedeltà associato a un utente.
     */
    public function applicaScontoFedelta(int $userId, float $subtotale): array
    {
        // Simula logica di calcolo
        $percentuale = $userId % 2 === 0 ? 10.0 : 5.0;
        $importoSconto = round($subtotale * ($percentuale / 100), 2);

        return [
            'user_id'         => $userId,
            'percentuale'     => $percentuale,
            'importo_sconto'  => $importoSconto,
            'subtotale_netto' => $subtotale - $importoSconto,
        ];
    }

    /**
     * Verifica la giacenza di magazzino.
     */
    public function verificaGiacenza(int $prodottoId, int $quantita): bool
    {
        // Simula giacenza sufficiente per ID pari, insufficiente per dispari > 100
        return !($prodottoId % 2 !== 0 && $quantita > 100);
    }
}
