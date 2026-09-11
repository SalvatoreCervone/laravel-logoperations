<?php

namespace SalvatoreCervone\LogOperations\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SalvatoreCervone\LogOperations\Models\OperationLog;
use SalvatoreCervone\LogOperations\Models\OperationSubject;
use SalvatoreCervone\LogOperations\Jobs\ProcessOperationLog;
use SalvatoreCervone\LogOperations\Services\StackTracer;
use SalvatoreCervone\LogOperations\Services\RuleEngine;
use SalvatoreCervone\LogOperations\Services\PrivacyManager;
use SalvatoreCervone\LogOperations\LogOperationsManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware per il tracciamento automatico delle operazioni HTTP.
 *
 * Funzionalità principali:
 * - Intercettazione configurabile di tutti i verbi HTTP o solo specifici
 * - Gestione transazioni pendenti (rollback/commit su DB::transactionLevel() > 0)
 * - Mascheramento ricorsivo di campi sensibili
 * - Cattura stack trace a 2 livelli (Core applicativo + Full)
 * - Misurazione della durata della richiesta (duration_ms)
 * - Esclusione di rotte e status code configurabili
 * - Isolamento: il log viene scritto DOPO la risoluzione delle transazioni
 */
class LogOperationsMiddleware
{
    protected StackTracer $stackTracer;
    protected LogOperationsManager $manager;
    protected RuleEngine $ruleEngine;
    protected PrivacyManager $privacyManager;
    protected static bool $dbListenerRegistered = false;

    public function __construct(
        StackTracer $stackTracer,
        LogOperationsManager $manager,
        RuleEngine $ruleEngine,
        ?PrivacyManager $privacyManager = null
    ) {
        $this->stackTracer = $stackTracer;
        $this->manager = $manager;
        $this->ruleEngine = $ruleEngine;
        $this->privacyManager = $privacyManager ?: app(PrivacyManager::class);
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Se il logging è disabilitato, passa immediatamente
        if (!config('logoperations.enabled', true)) {
            return $next($request);
        }

        // Protezione anti-duplicazione nel caso il middleware sia presente sia globalmente che per gruppo/rotta
        if ($request->attributes->has('_logoperations_processed')) {
            return $next($request);
        }
        $request->attributes->set('_logoperations_processed', true);

        // Attiva la richiesta nel Manager per abilitare l'auto-discovery dei soggetti
        $this->manager->activateRequest();

        // Registra il tempo di inizio per calcolare la durata
        $startTime = microtime(true);

        // Livello iniziale di transazione DB prima dell'esecuzione della richiesta
        $initialTransactionLevel = DB::transactionLevel();

        // Registra il listener per tracciare l'origine delle query DB (una sola volta per processo)
        if ($this->stackTracer->isEnabled()
            && config('logoperations.stack_trace.trace_db_callers', true)
        ) {
            $this->registerDbListener();
        }

        // Esegui la richiesta attraverso la pipeline
        $response = $next($request);

        // Calcola la durata in millisecondi
        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        $statusCode = $response->getStatusCode();
        $verbo = Str::lower($request->getMethod());

        /*
        |----------------------------------------------------------------------
        | Gestione Transazioni Pendenti
        |----------------------------------------------------------------------
        | Deve avvenire PRIMA della scrittura del log, in modo che:
        | 1. I dati non reali vengano ripuliti (rollback)
        | 2. Il log stesso non faccia parte di una transazione che verrà annullata
        |----------------------------------------------------------------------
        */
        $transactionInfo = $this->handlePendingTransactions($statusCode, $response, $initialTransactionLevel);

        /*
        |----------------------------------------------------------------------
        | Verifica se questa richiesta deve essere loggata
        |----------------------------------------------------------------------
        */
        $ruleEvaluation = null;
        if (!$this->shouldLog($request, $statusCode, $verbo, $ruleEvaluation)) {
            // Cleanup del manager per la prossima richiesta
            $this->manager->flush();
            $this->stackTracer->flushDbCallers();
            return $response;
        }

        /*
        |----------------------------------------------------------------------
        | Raccolta dati per il log
        |----------------------------------------------------------------------
        */
        try {
            $route = $request->route();

            // Ispezione parametri di rotta e rilevamento subject polimorfico
            $subject = $this->manager->getSubject();
            $routeParams = [];
            if ($route && method_exists($route, 'parameters')) {
                foreach ($route->parameters() as $key => $param) {
                    if ($param instanceof \Illuminate\Database\Eloquent\Model) {
                        if ($subject === null) {
                            $subject = $param;
                        }
                        $routeParams[$key] = [
                            'id' => $param->getKey(),
                            'model' => get_class($param),
                        ];
                    } else {
                        $routeParams[$key] = $param;
                    }
                }
            }

            $subjectId = $subject ? (string) $subject->getKey() : null;
            $subjectType = $subject ? $subject->getMorphClass() : null;

            // Parametri con mascheramento dei campi sensibili
            $parametriPost = $request->all() ? ['post' => $request->all()] : [];
            $parametriQuery = $request->query() ? ['querystring' => $request->query()] : [];
            $parametriRoute = !empty($routeParams) ? ['route' => $routeParams] : [];
            $parametriHeaders = config('logoperations.privacy.log_headers', false)
                ? ['headers' => $this->privacyManager->sanitizeHeaders($request->headers->all())]
                : [];
            $manualContext = $this->manager->getContext();
            $parametriContext = !empty($manualContext) ? ['context' => $manualContext] : [];
            $tags = $this->manager->getTags();
            $parametriTags = !empty($tags) ? ['tags' => $tags] : [];

            $parametri = array_merge(
                $parametriPost,
                $parametriQuery,
                $parametriRoute,
                $parametriHeaders,
                $parametriContext,
                $parametriTags
            );
            $parametri = !empty($parametri) ? $this->maskSensitiveFields($parametri) : null;

            // Controller e metodo
            $controllerMethod = null;
            if ($route && $route->getAction() && !empty($route->getAction()['controller'])) {
                $controllerMethod = $route->getAction()['controller'];
            }

            // Utente autenticato (polimorfico)
            $user = $request->user() ?: Auth::user();
            $userId = $user ? (string) $user->getKey() : null;
            $userType = $user ? get_class($user) : null;

            // Errore / Eccezione
            $errorMessage = $this->captureError($response, $statusCode);

            // Step e trace personalizzati dal codice applicativo
            // Se la rotta ha una regola dinamica esplicita nello Studio Rotte, rispettiamo il livello scelto ('base', 'core', 'full').
            // Se non c'è una regola esplicita (fallback statico), applichiamo la logica globale only_on_error.
            $stackLevel = ($ruleEvaluation && ($ruleEvaluation['matched_by'] ?? 'none') !== 'none')
                ? ($ruleEvaluation['stack_level'] ?? null)
                : null;

            $suppressDetailsOnSuccess = ($stackLevel === 'base') || ($stackLevel === null && $this->stackTracer->isOnlyOnError());
            $isSuccessAndSuppress = ($statusCode < 400 && !isset($response->exception) && $suppressDetailsOnSuccess);
            $customTraces = null;

            if ($this->manager->hasCustomTraces() || $this->manager->hasTags() || $this->manager->hasContext()) {
                $customTraces = [
                    'steps' => $this->manager->getSteps(),
                    'traces' => $this->manager->getTraces(),
                    'tags' => $this->manager->getTags(),
                    'context' => $this->privacyManager->maskSensitiveData($this->manager->getContext()),
                    'db_callers' => $isSuccessAndSuppress ? [] : $this->stackTracer->getDbCallers(),
                ];
            } elseif (!$isSuccessAndSuppress && !empty($this->stackTracer->getDbCallers())) {
                $customTraces = [
                    'steps' => [],
                    'traces' => [],
                    'tags' => [],
                    'context' => [],
                    'db_callers' => $this->stackTracer->getDbCallers(),
                ];
            }

            // Stack trace a 2 livelli calibrato sul livello di tracciamento
            $stackTrace = $this->captureStack($response, $statusCode, $route, $customTraces, $stackLevel);

            $clientIp = $request->ip();
            if (config('logoperations.privacy.anonymize_ip', false)) {
                $clientIp = $this->privacyManager->anonymizeIp($clientIp);
            }

            // Salvataggio del record di log
            $logData = [
                'user_id' => $userId,
                'user_type' => $userType,
                'subject_id' => $subjectId,
                'subject_type' => $subjectType,
                'rotta' => Str::limit($this->privacyManager->sanitizeUri($request->getRequestUri()), 1024, ''),
                'verbo' => $verbo,
                'controllermethod' => $controllerMethod ? Str::limit($controllerMethod, 500, '') : null,
                'codicehttp' => $statusCode,
                'client_ip' => $clientIp,
                'dataoperazione' => now(),
                'parametri' => $parametri,
                'error' => $errorMessage,
                'stack_trace' => $stackTrace,
                'custom_traces' => $customTraces,
                'nomeapplicazione' => Str::limit((string) config('logoperations.app_name', 'laravel'), 100, ''),
                'duration_ms' => $durationMs,
                'transaction_status' => $transactionInfo['action'],
                'transaction_level' => $transactionInfo['level'],
            ];

            // Se la scrittura post-risposta è disabilitata esplicitamente, persiste subito in handle
            if (!config('logoperations.write_after_response', true)) {
                $this->persistLog($logData, $request);
                $this->manager->flush();
                $this->stackTracer->flushDbCallers();
            } else {
                // Memorizza i dati per la persistenza post-risposta (Terminable Middleware)
                $request->attributes->set('_logoperations_pending', $logData);
            }

        } catch (\Throwable $e) {
            // Il logging non deve mai bloccare la risposta al client
            Log::warning('[LogOperations] Errore durante la preparazione del log: ' . $e->getMessage(), [
                'exception' => $e->getMessage(),
                'uri' => $this->privacyManager->sanitizeUri($request->getRequestUri()),
            ]);
        }

        return $response;
    }

    /**
     * Esegue la scrittura dei log dopo l'invio della risposta HTTP al client (Terminable Middleware).
     */
    public function terminate(Request $request, Response $response): void
    {
        $logData = $request->attributes->get('_logoperations_pending');
        if (!$logData) {
            return;
        }

        try {
            $this->persistLog($logData, $request);
        } finally {
            $request->attributes->remove('_logoperations_pending');
            $this->manager->flush();
            $this->stackTracer->flushDbCallers();
        }
    }

    /**
     * Persiste il log su database o lo invia alla coda asincrona con gestione fallback.
     */
    protected function persistLog(array $logData, Request $request): void
    {
        try {
            $queueConfig = config('logoperations.queue', []);
            $log = null;

            if (!empty($queueConfig['enabled'])) {
                try {
                    ProcessOperationLog::dispatch($logData);
                    // Con la coda, i soggetti devono essere inseriti nel job
                } catch (\Throwable $queueException) {
                    // Fallback immediato su salvataggio sincrono se il broker di coda è offline
                    Log::warning('[LogOperations] Fallback sincrono: dispatch coda non riuscito (' . $queueException->getMessage() . ').');
                    $log = OperationLog::create($logData);
                }
            } else {
                $log = OperationLog::create($logData);
            }

            // Bulk insert dei soggetti toccati durante la richiesta
            if ($log && $this->manager->hasTouchedModels()) {
                $this->persistTouchedSubjects($log);
            }

            // Invia notifica di allarme per rollback di transazioni pendenti se configurato
            if (!empty($logData['transaction_status']) && str_starts_with($logData['transaction_status'], 'rolled_back')) {
                try {
                    app(\SalvatoreCervone\LogOperations\Services\AlertNotificationService::class)->sendRollbackAlert($logData);
                } catch (\Throwable $alertException) {
                    // La notifica non deve mai bloccare o alterare il ciclo del log
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[LogOperations] Errore durante la persistenza del log: ' . $e->getMessage(), [
                'exception' => $e->getMessage(),
                'uri' => $this->privacyManager->sanitizeUri($request->getRequestUri()),
            ]);
        }
    }

    /**
     * Esegue una singola query INSERT bulk per tutti i modelli toccati durante la richiesta.
     * Performance: anche 100+ entità vengono inserite in < 5ms.
     */
    protected function persistTouchedSubjects(OperationLog $log): void
    {
        try {
            $touchedModels = $this->manager->getTouchedModels();
            if (empty($touchedModels)) {
                return;
            }

            $now = now();
            $rows = [];
            foreach ($touchedModels as $touched) {
                $rows[] = [
                    'log_id' => $log->id,
                    'subject_type' => $touched['subject_type'],
                    'subject_id' => $touched['subject_id'],
                    'action' => $touched['action'],
                    'created_at' => $now,
                ];
            }

            // Singola query INSERT bulk su log_operazioni_soggetti
            $table = config('logoperations.subjects_table_name', 'log_operazioni_soggetti');
            $connection = config('logoperations.database_connection');
            $db = $connection ? DB::connection($connection) : DB::connection();
            $db->table($table)->insert($rows);
        } catch (\Throwable $e) {
            Log::warning('[LogOperations] Errore durante inserimento soggetti: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Gestione Transazioni Pendenti
    |--------------------------------------------------------------------------
    */

    /**
     * Gestisce le transazioni lasciate aperte dai controller.
     *
     * Se DB::transactionLevel() > 0 dopo l'esecuzione della richiesta:
     * - Su errori (>= 400 o eccezione): rollback ciclico di tutti i livelli
     *   per eliminare dati non reali e rilasciare lock.
     * - Su successo con transazione aperta: comportamento configurabile
     *   (commit o rollback preventivo).
     *
     * Il log viene scritto DOPO questa fase per non essere revocato.
     *
     * @return array{level: int|null, action: string|null}
     */
    protected function handlePendingTransactions(int $statusCode, Response $response, int $initialLevel = 0): array
    {
        $config = config('logoperations.transactions', []);

        if (!($config['manage_unfinished'] ?? true)) {
            return ['level' => null, 'action' => null];
        }

        $level = DB::transactionLevel();

        if ($level <= $initialLevel) {
            return ['level' => null, 'action' => null];
        }

        $action = null;

        // Rileva se c'è un'eccezione nella risposta
        $hasException = isset($response->exception) && $response->exception;

        if ($statusCode >= 400 || $hasException) {
            // Errore: rollback di tutti i livelli aperti durante la richiesta
            if ($config['rollback_on_error'] ?? true) {
                while (DB::transactionLevel() > $initialLevel) {
                    DB::rollBack();
                }
                $action = 'rolled_back';

                Log::info('[LogOperations] Transazione pendente rilevata (livello ' . $level . '). '
                    . 'Eseguito rollback automatico su errore HTTP ' . $statusCode);
            }
        } else {
            // Successo ma transazione non chiusa (anomalia dello sviluppatore)
            if ($config['commit_on_success'] ?? false) {
                while (DB::transactionLevel() > $initialLevel) {
                    DB::commit();
                }
                $action = 'committed';

                Log::info('[LogOperations] Transazione pendente rilevata (livello ' . $level . '). '
                    . 'Eseguito commit automatico su successo HTTP ' . $statusCode);
            } else {
                while (DB::transactionLevel() > $initialLevel) {
                    DB::rollBack();
                }
                $action = 'rolled_back_dangling_on_success';

                Log::warning('[LogOperations] Transazione pendente rilevata (livello ' . $level . '). '
                    . 'Eseguito rollback preventivo su successo HTTP ' . $statusCode
                    . '. Verificare il controller che non chiude la transazione.');
            }
        }

        return ['level' => $level, 'action' => $action];
    }

    /*
    |--------------------------------------------------------------------------
    | Verifica se la richiesta deve essere loggata
    |--------------------------------------------------------------------------
    */

    protected function shouldLog(Request $request, int $statusCode, string $verbo, ?array &$ruleEvaluation = null): bool
    {
        $currentPath = $request->path();
        $apiPrefix = config('logoperations.api_prefix', 'api/logoperations');
        $dashboardRoute = config('logoperations.dashboard.route', 'logoperations');

        // Esclusione fondamentale anti-loop per le rotte interne del pacchetto (API e Dashboard web)
        if (Str::is([$apiPrefix . '*', 'api/log-operations*', $dashboardRoute . '*'], $currentPath)) {
            return false;
        }

        // Valutazione regole dinamiche Zero-Code
        $ruleEvaluation = $this->ruleEngine->evaluateRequest($request);

        // Verifica codici di stato esclusi da configurazione (es. 422 di default):
        // La blacklist dei codici esclusi ha sempre la precedenza assoluta sul tracciamento delle rotte.
        // Solo una sessione investigativa live su uno specifico utente (is_user_monitored) la bypassa.
        $excludedCodes = config('logoperations.excluded_status_codes', []);
        if (in_array($statusCode, $excludedCodes)) {
            if (!($ruleEvaluation['is_user_monitored'] ?? false)) {
                return false;
            }
        }

        // Se una regola dinamica (o sessione utente live) è attiva, forza il log
        if ($ruleEvaluation['should_log']) {
            return true;
        }

        // Gestione modalità selettiva: se 'selective', logga solo se la rotta ha il middleware applicato esplicitamente
        // oppure se si verifica un errore grave (status >= 500) con paracadute 'log_uncaught_errors' attivo
        $mode = config('logoperations.mode', 'all');
        $catchUncaughtError = false;

        if ($mode === 'selective') {
            $route = $request->route();
            $hasExplicitMiddleware = false;
            if ($route && method_exists($route, 'gatherMiddleware')) {
                $middlewares = $route->gatherMiddleware();
                $hasExplicitMiddleware = in_array('log.operations', $middlewares, true)
                    || in_array('logoperations', $middlewares, true)
                    || in_array(self::class, $middlewares, true)
                    || in_array('\\' . self::class, $middlewares, true);
            }

            // Paracadute "Safety Net": se la rotta non è esplicitamente monitorata ma si verifica un errore di sistema 500+
            $catchUncaughtError = ($statusCode >= 500) && config('logoperations.log_uncaught_errors', true);

            if (!$hasExplicitMiddleware && !$catchUncaughtError) {
                return false;
            }
        }

        // Verifica verbi HTTP consentiti (il safety net per crash 500 bypassa il filtro sui verbi)
        $allowedMethods = config('logoperations.allowed_methods', ['*']);
        if (!$catchUncaughtError && !in_array('*', $allowedMethods)) {
            if (!in_array(strtoupper($verbo), array_map('strtoupper', $allowedMethods))) {
                return false;
            }
        }

        // Verifica rotte escluse
        $excludedRoutes = config('logoperations.excluded_routes', []);
        foreach ($excludedRoutes as $pattern) {
            if (Str::is($pattern, $currentPath)) {
                return false;
            }
        }

        // Gestione campionamento (sampling rate) per risposte con esito positivo (< 400)
        // Gli errori (status >= 400) vengono SEMPRE tracciati al 100%
        $samplingRate = (int) config('logoperations.sampling_rate', 100);
        if ($samplingRate < 100 && $statusCode < 400) {
            if ($samplingRate <= 0 || mt_rand(1, 100) > $samplingRate) {
                return false;
            }
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Cattura Errori
    |--------------------------------------------------------------------------
    */

    protected function captureError(Response $response, int $statusCode): ?string
    {
        $exception = null;

        // Rileva l'eccezione dalla risposta (diversi metodi per compatibilità)
        if (method_exists($response, 'exception') && $response->exception()) {
            $exception = $response->exception();
        } elseif (isset($response->exception) && $response->exception) {
            $exception = $response->exception;
        }

        if ($exception) {
            return $exception->getMessage()
                . "\n\n[Stack Trace]\n"
                . $exception->getTraceAsString();
        }

        if ($statusCode >= 400) {
            $content = '';
            if (method_exists($response, 'getContent')) {
                $content = $response->getContent();
            }
            return "[Risposta di Errore HTTP " . $statusCode . "]: " . Str::limit($content, 5000, '...');
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Cattura Stack Trace a 2 Livelli
    |--------------------------------------------------------------------------
    */

    protected function captureStack(Response $response, int $statusCode, $route = null, ?array $customTraces = null, ?string $stackLevel = null): ?array
    {
        if (!$this->stackTracer->isEnabled()) {
            return null;
        }

        $level = $stackLevel ?: config('logoperations.stack_trace.default_view', 'core');

        // Se il livello impostato è 'base':
        // non memorizziamo alcuno stack su risposte normali 200 OK (massime prestazioni e zero overhead).
        // Se c'è un errore (>= 400), salviamo comunque lo stack dell'errore.
        if ($level === 'base' && $statusCode < 400 && !isset($response->exception)) {
            return null;
        }

        // Se la rotta NON ha un livello specificato esplicitamente dalla regola dello Studio,
        // applichiamo la configurazione globale only_on_error (default: true).
        // Se invece l'utente ha impostato esplicitamente 'core' o 'full' sulla rotta,
        // la sua intenzione è di tracciare lo stack anche per le chiamate 200 OK.
        if ($stackLevel === null && $this->stackTracer->isOnlyOnError() && $statusCode < 400 && !isset($response->exception)) {
            return null;
        }

        // Se c'è un'eccezione, usa il suo stack
        $exception = isset($response->exception) ? $response->exception : null;

        $frames = $exception
            ? $this->stackTracer->capture($exception)
            : $this->stackTracer->captureCurrentStack($route, $customTraces);

        // Se la rotta è impostata su 'core':
        // memorizziamo ESCLUSIVAMENTE i frame del codice Core applicativo (is_core = true),
        // eliminando decine di frame vendor e risparmiando fino al 90% di spazio su DB.
        if ($level === 'core') {
            $frames = array_values(array_filter($frames, fn($f) => !empty($f['is_core'])));
        }

        return $frames;
    }

    /*
    |--------------------------------------------------------------------------
    | Mascheramento Campi Sensibili
    |--------------------------------------------------------------------------
    */

    /**
     * Sostituisce ricorsivamente i valori dei campi sensibili con '***MASKED***'.
     */
    protected function maskSensitiveFields(array $data): array
    {
        return $this->privacyManager->maskSensitiveData($data);
    }

    /*
    |--------------------------------------------------------------------------
    | Listener per tracciamento origine delle Query DB
    |--------------------------------------------------------------------------
    */

    /**
     * Registra un listener su DB::listen() per identificare quale funzione
     * del codice applicativo ha originato ciascuna query.
     * Protetto con guardia statica per registrarsi una sola volta per processo (Octane/persistent safe).
     */
    protected function registerDbListener(): void
    {
        if (static::$dbListenerRegistered) {
            return;
        }

        DB::listen(function ($query) {
            $tracer = app(StackTracer::class);
            if ($tracer->isEnabled() && config('logoperations.stack_trace.trace_db_callers', true)) {
                $caller = $tracer->findProjectCaller();
                if ($caller) {
                    $tracer->recordDbCaller([
                        'sql' => Str::limit($query->sql, 500, '...'),
                        'time_ms' => $query->time,
                        'caller' => $caller,
                    ]);
                }
            }
        });

        static::$dbListenerRegistered = true;
    }
}
