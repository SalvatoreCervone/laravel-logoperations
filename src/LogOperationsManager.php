<?php

namespace SalvatoreCervone\LogOperations;

use SalvatoreCervone\LogOperations\Services\StackTracer;
use SalvatoreCervone\LogOperations\Services\PrivacyManager;

/**
 * Manager centrale per il tracciamento manuale delle operazioni.
 *
 * Permette di registrare step e checkpoint all'interno del codice
 * applicativo, che verranno poi salvati insieme al log della richiesta.
 *
 * Utilizzo tramite Facade:
 *   LogOperations::step('Verifica permessi fiscali');
 *   $result = LogOperations::trace('Calcolo giacenze', fn() => $this->calcolaGiacenze());
 */
class LogOperationsManager
{
    /**
     * Step personalizzati registrati durante la richiesta corrente.
     */
    protected array $steps = [];

    /**
     * Risultati delle funzioni tracciate durante la richiesta.
     */
    protected array $traces = [];

    /**
     * Contesto manuale applicativo arricchito durante la richiesta.
     */
    protected array $context = [];

    /**
     * Tag descrittivi associati alla richiesta corrente.
     */
    protected array $tags = [];

    /**
     * Entità target polimorfica (subject) associata alla richiesta corrente.
     */
    protected ?\Illuminate\Database\Eloquent\Model $subject = null;

    /**
     * Modelli Eloquent toccati (created/updated/deleted) durante la richiesta corrente.
     * Chiave: "App\Models\Foo::42" (deduplicazione O(1)).
     */
    protected array $touchedModels = [];

    /**
     * Flag che indica se la richiesta corrente è attivamente tracciata.
     */
    protected bool $requestActive = false;

    protected PrivacyManager $privacyManager;

    public function __construct(
        protected StackTracer $stackTracer,
        ?PrivacyManager $privacyManager = null
    ) {
        $this->privacyManager = $privacyManager ?: app(PrivacyManager::class);
    }

    /**
     * Associa manualmente un'entità target (subject) al log della richiesta corrente.
     */
    public function setSubject(?\Illuminate\Database\Eloquent\Model $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Restituisce l'entità target associata alla richiesta corrente.
     */
    public function getSubject(): ?\Illuminate\Database\Eloquent\Model
    {
        return $this->subject;
    }

    /**
     * Contrassegna la richiesta corrente come attiva (la rotta è tracciata).
     */
    public function activateRequest(): void
    {
        $this->requestActive = true;
    }

    /**
     * Verifica se la richiesta corrente è contrassegnata come attiva.
     */
    public function isRequestActive(): bool
    {
        return $this->requestActive;
    }

    /**
     * Registra un modello Eloquent toccato durante la richiesta corrente.
     *
     * Filtri applicati:
     * - Namespace applicativo (configurable, default: App\Models)
     * - Esclusione modelli pivot (Illuminate\Database\Eloquent\Relations\Pivot)
     * - Esclusione modelli interni del pacchetto (SalvatoreCervone\LogOperations)
     * - Deduplicazione per chiave "tipo::id"
     *
     * @param \Illuminate\Database\Eloquent\Model $model Il modello toccato
     * @param string $action L'azione Eloquent: 'created', 'updated', 'deleted'
     */
    public function recordTouchedModel(\Illuminate\Database\Eloquent\Model $model, string $action): void
    {
        $class = get_class($model);

        // Escludi modelli pivot
        if ($model instanceof \Illuminate\Database\Eloquent\Relations\Pivot) {
            return;
        }

        // Escludi modelli interni del pacchetto (OperationLog, OperationSubject, OperationRule)
        if (str_starts_with($class, 'SalvatoreCervone\\LogOperations\\Models\\')) {
            return;
        }

        // Verifica namespace applicativo
        $allowedNamespace = config('logoperations.models_namespace', 'App\\Models');
        if (!str_starts_with($class, $allowedNamespace)) {
            return;
        }

        $key = $model->getKey();
        if ($key === null) {
            return;
        }

        $deduplicationKey = $class . '::' . $key;

        // Registra solo la prima azione per evitare duplicati
        if (isset($this->touchedModels[$deduplicationKey])) {
            return;
        }

        $this->touchedModels[$deduplicationKey] = [
            'subject_type' => $model->getMorphClass(),
            'subject_id' => (string) $key,
            'action' => $action,
        ];

        // Auto-assegna il primo modello toccato come soggetto primario
        if ($this->subject === null) {
            $this->subject = $model;
        }
    }

    /**
     * Restituisce tutti i modelli toccati durante la richiesta corrente.
     *
     * @return array Array di ['subject_type', 'subject_id', 'action']
     */
    public function getTouchedModels(): array
    {
        return array_values($this->touchedModels);
    }

    /**
     * Verifica se ci sono modelli toccati durante la richiesta.
     */
    public function hasTouchedModels(): bool
    {
        return !empty($this->touchedModels);
    }

    /**
     * Registra un checkpoint/step con un'etichetta descrittiva.
     * Lo stack di chiamata viene catturato automaticamente al momento
     * dell'invocazione per identificare esattamente da dove è stato chiamato.
     */
    public function step(string $label, array $context = []): void
    {
        $caller = $this->findCaller();

        $this->steps[] = [
            'label' => $label,
            'context' => $context,
            'file' => $caller['file'] ?? null,
            'line' => $caller['line'] ?? null,
            'class' => $caller['class'] ?? null,
            'function' => $caller['function'] ?? null,
            'timestamp' => microtime(true),
        ];
    }

    /**
     * Esegue e traccia un metodo applicativo o callable, misurandone la durata
     * e registrando classe, metodo reale, file e riga esatti per lo stack trace.
     *
     * Firme supportate:
     * 1. trace([$object, 'methodName'], callable $callback, ?string $label = null)
     * 2. trace('Class@methodName', callable $callback, ?string $label = null)
     * 3. trace('Class::methodName', callable $callback, ?string $label = null)
     * 4. trace('label', callable $callback)
     *
     * @param mixed $target Callable, array [object, 'method'], string 'Class@method' o string label
     * @param callable $callback Funzione da eseguire
     * @param string|null $label Etichetta descrittiva opzionale
     * @return mixed Risultato del callback
     */
    public function trace(mixed $target, callable $callback, ?string $label = null): mixed
    {
        $caller = $this->findCaller();
        $targetClass = $caller['class'] ?? null;
        $targetFunction = $caller['function'] ?? null;
        $targetFile = $caller['file'] ?? null;
        $targetLine = $caller['line'] ?? null;
        $targetLabel = $label;

        // Caso 1: Array [$object, 'methodName'] o [Class::class, 'methodName']
        if (is_array($target) && count($target) === 2 && is_string($target[1])) {
            $cls = is_object($target[0]) ? get_class($target[0]) : (string) $target[0];
            $mth = $target[1];
            $targetClass = $cls;
            $targetFunction = $mth;
            $targetLabel = $label ?: $mth;

            if (class_exists($cls) && method_exists($cls, $mth)) {
                try {
                    $ref = new \ReflectionMethod($cls, $mth);
                    $targetFile = $ref->getFileName();
                    $targetLine = $ref->getStartLine();
                } catch (\Throwable $e) {}
            }
        }
        // Caso 2: Stringa "Class@method" o "Class::method"
        elseif (is_string($target) && (str_contains($target, '@') || str_contains($target, '::'))) {
            $delimiter = str_contains($target, '@') ? '@' : '::';
            [$cls, $mth] = explode($delimiter, $target, 2);
            $targetClass = $cls;
            $targetFunction = $mth;
            $targetLabel = $label ?: $mth;

            if (class_exists($cls) && method_exists($cls, $mth)) {
                try {
                    $ref = new \ReflectionMethod($cls, $mth);
                    $targetFile = $ref->getFileName();
                    $targetLine = $ref->getStartLine();
                } catch (\Throwable $e) {}
            }
        }
        // Caso 3: Stringa semplice (label o nome metodo)
        elseif (is_string($target)) {
            $targetLabel = $target;
            // Se la stringa è un identificatore PHP valido senza spazi e il chiamante ha questo metodo
            if (preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $target)) {
                if ($targetClass && method_exists($targetClass, $target)) {
                    $targetFunction = $target;
                    try {
                        $ref = new \ReflectionMethod($targetClass, $target);
                        $targetFile = $ref->getFileName();
                        $targetLine = $ref->getStartLine();
                    } catch (\Throwable $e) {}
                }
            }
        }

        $start = microtime(true);
        $error = null;

        try {
            $result = $callback();
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            throw $e;
        } finally {
            $this->traces[] = [
                'label' => $targetLabel ?: $targetFunction,
                'file' => $targetFile,
                'line' => $targetLine,
                'class' => $targetClass,
                'function' => $targetFunction,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                'error' => $error,
                'timestamp' => $start,
            ];
        }

        return $result;
    }

    /**
     * Identifica il primo frame dello stack che appartiene al codice chiamante,
     * saltando la Facade di Laravel e le classi interne del package.
     */
    protected function findCaller(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $callSite = null;

        for ($i = 0; $i < count($trace); $i++) {
            $class = $trace[$i]['class'] ?? '';
            if (empty($class) 
                || str_starts_with($class, 'Illuminate\\Support\\Facades')
                || str_starts_with($class, 'SalvatoreCervone\\LogOperations')
            ) {
                if (!empty($trace[$i]['file'])) {
                    $callSite = [
                        'file' => $trace[$i]['file'],
                        'line' => $trace[$i]['line'] ?? null,
                    ];
                }
                continue;
            }

            return [
                'file' => $callSite['file'] ?? ($trace[$i]['file'] ?? null),
                'line' => $callSite['line'] ?? ($trace[$i]['line'] ?? null),
                'class' => $trace[$i]['class'] ?? null,
                'function' => $trace[$i]['function'] ?? null,
            ];
        }

        return $trace[1] ?? [];
    }

    /**
     * Restituisce tutti gli step registrati durante questa richiesta.
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * Restituisce tutti i trace registrati durante questa richiesta.
     */
    public function getTraces(): array
    {
        return $this->traces;
    }

    /**
     * Verifica se ci sono step o trace registrati.
     */
    public function hasCustomTraces(): bool
    {
        return !empty($this->steps) || !empty($this->traces);
    }

    /**
     * Restituisce tutti i custom traces raggruppati (steps e traces).
     */
    public function getCustomTraces(): array
    {
        return [
            'steps' => $this->steps,
            'traces' => $this->traces,
        ];
    }

    /**
     * Arricchisce il contesto personalizzato della richiesta corrente.
     *
     * @param array $context Array associativo di dati di contesto
     * @return self
     */
    public function withContext(array $context): self
    {
        $this->context = array_merge($this->context, $context);
        return $this;
    }

    /**
     * Aggiunge un singolo elemento al contesto della richiesta corrente.
     *
     * @param string $key Chiave del dato di contesto
     * @param mixed $value Valore associato
     * @return self
     */
    public function addContext(string $key, mixed $value): self
    {
        $this->context[$key] = $value;
        return $this;
    }

    /**
     * Restituisce l'array del contesto personalizzato registrato.
     *
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Verifica se è presente un contesto personalizzato.
     *
     * @return bool
     */
    public function hasContext(): bool
    {
        return !empty($this->context);
    }

    /**
     * Assegna uno o più tag alla richiesta corrente.
     *
     * @param string|array ...$tags Uno o più tag o array di tag
     * @return self
     */
    public function tag(string|array ...$tags): self
    {
        foreach ($tags as $item) {
            if (is_array($item)) {
                foreach ($item as $t) {
                    if (is_string($t) && $t !== '' && !in_array($t, $this->tags, true)) {
                        $this->tags[] = $t;
                    }
                }
            } elseif (is_string($item) && $item !== '' && !in_array($item, $this->tags, true)) {
                $this->tags[] = $item;
            }
        }
        return $this;
    }

    /**
     * Restituisce i tag associati alla richiesta corrente.
     *
     * @return array
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * Verifica se sono presenti tag associati.
     *
     * @return bool
     */
    public function hasTags(): bool
    {
        return !empty($this->tags);
    }

    /**
     * Resetta gli step, i trace, il contesto e i tag per la prossima richiesta.
     */
    public function flush(): void
    {
        $this->steps = [];
        $this->traces = [];
        $this->context = [];
        $this->tags = [];
        $this->subject = null;
        $this->touchedModels = [];
        $this->requestActive = false;
    }

    /**
     * Sanitizza una URI mascherando i parametri sensibili.
     */
    public function sanitizeUri(string $uri, ?array $fields = null): string
    {
        return $this->privacyManager->sanitizeUri($uri, $fields);
    }

    /**
     * Accesso diretto al StackTracer per operazioni avanzate.
     */
    public function getStackTracer(): StackTracer
    {
        return $this->stackTracer;
    }

    /**
     * Accesso al PrivacyManager per operazioni di conformità GDPR e anonimizzazione.
     */
    public function getPrivacyManager(): PrivacyManager
    {
        return $this->privacyManager;
    }

    /**
     * Esegue il Diritto all'Oblio (GDPR Art. 17).
     *
     * @param int|string $userId Identificativo utente
     * @param string|null $userType Classe modello utente (es. 'App\Models\User')
     * @param bool $anonymize True per ripulire i dati PII ma preservare metadati tecnici, False per cancellare fisicamente
     * @return int Numero di record coinvolti
     */
    public function forgetUser(int|string $userId, ?string $userType = null, bool $anonymize = false): int
    {
        return $this->privacyManager->forgetUser($userId, $userType, $anonymize);
    }

    /**
     * Anonimizza un indirizzo IP secondo le impostazioni di privacy.
     */
    public function anonymizeIp(?string $ip, ?string $mask = null): ?string
    {
        return $this->privacyManager->anonymizeIp($ip, $mask);
    }
}
