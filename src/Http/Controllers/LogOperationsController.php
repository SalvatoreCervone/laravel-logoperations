<?php

namespace SalvatoreCervone\LogOperations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use SalvatoreCervone\LogOperations\Models\OperationLog;

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
    public function index(Request $request): JsonResponse
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
            ])
            ->orderBy($tableName . '.dataoperazione', 'desc');

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

        // Paginazione
        $perPage = min((int) $request->input('per_page', 20), 100);
        $logs = $query->paginate($perPage)->withQueryString();

        // Arricchisci i risultati con i dati dell'utente polimorfico
        $logs->getCollection()->transform(function ($log) {
            return $this->enrichWithUserData($log);
        });

        return response()->json($logs);
    }

    /**
     * Dettaglio completo di un singolo log.
     *
     * GET /api/log-operations/{id}
     */
    public function show(int $id): JsonResponse
    {
        $log = OperationLog::findOrFail($id);
        $enriched = $this->enrichWithUserData($log);

        return response()->json([
            'data' => $enriched,
            'core_stack' => $log->core_stack,
            'full_stack' => $log->full_stack,
        ]);
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

        $totalRequests = (clone $query)->count();
        $totalErrors = (clone $query)->where('codicehttp', '>=', 400)->count();
        $avgDuration = (clone $query)->whereNotNull('duration_ms')->avg('duration_ms');
        $pendingTransactions = (clone $query)->whereNotNull('transaction_status')->count();

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
        // Ricerca utente (polimorfica)
        if ($search) {
            $query->$metodo(function ($userQuery) use ($search) {
                $this->applyPolymorphicUserSearch($userQuery, $search);
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
        // Ricerca utente
        if ($request->filled('user')) {
            $query->where(function ($q) use ($request) {
                $this->applyPolymorphicUserSearch($q, $request->user);
            });
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
                    if (!class_exists($userType)) {
                        continue;
                    }

                    $modelInstance = new $userType;
                    $userTable = $modelInstance->getTable();

                    // Verifica quali colonne esistono realmente nella tabella
                    $existingFields = [];
                    foreach ($searchFields as $field) {
                        if (Schema::hasColumn($userTable, $field)) {
                            $existingFields[] = $field;
                        }
                    }

                    if (empty($existingFields)) {
                        continue;
                    }

                    $subQuery->orWhereIn('user_id', function ($inQuery) use (
                        $userTable, $userType, $existingFields, $like
                    ) {
                        $inQuery->select('id')
                            ->from($userTable)
                            ->where(function ($q) use ($existingFields, $like) {
                                foreach ($existingFields as $field) {
                                    $q->orWhere($field, 'like', $like);
                                }
                            });
                    });
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

        return $data;
    }
}
