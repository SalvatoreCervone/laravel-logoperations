<?php

namespace SalvatoreCervone\LogOperations\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Servizio per il monitoraggio dei fallimenti della coda asincrona
 * e l'invio di notifiche email di emergenza al superamento della soglia.
 */
class QueueAlertService
{
    public const CACHE_FAILURES_KEY = 'logoperations_queue_consecutive_failures';
    public const CACHE_ALERT_SENT_KEY = 'logoperations_queue_alert_sent_lock';

    /**
     * Registra un fallimento di un job di log ed invia un alert se la soglia è superata.
     */
    public function recordJobFailure(array $logData, ?\Throwable $exception = null): void
    {
        $failures = (int) Cache::increment(self::CACHE_FAILURES_KEY);

        Log::critical('[LogOperations] Job di salvataggio log fallito (conteggio fallimenti: ' . $failures . ')', [
            'exception' => $exception?->getMessage(),
            'uri'       => $logData['rotta'] ?? 'N/D',
            'verbo'     => $logData['verbo'] ?? 'N/D',
        ]);

        $threshold = (int) config('logoperations.queue.failed_jobs_threshold', 5);
        $alertEmail = config('logoperations.queue.alert_email');

        if ($failures >= $threshold && !empty($alertEmail)) {
            $this->sendEmergencyAlert($failures, $logData, $exception, $alertEmail);
        }
    }

    /**
     * Invia una mail di allarme per mancata persistenza dei log.
     * Include rate-limiting per non saturare la casella di posta (max 1 mail ogni 30 min).
     */
    public function sendEmergencyAlert(int $failureCount, array $lastLogData, ?\Throwable $exception, string $email): void
    {
        if (Cache::has(self::CACHE_ALERT_SENT_KEY)) {
            return;
        }

        // Blocca ulteriori email per 30 minuti
        Cache::put(self::CACHE_ALERT_SENT_KEY, true, 1800);

        $subject = '🚨 [ALLARME] LogOperations: ' . $failureCount . ' fallimenti nella coda di salvataggio log';

        $body = "Attenzione,\n\n"
            . "Il sistema di salvataggio asincrono dei log applicativi di LogOperations ha registrato "
            . $failureCount . " fallimenti consecutivi.\n\n"
            . "La persistenza della Storyboard e dell'audit trail è attualmente a rischio.\n\n"
            . "Dettagli Ultimo Errore:\n"
            . "--------------------------------------------------\n"
            . "Data: " . now()->toDateTimeString() . "\n"
            . "Rotta: " . ($lastLogData['rotta'] ?? 'N/D') . "\n"
            . "Verbo: " . strtoupper($lastLogData['verbo'] ?? 'N/D') . "\n"
            . "Eccezione: " . ($exception ? $exception->getMessage() : 'N/D') . "\n\n"
            . "Azioni consigliate:\n"
            . "1. Verificare lo stato del worker di coda (es. supervisor, Redis, database).\n"
            . "2. I payload non salvati sono stati archiviati in: storage/logs/logoperations-emergency.log\n"
            . "3. Verificare i job nella tabella failed_jobs.\n\n"
            . "-- LogOperations Emergency Monitor";

        try {
            Mail::raw($body, function ($message) use ($email, $subject) {
                $message->to($email)
                    ->subject($subject);
            });
            Log::info('[LogOperations] Email di emergenza inviata con successo a ' . $email);
        } catch (\Throwable $e) {
            Log::emergency('[LogOperations] Impossibile inviare email di alert: ' . $e->getMessage());
        }
    }

    /**
     * Restituisce il conteggio attuale dei fallimenti registrati.
     */
    public function getFailureCount(): int
    {
        return (int) Cache::get(self::CACHE_FAILURES_KEY, 0);
    }

    /**
     * Resetta il contatore dei fallimenti.
     */
    public function resetFailureCount(): void
    {
        Cache::forget(self::CACHE_FAILURES_KEY);
        Cache::forget(self::CACHE_ALERT_SENT_KEY);
    }
}
