<?php

namespace SalvatoreCervone\LogOperations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Database\Eloquent\Relations\Relation;
use SalvatoreCervone\LogOperations\Models\OperationLog;
use SalvatoreCervone\LogOperations\Models\OperationSubject;
use SalvatoreCervone\LogOperations\Services\LogExportService;
use SalvatoreCervone\LogOperations\Http\Controllers\Concerns\AuthorizesLogOperations;

/**
 * Controller REST per l'interrogazione dei log operazioni.
 *
 * Supporta:
 * - Query a gruppi logici base64/JSON (retrocompatibilità con il vecchio frontend)
 * - Parametri GET diretti per integrazione immediata
 * - Ricerca polimorfica sicura sull'utente
 * - Endpoint metadati: codici HTTP, verbi, applicazioni, statistiche
 */
class LogOperationsController extends Controller
{
    use AuthorizesLogOperations;

    /**
     * Cache in memoria delle colonne esistenti per tabella per azzerare query Schema::hasColumn.
     */
    protected static array $userColumnsCache = [];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->authorizeAccess($request);
            return $next($request);
        });
    }

    /**
     * Lista paginata dei log con filtri combinati.
     *
     * GET /api/log-operations
     * Parametri:
     *   g             = base64(json) gruppi di ricerca (retrocompatibilità)
     *   user          = ricerca testuale su utente (nome, cognome, email)
     *   verb          = verbo HTTP (GET, POST, PUT, etc.)
     *   status_codes  = array di codici HTTP
     *   date_from     = data di inizio range (ISO 8601 o dd/mm/yyyy)
     *   date_to       = data di fine range
     *   ip            = indirizzo IP (ricerca parziale)
     *   controller    = controller/metodo (ricerca parziale)
     *   app           = nome applicazione
     *   text          = ricerca testuale libera (rotta, parametri, errore)
     *   has_error     = 1 per filtrare solo errori
     *   has_unfinished_transaction = 1 per transazioni pendenti
     *   per_page      = elementi per pagina (default 20, max 100)
     */
    /**
     * Costruisce la query filtrata comune a index ed export.
     */
    protected function buildFilteredQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $tableName = config('logoperations.table_name', 'log_operazioni');

        $query = OperationLog::query()
            ->select([
                $tableName . '.id',
                $tableName . '.user_id',
                $tableName . '.user_type',
                $tableName . '.rotta',
                $tableName . '.verbo',
                $tableName . '.controllermethod',
                $tableName . '.codicehttp',
                $tableName . '.client_ip',
                $tableName . '.dataoperazione',
                $tableName . '.parametri',
                $tableName . '.error',
                $tableName . '.stack_trace',
                $tableName . '.custom_traces',
                $tableName . '.nomeapplicazione',
                $tableName . '.duration_ms',
                $tableName . '.transaction_status',
                $tableName . '.transaction_level',
                $tableName . '.subject_type',
            ]);

        // Ordinamento: default per dataoperazione DESC, id DESC (dal più recente al più vecchio)
        $orderColumn = (string) $request->input('order_by', $request->input('sort', 'dataoperazione'));
        $orderDirection = strtolower((string) $request->input('order_direction', $request->input('direction', 'desc'))) === 'asc' ? 'asc' : 'desc';

        $allowedColumns = [
            'id', 'dataoperazione', 'duration_ms', 'codicehttp', 'verbo', 'rotta', 'controllermethod', 'client_ip',
        ];

        if (!in_array($orderColumn, $allowedColumns, true)) {
            $orderColumn = 'dataoperazione';
        }

        $query->orderBy($tableName . '.' . $orderColumn, $orderDirection);
        if ($orderColumn !== 'id') {
            $query->orderBy($tableName . '.id', $orderDirection);
        }

        /*
        |----------------------------------------------------------------------
        | Modalità 1: Gruppi di Ricerca (retrocompatibilità con il vecchio Vue)
        |----------------------------------------------------------------------
        */
        if ($request->filled('g')) {
            $this->applyGroupFilters($query, $request->g, $tableName);
        }

        /*
        |----------------------------------------------------------------------
        | Modalità 2: Parametri GET Diretti
        |----------------------------------------------------------------------
        */
        $this->applyDirectFilters($query, $request, $tableName);

        return $query;
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->buildFilteredQuery($request);

        // Paginazione server-side deterministica
        $defaultPerPage = (int) config('logoperations.dashboard.per_page', 20);
        $requestedPerPage = (int) $request->input('per_page', $defaultPerPage);
        $perPage = max(1, min($requestedPerPage, 100));
        $logs = $query->paginate($perPage)->withQueryString();

        // Eager loading polimorfico per prevenire query N+1 nella trasformazione
        try {
            $userTypes = $logs->getCollection()->pluck('user_type')->filter()->unique()->all();
            $hasValidTypes = false;
            foreach ($userTypes as $type) {
                $class = Relation::getMorphedModel($type) ?? $type;
                if (class_exists($class)) {
                    $hasValidTypes = true;
                    break;
                }
            }
            if ($hasValidTypes) {
                $logs->getCollection()->load('user');
            }
        } catch (\Throwable $e) {
            // Fallback trasparente al lazy loading se il caricamento massivo riscontra anomalie
        }

        // Arricchisci i risultati con i dati dell'utente polimorfico
        $logs->getCollection()->transform(function ($log) {
            return $this->enrichWithUserData($log);
        });

        return response()->json($logs);
    }

    /**
     * Esporta i log filtrati in formato streaming CSV o JSON con consumo O(1) di memoria.
     *
     * GET /api/log-operations/export
     * Parametri: format=csv|json e tutti i filtri di ricerca supportati da index.
     */
    public function export(Request $request, LogExportService $exportService): StreamedResponse
    {
        $query = $this->buildFilteredQuery($request);
        $format = strtolower($request->input('format', 'csv'));
        $timestamp = now()->format('Y-m-d_His');

        if ($format === 'json') {
            $filename = "logoperations_export_{$timestamp}.json";
            return response()->streamDownload(function () use ($query, $exportService) {
                $stream = fopen('php://output', 'w');
                $exportService->exportJson($query, $stream);
                fclose($stream);
            }, $filename, [
                'Content-Type' => 'application/json',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
            ]);
        }

        $filename = "logoperations_export_{$timestamp}.csv";
        return response()->streamDownload(function () use ($query, $exportService) {
            $stream = fopen('php://output', 'w');
            $exportService->exportCsv($query, $stream);
            fclose($stream);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Dettaglio completo di un singolo log.
     *
     * GET /api/log-operations/{id}
     */
    public function show(int $id): JsonResponse
    {
        $log = OperationLog::with('subjects')->findOrFail($id);
        $enriched = $this->enrichWithUserData($log);

        // Modelli toccati raggruppati per classe
        $touchedModels = [];
        if ($log->subjects->isNotEmpty()) {
            $touchedModels = $log->subjects
                ->groupBy('subject_type')
                ->map(function ($items, $type) {
                    return [
                        'type' => $type,
                        'label' => class_basename($type),
                        'count' => $items->count(),
                        'items' => $items->map(fn ($s) => [
                            'id' => $s->subject_id,
                            'action' => $s->action,
                        ])->values()->all(),
                    ];
                })
                ->values()
                ->all();
        }

        return response()->json([
            'data' => $enriched,
            'core_stack' => $log->core_stack,
            'full_stack' => $log->full_stack,
            'touched_models' => $touchedModels,
        ]);
    }

    /**
     * Timeline completa di vita del record target (Storyboard & Audit Trail).
     *
     * GET /api/log-operations/storyboard?subject_type=...&subject_id=...
     * oppure
     * GET /api/log-operations/storyboard/{type}/{id}
     */
    public function storyboard(Request $request): JsonResponse
    {
        $request->validate([
            'subject_type' => 'required|string',
            'subject_id' => 'required',
        ]);

        $subjectType = $request->input('subject_type');
        $subjectId = (string) $request->input('subject_id');
        $order = strtolower($request->input('order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $limit = min((int) $request->input('limit', 200), 500);

        $query = OperationLog::query()
            ->forSubject($subjectType, $subjectId);

        // Calcolo KPI riassuntivi sull'intera storia dell'entità con una singola query aggregata ANSI SQL (-83% query)
        $kpisRaw = (clone $query)->selectRaw("
            COUNT(*) as total_events,
            SUM(CASE WHEN codicehttp >= 400 THEN 1 ELSE 0 END) as total_errors,
            SUM(CASE WHEN transaction_status IS NOT NULL THEN 1 ELSE 0 END) as total_rollbacks,
            SUM(CASE WHEN UPPER(verbo) = 'STEP' THEN 1 ELSE 0 END) as total_checkpoints,
            MIN(dataoperazione) as first_activity,
            MAX(dataoperazione) as last_activity
        ")->first();

        $totalEvents = (int) ($kpisRaw->total_events ?? 0);
        $totalErrors = (int) ($kpisRaw->total_errors ?? 0);
        $totalRollbacks = (int) ($kpisRaw->total_rollbacks ?? 0);
        $totalCheckpoints = (int) ($kpisRaw->total_checkpoints ?? 0);
        $firstActivity = $kpisRaw->first_activity ?? null;
        $lastActivity = $kpisRaw->last_activity ?? null;

        // Filtri opzionali sulla timeline
        if ($request->boolean('has_error')) {
            $query->where('codicehttp', '>=', 400);
        }

        if ($request->filled('event_type')) {
            $type = $request->input('event_type');
            if ($type === 'checkpoint') {
                $query->where('verbo', 'STEP');
            } elseif ($type === 'create') {
                $query->where('verbo', 'post')->where('codicehttp', '<', 400);
            } elseif ($type === 'update') {
                $query->whereIn('verbo', ['put', 'patch'])->where('codicehttp', '<', 400);
            } elseif ($type === 'delete') {
                $query->where('verbo', 'delete')->where('codicehttp', '<', 400);
            } elseif ($type === 'error') {
                $query->where('codicehttp', '>=', 400);
            }
        }

        if ($request->filled('search')) {
            $query->textSearch($request->input('search'));
        }

        $logs = $query->orderBy('dataoperazione', $order)
            ->orderBy('id', $order)
            ->limit($limit)
            ->with('subjects')
            ->get();

        try {
            $logs->load('user');
        } catch (\Throwable) {
            // Se la connessione o tabella utente non è accessibile, prosegui senza bloccare lo Storyboard
        }

        $events = $logs->map(function ($log) use ($subjectType, $subjectId) {
            $enriched = $this->enrichWithUserData($log);
            $classification = $this->classifyEvent($log);

            // Includi i modelli toccati dalla tabella relazionale
            $touchedModels = [];
            $myAction = null;
            if ($log->relationLoaded('subjects') && $log->subjects->isNotEmpty()) {
                $mySubject = $log->subjects->first(fn ($s) => $s->subject_type === $subjectType && (string) $s->subject_id === (string) $subjectId);
                $myAction = $mySubject?->action;

                $touchedModels = $log->subjects
                    ->groupBy('subject_type')
                    ->map(function ($items, $type) {
                        return [
                            'type' => $type,
                            'label' => class_basename($type),
                            'count' => $items->count(),
                            'items' => $items->map(fn ($s) => [
                                'id' => $s->subject_id,
                                'action' => $s->action,
                            ])->values()->all(),
                        ];
                    })
                    ->values()
                    ->all();
            }

            $isPrimary = ($log->subject_type === $subjectType && (string) $log->subject_id === (string) $subjectId);
            if (!$myAction) {
                $myAction = $isPrimary ? match (strtolower($log->verbo)) {
                    'post' => 'created',
                    'put', 'patch' => 'updated',
                    'delete' => 'deleted',
                    default => 'accessed',
                } : 'touched';
            }

            $actionLabels = [
                'created' => 'Creazione',
                'updated' => 'Modifica',
                'deleted' => 'Eliminazione',
                'accessed' => 'Accesso',
                'touched' => 'Coinvolto',
            ];

            $date = $log->dataoperazione ? Carbon::parse($log->dataoperazione) : null;

            return array_merge($enriched, [
                'route' => $log->rotta,
                'verb' => strtoupper($log->verbo),
                'status_code' => $log->codicehttp,
                'ip_address' => $log->client_ip,
                'time_iso' => $date?->toIso8601String(),
                'time_human' => $date?->diffForHumans(),
                'time_formatted' => $date?->format('d/m/Y H:i:s'),
                'is_primary_subject' => $isPrimary,
                'subject_action' => $myAction,
                'subject_action_label' => $actionLabels[$myAction] ?? ucfirst($myAction),
                'primary_subject_label' => ($log->subject_type && $log->subject_id) ? class_basename($log->subject_type) . ' #' . $log->subject_id : null,
                'classification' => $classification,
                'core_stack' => $log->core_stack,
                'full_stack' => $log->full_stack,
                'touched_models' => $touchedModels,
            ]);
        });

        return response()->json([
            'subject' => [
                'type' => $subjectType,
                'id' => $subjectId,
                'label' => class_basename($subjectType) . ' #' . $subjectId,
            ],
            'kpis' => [
                'total_events' => $totalEvents,
                'total_errors' => $totalErrors,
                'total_rollbacks' => $totalRollbacks,
                'total_checkpoints' => $totalCheckpoints,
                'first_activity' => $firstActivity,
                'last_activity' => $lastActivity,
            ],
            'events' => $events,
        ]);
    }

    /**
     * Alias per recupero Storyboard con parametri di rotta diretti.
     *
     * GET /api/log-operations/storyboard/{type}/{id}
     */
    public function storyboardByRoute(string $type, string|int $id, Request $request): JsonResponse
    {
        $request->merge([
            'subject_type' => urldecode($type),
            'subject_id' => $id,
        ]);

        return $this->storyboard($request);
    }

    /**
     * Restituisce la lista degli ultimi soggetti polimorfici (subject) tracciati nei log.
     *
     * GET /api/log-operations/storyboard/subjects
     */
    public function subjects(): JsonResponse
    {
        // Soggetti primari dalla tabella principale
        $primarySubjects = OperationLog::query()
            ->whereNotNull('subject_type')
            ->whereNotNull('subject_id')
            ->select(['subject_type', 'subject_id'])
            ->distinct()
            ->limit(100)
            ->get()
            ->map(fn ($item) => $item->subject_type . '::' . $item->subject_id);

        // Soggetti dalla tabella relazionale
        $relationalSubjects = OperationSubject::query()
            ->select(['subject_type', 'subject_id'])
            ->distinct()
            ->limit(100)
            ->get()
            ->map(fn ($item) => $item->subject_type . '::' . $item->subject_id);

        // Unione e deduplicazione
        $allSubjects = $primarySubjects->merge($relationalSubjects)
            ->unique()
            ->take(50)
            ->map(function ($key) {
                [$type, $id] = explode('::', $key, 2);
                return [
                    'type' => $type,
                    'id' => $id,
                    'label' => class_basename($type) . ' #' . $id,
                ];
            })
            ->values();

        return response()->json($allSubjects);
    }

    /**
     * Classifica la natura dell'evento per renderlo intuitivo nello Storyboard.
     */
    protected function classifyEvent(OperationLog $log): array
    {
        $verbo = strtolower($log->verbo);
        $isError = $log->codicehttp >= 400 || !empty($log->error);
        $isRollback = !empty($log->transaction_status) && str_contains($log->transaction_status, 'rolled_back');
        $hasSteps = !empty($log->custom_traces['steps']);

        if ($isError || $isRollback) {
            $label = $isRollback ? 'Rollback' : 'Errore ' . $log->codicehttp;
            return [
                'category' => 'error',
                'label' => $label,
                'badge_label' => $label,
                'badge_bg' => 'rgba(239, 68, 68, 0.18)',
                'badge_color' => '#ef4444',
                'title' => $isRollback
                    ? 'Rollback transazione su ' . strtoupper($log->verbo) . ' ' . $log->rotta
                    : 'Errore HTTP ' . $log->codicehttp . ($log->controllermethod ? ' in ' . class_basename($log->controllermethod) : ''),
                'icon' => 'alert-triangle',
            ];
        }

        if ($verbo === 'step' || ($hasSteps && $log->rotta === 'storyboard::checkpoint')) {
            $stepTitle = $log->custom_traces['steps'][0]['label'] ?? 'Checkpoint';
            return [
                'category' => 'checkpoint',
                'label' => 'Checkpoint',
                'badge_label' => 'Checkpoint',
                'badge_bg' => 'rgba(59, 130, 246, 0.18)',
                'badge_color' => '#3b82f6',
                'title' => $stepTitle,
                'icon' => 'flag',
            ];
        }

        if ($verbo === 'post') {
            return [
                'category' => 'create',
                'label' => 'Creazione',
                'badge_label' => 'Creazione',
                'badge_bg' => 'rgba(16, 185, 129, 0.18)',
                'badge_color' => '#10b981',
                'title' => 'Creazione / Inserimento (' . strtoupper($log->verbo) . ')',
                'icon' => 'plus-circle',
            ];
        }

        if (in_array($verbo, ['put', 'patch'])) {
            return [
                'category' => 'update',
                'label' => 'Modifica',
                'badge_label' => 'Modifica',
                'badge_bg' => 'rgba(245, 158, 11, 0.18)',
                'badge_color' => '#f59e0b',
                'title' => 'Modifica entità (' . strtoupper($log->verbo) . ')',
                'icon' => 'edit-3',
            ];
        }

        if ($verbo === 'delete') {
            return [
                'category' => 'delete',
                'label' => 'Eliminazione',
                'badge_label' => 'Eliminazione',
                'badge_bg' => 'rgba(139, 92, 246, 0.18)',
                'badge_color' => '#8b5cf6',
                'title' => 'Eliminazione record (' . strtoupper($log->verbo) . ')',
                'icon' => 'trash-2',
            ];
        }

        return [
            'category' => 'read',
            'label' => strtoupper($log->verbo),
            'badge_label' => strtoupper($log->verbo),
            'badge_bg' => 'rgba(107, 114, 128, 0.18)',
            'badge_color' => '#9ca3af',
            'title' => 'Accesso / Consultazione (' . strtoupper($log->verbo) . ')',
            'icon' => 'eye',
        ];
    }

    /**
     * Elenco dei codici HTTP presenti nel DB.
     *
     * GET /api/log-operations/http-codes
     */
    public function httpCodes(): JsonResponse
    {
        $codes = OperationLog::query()
            ->select('codicehttp')
            ->distinct()
            ->whereNotNull('codicehttp')
            ->orderBy('codicehttp')
            ->pluck('codicehttp');

        return response()->json($codes);
    }

    /**
     * Elenco dei verbi HTTP presenti nel DB.
     *
     * GET /api/log-operations/verbs
     */
    public function verbs(): JsonResponse
    {
        $verbs = OperationLog::query()
            ->select('verbo')
            ->distinct()
            ->whereNotNull('verbo')
            ->orderBy('verbo')
            ->pluck('verbo');

        return response()->json($verbs);
    }

    /**
     * Elenco delle applicazioni registrate.
     *
     * GET /api/log-operations/applications
     */
    public function applications(): JsonResponse
    {
        $apps = OperationLog::query()
            ->select('nomeapplicazione')
            ->distinct()
            ->whereNotNull('nomeapplicazione')
            ->orderBy('nomeapplicazione')
            ->pluck('nomeapplicazione');

        return response()->json($apps);
    }

    /**
     * Statistiche rapide per la dashboard KPI.
     *
     * GET /api/log-operations/stats
     * Parametri opzionali: date_from, date_to, app
     */
    public function stats(Request $request): JsonResponse
    {
        $query = OperationLog::query();

        // Filtro data opzionale
        if ($request->filled('date_from')) {
            $query->where('dataoperazione', '>=', Carbon::parse($request->date_from));
        }
        if ($request->filled('date_to')) {
            $query->where('dataoperazione', '<=', Carbon::parse($request->date_to));
        }
        if ($request->filled('app')) {
            $query->where('nomeapplicazione', $request->app);
        }

        // Calcolo metriche statistiche aggregate con una singola query SQL (-75% query)
        $statsRaw = (clone $query)->selectRaw("
            COUNT(*) as total_requests,
            SUM(CASE WHEN codicehttp >= 400 THEN 1 ELSE 0 END) as total_errors,
            AVG(duration_ms) as avg_duration,
            SUM(CASE WHEN transaction_status IS NOT NULL THEN 1 ELSE 0 END) as pending_transactions
        ")->first();

        $totalRequests = (int) ($statsRaw->total_requests ?? 0);
        $totalErrors = (int) ($statsRaw->total_errors ?? 0);
        $avgDuration = $statsRaw->avg_duration !== null ? (float) $statsRaw->avg_duration : null;
        $pendingTransactions = (int) ($statsRaw->pending_transactions ?? 0);

        // Top 5 errori più frequenti
        $topErrors = (clone $query)
            ->where('codicehttp', '>=', 400)
            ->select('codicehttp', DB::raw('COUNT(*) as count'))
            ->groupBy('codicehttp')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        return response()->json([
            'total_requests' => $totalRequests,
            'total_errors' => $totalErrors,
            'error_rate' => $totalRequests > 0
                ? round(($totalErrors / $totalRequests) * 100, 2)
                : 0,
            'avg_duration_ms' => $avgDuration ? round($avgDuration, 0) : null,
            'pending_transactions' => $pendingTransactions,
            'top_errors' => $topErrors,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Filtri a Gruppi (Retrocompatibilità)
    |--------------------------------------------------------------------------
    */

    protected function applyGroupFilters($query, string $encodedGroups, string $tableName): void
    {
        $gruppi = json_decode(base64_decode($encodedGroups), true) ?? [];

        foreach ($gruppi as $gruppo) {
            $operatoreGruppo = strtoupper($gruppo['operatoreGruppo'] ?? 'AND');
            $operatoreCampi = strtoupper($gruppo['operatoreCampi'] ?? 'OR');

            $search = trim($gruppo['search'] ?? '');
            $ip = trim($gruppo['ip'] ?? '');
            $controller = trim($gruppo['controller'] ?? '');
            $codicehttp = $gruppo['codicehttp'] ?? [];
            $dataDa = $gruppo['data_da'] ?? null;
            $dataA = $gruppo['data_a'] ?? null;

            // Data di default: oggi
            if (!$dataDa) {
                $dataDa = now()->startOfDay();
            }
            if (!$dataA) {
                $dataA = now()->endOfDay();
            }

            // Salta gruppi senza criteri significativi
            if (empty(array_filter([$search, $ip, $controller, $codicehttp, $dataDa, $dataA]))) {
                continue;
            }

            // Operatore NOT: nega tutto il gruppo
            if ($operatoreGruppo === 'NOT') {
                $query->where(function ($notQuery) use (
                    $search, $ip, $controller, $codicehttp, $dataDa, $dataA, $tableName
                ) {
                    $notQuery->whereNot(function ($sub) use (
                        $search, $ip, $controller, $codicehttp, $dataDa, $dataA, $tableName
                    ) {
                        $this->applyGroupFields($sub, $search, $ip, $controller, $codicehttp, $dataDa, $dataA, 'orWhere', $tableName);
                    });
                });
                continue;
            }

            // Operatore tra gruppi: AND o OR
            $metodoGruppo = ($operatoreGruppo === 'OR') ? 'orWhere' : 'where';
            $metodoCampo = ($operatoreCampi === 'AND') ? 'where' : 'orWhere';

            $query->$metodoGruppo(function ($sub) use (
                $search, $ip, $controller, $codicehttp, $dataDa, $dataA, $metodoCampo, $tableName
            ) {
                $this->applyGroupFields($sub, $search, $ip, $controller, $codicehttp, $dataDa, $dataA, $metodoCampo, $tableName);
            });
        }
    }

    /**
     * Applica i campi di un singolo gruppo alla query.
     */
    protected function applyGroupFields(
        $query,
        string $search,
        string $ip,
        string $controller,
        array $codicehttp,
        $dataDa,
        $dataA,
        string $metodo,
        string $tableName
    ): void {
        // Ricerca utente (polimorfica, ospite o ID numerico)
        if ($search) {
            $query->$metodo(function ($userQuery) use ($search, $tableName) {
                if ($search === 'guest' || $search === '__guest__') {
                    $userQuery->whereNull($tableName . '.user_id');
                } elseif (is_numeric($search)) {
                    $userQuery->where($tableName . '.user_id', $search);
                } else {
                    $this->applyPolymorphicUserSearch($userQuery, $search);
                }
            });
        }

        // IP
        if ($ip) {
            $query->$metodo($tableName . '.client_ip', 'like', '%' . $ip . '%');
        }

        // Controller
        if ($controller) {
            $query->$metodo($tableName . '.controllermethod', 'like', '%' . $controller . '%');
        }

        // Codici HTTP
        if (!empty($codicehttp)) {
            if ($metodo === 'where') {
                $query->whereIn($tableName . '.codicehttp', $codicehttp);
            } else {
                $query->orWhereIn($tableName . '.codicehttp', $codicehttp);
            }
        }

        // Range date
        if ($dataDa) {
            $query->where($tableName . '.dataoperazione', '>=', Carbon::parse($dataDa));
        }
        if ($dataA) {
            $query->where($tableName . '.dataoperazione', '<=', Carbon::parse($dataA));
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Filtri Diretti (GET Parameters)
    |--------------------------------------------------------------------------
    */

    protected function applyDirectFilters($query, Request $request, string $tableName): void
    {
        // Ricerca utente (inclusi ospiti non autenticati e ID numerico)
        if ($request->filled('user')) {
            if ($request->user === 'guest' || $request->user === '__guest__') {
                $query->whereNull($tableName . '.user_id');
            } elseif (is_numeric($request->user)) {
                $query->where($tableName . '.user_id', $request->user);
            } else {
                $query->where(function ($q) use ($request) {
                    $this->applyPolymorphicUserSearch($q, $request->user);
                });
            }
        } elseif ($request->filled('user_id')) {
            if ($request->user_id === 'guest' || $request->user_id === '__guest__') {
                $query->whereNull($tableName . '.user_id');
            } else {
                $query->where($tableName . '.user_id', $request->user_id);
            }
        }

        // Verbo HTTP
        if ($request->filled('verb')) {
            $verbs = is_array($request->verb) ? $request->verb : [$request->verb];
            $query->whereIn($tableName . '.verbo', array_map('strtolower', $verbs));
        }

        // Codici HTTP
        if ($request->filled('status_codes')) {
            $codes = is_array($request->status_codes) ? $request->status_codes : [$request->status_codes];
            $query->whereIn($tableName . '.codicehttp', $codes);
        }

        // Range date
        if ($request->filled('date_from')) {
            $query->where($tableName . '.dataoperazione', '>=', Carbon::parse($request->date_from));
        }
        if ($request->filled('date_to')) {
            $query->where($tableName . '.dataoperazione', '<=', Carbon::parse($request->date_to));
        }

        // IP
        if ($request->filled('ip')) {
            $query->where($tableName . '.client_ip', 'like', '%' . $request->ip . '%');
        }

        // Controller
        if ($request->filled('controller')) {
            $query->where($tableName . '.controllermethod', 'like', '%' . $request->controller . '%');
        }

        // Applicazione
        if ($request->filled('app')) {
            $query->where($tableName . '.nomeapplicazione', $request->app);
        }

        // Ricerca testuale libera
        if ($request->filled('text')) {
            $like = '%' . $request->text . '%';
            $query->where(function ($q) use ($like, $tableName) {
                $q->where($tableName . '.rotta', 'like', $like)
                  ->orWhere($tableName . '.controllermethod', 'like', $like)
                  ->orWhere($tableName . '.error', 'like', $like);
            });
        }

        // Solo errori
        if ($request->boolean('has_error')) {
            $query->where($tableName . '.codicehttp', '>=', 400);
        }

        // Solo transazioni pendenti
        if ($request->boolean('has_unfinished_transaction')) {
            $query->whereNotNull($tableName . '.transaction_status');
        }

        // Durata minima in ms (es. richieste lente > 1000ms)
        if ($request->filled('min_duration')) {
            $query->where($tableName . '.duration_ms', '>=', (float) $request->min_duration);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Ricerca Polimorfica Utente
    |--------------------------------------------------------------------------
    */

    /**
     * Esegue una ricerca sull'utente associato al log in modo polimorfico.
     * Verifica dinamicamente quali colonne esistono nella tabella utenti.
     */
    protected function applyPolymorphicUserSearch($query, string $search): void
    {
        $like = '%' . $search . '%';
        $searchFields = config('logoperations.user_search_fields', ['name', 'cognome', 'email']);

        // Prova a caricare il modello utente predefinito per ottenere la tabella
        $userModels = $this->getDistinctUserTypes();

        if (empty($userModels)) {
            // Fallback: cerca user_id se il search è numerico
            if (is_numeric($search)) {
                $query->where('user_id', $search);
            }
            return;
        }

        $query->where(function ($subQuery) use ($like, $searchFields, $userModels) {
            foreach ($userModels as $userType) {
                try {
                    $actualClass = Relation::getMorphedModel($userType) ?? $userType;
                    if (!class_exists($actualClass)) {
                        continue;
                    }

                    $modelInstance = new $actualClass;
                    $userTable = $modelInstance->getTable();
                    $userConnection = $modelInstance->getConnectionName() ?: config('database.default');

                    // Verifica quali colonne esistono realmente nella tabella utente sulla sua specifica connessione DB
                    if (!isset(static::$userColumnsCache[$userTable])) {
                        static::$userColumnsCache[$userTable] = [];
                        foreach ($searchFields as $field) {
                            if (Schema::connection($userConnection)->hasColumn($userTable, $field)) {
                                static::$userColumnsCache[$userTable][] = $field;
                            }
                        }
                    }
                    $existingFields = static::$userColumnsCache[$userTable];

                    if (empty($existingFields)) {
                        continue;
                    }

                    // Esegui la query degli ID direttamente sulla connessione nativa del modello utente,
                    // evitando subquery cross-database che falliscono quando i log risiedono su connessione separata
                    $userIds = $modelInstance->newQuery()
                        ->select($modelInstance->getKeyName())
                        ->where(function ($q) use ($existingFields, $like) {
                            foreach ($existingFields as $field) {
                                $q->orWhere($field, 'like', $like);
                            }
                        })
                        ->limit(500)
                        ->pluck($modelInstance->getKeyName())
                        ->all();

                    if (!empty($userIds)) {
                        $subQuery->orWhere(function ($typeQuery) use ($userType, $userIds) {
                            $typeQuery->where('user_type', $userType)
                                ->whereIn('user_id', $userIds);
                        });
                    }
                } catch (\Throwable $e) {
                    // Se il modello non è caricabile, ignora silenziosamente
                    continue;
                }
            }
        });
    }

    /**
     * Ottiene i tipi di utente distinti presenti nella tabella log.
     */
    protected function getDistinctUserTypes(): array
    {
        try {
            return OperationLog::query()
                ->select('user_type')
                ->distinct()
                ->whereNotNull('user_type')
                ->pluck('user_type')
                ->toArray();
        } catch (\Throwable $e) {
            // Fallback: tenta con il modello utente di default di Laravel
            return ['App\\Models\\User'];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Arricchimento Dati Utente
    |--------------------------------------------------------------------------
    */

    /**
     * Arricchisce il log con i dati dell'utente (nome, cognome, email)
     * caricandoli in modo polimorfico senza eseguire join rigidi.
     */
    protected function enrichWithUserData(OperationLog $log): array
    {
        $data = $log->toArray();

        // Carica la relazione utente polimorfica
        if ($log->user_id && $log->user_type) {
            try {
                $user = $log->user;
                if ($user) {
                    $searchFields = config('logoperations.user_search_fields', ['name', 'cognome', 'email']);
                    $userData = [];
                    foreach ($searchFields as $field) {
                        if (isset($user->$field)) {
                            $userData[$field] = $user->$field;
                        }
                    }
                    $data['user_data'] = $userData;
                    $data['user_label'] = implode(' ', array_filter([
                        $userData['cognome'] ?? null,
                        $userData['name'] ?? null,
                    ])) ?: ($userData['email'] ?? 'Utente #' . $log->user_id);
                }
            } catch (\Throwable $e) {
                $data['user_data'] = null;
                $data['user_label'] = 'Utente #' . $log->user_id . ' (' . class_basename($log->user_type) . ')';
            }
        } else {
            $data['user_data'] = null;
            $data['user_label'] = $log->user_id ? 'Utente #' . $log->user_id : 'Anonimo';
        }

        // Calcola label leggibile dell'entità target (subject) se presente
        if ($log->subject_id && $log->subject_type) {
            $data['subject_label'] = class_basename($log->subject_type) . ' #' . $log->subject_id;
        } else {
            $data['subject_label'] = null;
        }

        return $data;
    }
}
