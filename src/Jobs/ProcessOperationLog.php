<?php

namespace SalvatoreCervone\LogOperations\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use SalvatoreCervone\LogOperations\Models\OperationLog;
use SalvatoreCervone\LogOperations\Services\QueueAlertService;

/**
 * Job asincrono per la persistenza dei log delle operazioni.
 * Include gestione ritentativi, backoff, scrittura su storage di emergenza
 * e notifica al servizio di allerta al superamento della soglia.
 */
class ProcessOperationLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    public array $logData;
    public array $touchedSubjects;

    public function __construct(array $logData, array $touchedSubjects = [])
    {
        $this->logData = $logData;
        $this->touchedSubjects = $touchedSubjects;
        $this->tries = (int) config('logoperations.queue.tries', 3);
        $this->backoff = config('logoperations.queue.backoff', [10, 30, 60]);

        $queueName = config('logoperations.queue.queue');
        if ($queueName) {
            $this->onQueue($queueName);
        }

        $connection = config('logoperations.queue.connection');
        if ($connection) {
            $this->onConnection($connection);
        }
    }

    /**
     * Esecuzione del Job: salvataggio su database del log e dei soggetti correlati.
     */
    public function handle(): void
    {
        $log = OperationLog::create($this->logData);

        if ($log && !empty($this->touchedSubjects)) {
            try {
                $table = config('logoperations.subjects_table_name', 'log_operazioni_soggetti');
                $connection = config('logoperations.database_connection');
                $db = $connection ? \Illuminate\Support\Facades\DB::connection($connection) : \Illuminate\Support\Facades\DB::connection();

                $now = now();
                $rows = [];
                foreach ($this->touchedSubjects as $touched) {
                    $rows[] = [
                        'log_id' => $log->id,
                        'subject_type' => $touched['subject_type'],
                        'subject_id' => $touched['subject_id'],
                        'action' => $touched['action'],
                        'created_at' => $now,
                    ];
                }

                if (!empty($rows)) {
                    $db->table($table)->insert($rows);
                }
            } catch (\Throwable $e) {
                Log::warning('[LogOperations] Errore inserimento soggetti in job di coda: ' . $e->getMessage());
            }
        }

        // Reset del contatore fallimenti in caso di successo
        try {
            app(QueueAlertService::class)->resetFailureCount();
        } catch (\Throwable $e) {
            // Ignora errori minori sul reset cache
        }
    }

    /**
     * Gestione del fallimento definitivo del Job dopo tutti i tentativi esauriti.
     */
    public function failed(?\Throwable $exception): void
    {
        // 1. Scrittura del payload nel file di emergenza
        $this->writeEmergencyFallback($exception);

        // 2. Registrazione del fallimento e controllo soglia alert email
        try {
            app(QueueAlertService::class)->recordJobFailure($this->logData, $exception);
        } catch (\Throwable $e) {
            Log::emergency('[LogOperations] Errore durante la registrazione del fallimento job: ' . $e->getMessage());
        }
    }

    /**
     * Scrive il record non salvato in un file di emergenza su disco.
     */
    protected function writeEmergencyFallback(?\Throwable $exception): void
    {
        try {
            $logDir = storage_path('logs');
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }

            $storagePath = $logDir . '/logoperations-emergency.log';
            $entry = json_encode([
                'timestamp' => now()->toIso8601String(),
                'exception' => $exception ? $exception->getMessage() : 'Nessun messaggio',
                'log_data'  => $this->logData,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

            file_put_contents($storagePath, $entry, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            Log::emergency('[LogOperations] Impossibile scrivere nel file di emergenza: ' . $e->getMessage());
        }
    }
}
