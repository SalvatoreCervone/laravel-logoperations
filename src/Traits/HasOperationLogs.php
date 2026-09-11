<?php

namespace SalvatoreCervone\LogOperations\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;
use SalvatoreCervone\LogOperations\LogOperationsManager;
use SalvatoreCervone\LogOperations\Models\OperationLog;

/**
 * Trait per i modelli Eloquent che supportano la cronologia eventi (Storyboard & Audit Trail).
 *
 * Utilizzo nei modelli di business (es. Order, Invoice, Customer, Ticket):
 *
 *   use SalvatoreCervone\LogOperations\Traits\HasOperationLogs;
 *
 *   class Order extends Model
 *   {
 *       use HasOperationLogs;
 *   }
 *
 * Funzionalità:
 *   $order->operationLogs;                       // Relazione morphMany
 *   $order->storyboard();                        // Timeline cronologica completa
 *   $order->logStep('Pagamento confermato', []); // Checkpoint applicativo manuale
 */
trait HasOperationLogs
{
    /**
     * Boot del trait: associa automaticamente l'istanza creata come soggetto
     * della richiesta HTTP corrente (utile per le rotte POST /store senza route model binding).
     */
    public static function bootHasOperationLogs(): void
    {
        static::created(function ($model) {
            if (app()->bound(LogOperationsManager::class)) {
                try {
                    $manager = app()->make(LogOperationsManager::class);
                    if ($manager->getSubject() === null) {
                        $manager->setSubject($model);
                    }
                } catch (\Throwable $e) {
                }
            }
        });
    }

    /**
     * Relazioni genitore/padre a cui propagare la cronologia degli eventi di questo modello.
     * Può essere dichiarato nel modello come:
     *   protected array $logParents = ['anagrafica', 'ufficio'];
     * oppure sovrascrivendo questo metodo:
     *   public function getLogParents(): array { return ['anagrafica']; }
     *
     * @return array<string>
     */
    public function getLogParents(): array
    {
        return property_exists($this, 'logParents') ? (array) $this->logParents : [];
    }

    /**
     * Relazione polimorfica a tutti i log delle operazioni associati a questo modello.
     */
    public function operationLogs(): MorphMany
    {
        return $this->morphMany(OperationLog::class, 'subject');
    }

    /**
     * Restituisce la cronologia temporale ordinata di tutti gli eventi che hanno interessato questo modello.
     *
     * @param int|null $limit Numero massimo di eventi da restituire (null per tutti)
     * @param string $order Ordinamento temporale: 'asc' (cronologico, default) o 'desc' (dal più recente)
     * @return Collection
     */
    public function storyboard(?int $limit = null, string $order = 'asc'): Collection
    {
        $dir = strtolower($order) === 'desc' ? 'desc' : 'asc';
        $query = OperationLog::forSubject($this)
            ->with(['user', 'subjects'])
            ->orderBy('dataoperazione', $dir)
            ->orderBy('id', $dir);

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Registra un checkpoint applicativo o una nota operativa direttamente nella storyboard di questo record.
     * Funziona sia durante richieste HTTP, sia all'interno di webhook, job asincroni o comandi Artisan.
     *
     * @param string $description Descrizione sintetica dell'evento/checkpoint
     * @param array $payload Dati contestuali o parametri associati
     * @return OperationLog
     */
    public function logStep(string $description, array $payload = []): OperationLog
    {
        $user = Auth::user();
        $userId = $user ? (string) $user->getKey() : null;
        $userType = $user ? $user->getMorphClass() : null;

        // Se attivo nel container, sincronizza lo step e il subject anche nel manager della richiesta HTTP corrente
        if (app()->bound(LogOperationsManager::class)) {
            try {
                $manager = app()->make(LogOperationsManager::class);
                $manager->setSubject($this);
                $manager->step($description, $payload);
            } catch (\Throwable $e) {
                // Procedi comunque con il salvataggio persistente
            }
        }

        $stepItem = [
            'label' => $description,
            'context' => $payload,
            'timestamp' => microtime(true),
        ];

        return OperationLog::create([
            'user_id' => $userId,
            'user_type' => $userType,
            'subject_id' => (string) $this->getKey(),
            'subject_type' => $this->getMorphClass(),
            'rotta' => 'storyboard::checkpoint',
            'verbo' => 'STEP',
            'controllermethod' => static::class . '@logStep',
            'codicehttp' => 200,
            'client_ip' => request()?->ip(),
            'dataoperazione' => now(),
            'parametri' => !empty($payload) ? $payload : null,
            'custom_traces' => [
                'steps' => [$stepItem],
                'traces' => [],
                'db_callers' => [],
            ],
            'nomeapplicazione' => config('logoperations.app_name', 'laravel'),
            'duration_ms' => 0,
        ]);
    }
}
