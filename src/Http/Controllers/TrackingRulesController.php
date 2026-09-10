<?php

namespace SalvatoreCervone\LogOperations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use SalvatoreCervone\LogOperations\Models\OperationRule;
use SalvatoreCervone\LogOperations\Services\AppScanner;
use SalvatoreCervone\LogOperations\Services\RuleEngine;
use SalvatoreCervone\LogOperations\Http\Controllers\Concerns\AuthorizesLogOperations;

/**
 * Controller per la gestione delle Regole di Tracciamento Zero-Code.
 */
class TrackingRulesController extends Controller
{
    use AuthorizesLogOperations;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->authorizeAccess($request);
            return $next($request);
        });
    }

    /**
     * Catalogo delle rotte dell'applicazione con stato di monitoraggio.
     */
    public function routesDiscovery(AppScanner $scanner): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $scanner->getRoutes(),
        ]);
    }

    /**
     * Catalogo delle classi e metodi applicativi con stato di monitoraggio.
     */
    public function classesDiscovery(AppScanner $scanner): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $scanner->getClasses(),
        ]);
    }

    /**
     * Elenco di tutte le regole configurate.
     */
    public function getRules(): JsonResponse
    {
        $rules = OperationRule::latest()->get()->map(function ($rule) {
            return [
                'id'           => $rule->id,
                'name'         => $rule->name,
                'type'         => $rule->type,
                'target'       => $rule->target,
                'http_methods' => $rule->http_methods ?: ['*'],
                'stack_level'  => $rule->stack_level,
                'is_active'    => (bool) $rule->is_active,
                'is_expired'   => $rule->isExpired(),
                'expires_at'   => $rule->expires_at?->toIso8601String(),
                'seconds_remaining' => $rule->expires_at ? max(0, (int) now()->diffInSeconds($rule->expires_at, false)) : null,
                'metadata'     => $rule->metadata,
                'created_at'   => $rule->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $rules,
        ]);
    }

    /**
     * Salva o aggiorna una regola (rotta o metodo).
     */
    public function saveRule(Request $request, RuleEngine $engine): JsonResponse
    {
        $data = $request->validate([
            'id'           => 'nullable|integer',
            'type'         => 'required|in:route,method',
            'target'       => 'required|string|max:512',
            'name'         => 'nullable|string|max:255',
            'http_methods' => 'nullable|array',
            'stack_level'  => 'nullable|in:base,core,full',
            'is_active'    => 'nullable|boolean',
        ]);

        $type = $data['type'];
        $target = $data['target'];
        $methods = $data['http_methods'] ?? ($type === 'route' ? ['*'] : null);
        if ($methods) {
            $methods = array_values(array_unique(array_map('strtoupper', $methods)));
            sort($methods);
        }

        $rule = null;
        if (!empty($data['id'])) {
            $rule = OperationRule::find($data['id']);
        }

        if (!$rule) {
            $candidates = OperationRule::where('type', $type)
                ->where('target', $target)
                ->get();

            if ($type === 'route' && $methods !== null) {
                $rule = $candidates->first(function ($c) use ($methods) {
                    $cMethods = $c->http_methods ?: ['*'];
                    $cMethods = array_values(array_unique(array_map('strtoupper', $cMethods)));
                    sort($cMethods);
                    return $cMethods === $methods;
                });
            } else {
                $rule = $candidates->first();
            }
        }

        $attributes = [
            'name'         => $data['name'] ?? ($rule ? $rule->name : null),
            'http_methods' => $methods ?? ($rule ? $rule->http_methods : ['*']),
            'stack_level'  => $data['stack_level'] ?? ($rule ? $rule->stack_level : ($type === 'method' ? 'core' : 'base')),
            'is_active'    => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : ($rule ? $rule->is_active : true),
        ];

        if ($rule) {
            $rule->update($attributes);
        } else {
            $rule = OperationRule::create(array_merge([
                'type'   => $type,
                'target' => $target,
            ], $attributes));
        }

        $engine->flushCache();

        return response()->json([
            'success' => true,
            'message' => 'Regola salvata con successo.',
            'data'    => $rule,
        ]);
    }

    /**
     * Salva o aggiorna in blocco le regole di tracciamento per più rotte o metodi.
     */
    public function bulkSaveRules(Request $request, RuleEngine $engine): JsonResponse
    {
        $data = $request->validate([
            'targets'          => 'nullable|array',
            'targets.*'        => 'string|max:512',
            'routes'           => 'nullable|array',
            'routes.*.target'  => 'required_with:routes|string|max:512',
            'routes.*.methods' => 'nullable|array',
            'type'             => 'required|in:route,method',
            'is_active'        => 'nullable|boolean',
            'stack_level'      => 'nullable|in:base,core,full',
        ]);

        $type = $data['type'];
        $items = [];

        if (!empty($data['routes'])) {
            foreach ($data['routes'] as $r) {
                $methods = !empty($r['methods']) ? array_values(array_unique(array_map('strtoupper', $r['methods']))) : ['*'];
                sort($methods);
                $key = $r['target'] . '::' . implode(',', $methods);
                $items[$key] = [
                    'target'  => $r['target'],
                    'methods' => $methods,
                ];
            }
        } elseif (!empty($data['targets'])) {
            foreach (array_unique($data['targets']) as $target) {
                $items[$target] = [
                    'target'  => $target,
                    'methods' => ['*'],
                ];
            }
        }

        if (empty($items)) {
            return response()->json([
                'success' => false,
                'message' => 'Nessun elemento specificato.',
            ], 422);
        }

        DB::transaction(function () use ($items, $type, $data) {
            foreach ($items as $item) {
                $target = $item['target'];
                $methods = $item['methods'];

                $values = [];
                if (array_key_exists('is_active', $data)) {
                    $values['is_active'] = (bool) $data['is_active'];
                }
                if (!empty($data['stack_level'])) {
                    $values['stack_level'] = $data['stack_level'];
                }

                $candidates = OperationRule::where('type', $type)->where('target', $target)->get();
                $rule = null;

                if ($type === 'route') {
                    $rule = $candidates->first(function ($c) use ($methods) {
                        $cMethods = $c->http_methods ?: ['*'];
                        $cMethods = array_values(array_unique(array_map('strtoupper', $cMethods)));
                        sort($cMethods);
                        return $cMethods === $methods;
                    });
                } else {
                    $rule = $candidates->first();
                }

                if ($rule) {
                    $rule->update($values);
                } else {
                    OperationRule::create(array_merge([
                        'type'         => $type,
                        'target'       => $target,
                        'name'         => $target,
                        'http_methods' => $methods,
                        'stack_level'  => $values['stack_level'] ?? 'base',
                        'is_active'    => $values['is_active'] ?? true,
                    ], $values));
                }
            }
        });

        $engine->flushCache();

        return response()->json([
            'success' => true,
            'message' => count($items) . ' regole aggiornate con successo.',
            'count'   => count($items),
        ]);
    }

    /**
     * Attiva o disattiva istantaneamente una regola.
     */
    public function toggleRule(int $id, RuleEngine $engine): JsonResponse
    {
        $rule = OperationRule::findOrFail($id);
        $rule->is_active = !$rule->is_active;
        $rule->save();

        $engine->flushCache();

        return response()->json([
            'success'   => true,
            'message'   => 'Stato della regola aggiornato.',
            'is_active' => $rule->is_active,
        ]);
    }

    /**
     * Elimina una regola.
     */
    public function deleteRule(int $id, RuleEngine $engine): JsonResponse
    {
        $rule = OperationRule::findOrFail($id);
        $rule->delete();

        $engine->flushCache();

        return response()->json([
            'success' => true,
            'message' => 'Regola eliminata.',
        ]);
    }

    /**
     * Ricerca rapida utenti dell'applicazione (per nome o email).
     */
    public function searchUsers(Request $request): JsonResponse
    {
        $q = trim($request->query('q', ''));
        $userModel = config('auth.providers.users.model', 'App\\Models\\User');

        if (!class_exists($userModel)) {
            return response()->json(['success' => true, 'data' => []]);
        }

        try {
            $query = $userModel::query();

            if (!empty($q)) {
                $query->where(function ($sub) use ($q) {
                    if (is_numeric($q)) {
                        $sub->where('id', $q);
                    }
                    if (Schema::hasColumn($sub->getModel()->getTable(), 'name')) {
                        $sub->orWhere('name', 'like', "%{$q}%");
                    }
                    if (Schema::hasColumn($sub->getModel()->getTable(), 'email')) {
                        $sub->orWhere('email', 'like', "%{$q}%");
                    }
                });
            }

            $users = $query->limit(15)->get()->map(function ($u) {
                $id = method_exists($u, 'getAuthIdentifier') ? $u->getAuthIdentifier() : $u->getKey();
                return [
                    'id'    => (string) $id,
                    'name'  => $u->name ?? $u->email ?? ('Utente #' . $id),
                    'email' => $u->email ?? null,
                ];
            });

            return response()->json(['success' => true, 'data' => $users]);
        } catch (\Throwable $e) {
            return response()->json(['success' => true, 'data' => []]);
        }
    }

    /**
     * Avvia una sessione di monitoraggio utente temporanea a tempo.
     */
    public function startUserSession(Request $request, RuleEngine $engine): JsonResponse
    {
        $data = $request->validate([
            'user_id'          => 'required',
            'duration_minutes' => 'required|integer|min:1|max:1440', // max 24h
            'user_name'        => 'nullable|string',
            'user_email'       => 'nullable|string',
        ]);

        $targetId = (string) $data['user_id'];
        $expiresAt = now()->addMinutes((int) $data['duration_minutes']);

        // Rimuove eventuali sessioni precedenti per lo stesso utente
        OperationRule::where('type', 'user_session')
            ->where('target', $targetId)
            ->delete();

        $rule = OperationRule::create([
            'type'        => 'user_session',
            'target'      => $targetId,
            'name'        => 'Live Monitor: ' . ($data['user_name'] ?? ('Utente #' . $targetId)),
            'stack_level' => 'full',
            'is_active'   => true,
            'expires_at'  => $expiresAt,
            'metadata'    => [
                'user_name'        => $data['user_name'] ?? null,
                'user_email'       => $data['user_email'] ?? null,
                'duration_minutes' => (int) $data['duration_minutes'],
            ],
        ]);

        $engine->flushCache();

        return response()->json([
            'success' => true,
            'message' => "Monitoraggio live avviato per {$data['duration_minutes']} minuti.",
            'data'    => [
                'id'         => $rule->id,
                'user_id'    => $rule->target,
                'expires_at' => $rule->expires_at->toIso8601String(),
                'seconds_remaining' => max(0, (int) now()->diffInSeconds($rule->expires_at, false)),
                'metadata'   => $rule->metadata,
            ],
        ]);
    }

    /**
     * Interrompe una sessione di monitoraggio utente.
     */
    public function stopUserSession(int $id, RuleEngine $engine): JsonResponse
    {
        $rule = OperationRule::where('type', 'user_session')->findOrFail($id);
        $rule->delete();

        $engine->flushCache();

        return response()->json([
            'success' => true,
            'message' => 'Monitoraggio utente interrotto.',
        ]);
    }
}
