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

/**
 * Controller per la gestione delle Regole di Tracciamento Zero-Code.
 */
class TrackingRulesController extends Controller
{
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
            'type'         => 'required|in:route,method',
            'target'       => 'required|string|max:512',
            'name'         => 'nullable|string|max:255',
            'http_methods' => 'nullable|array',
            'stack_level'  => 'nullable|in:base,core,full',
            'is_active'    => 'nullable|boolean',
        ]);

        $rule = OperationRule::updateOrCreate(
            [
                'type'   => $data['type'],
                'target' => $data['target'],
            ],
            [
                'name'         => $data['name'] ?? null,
                'http_methods' => $data['http_methods'] ?? ['*'],
                'stack_level'  => $data['stack_level'] ?? ($data['type'] === 'method' ? 'core' : 'base'),
                'is_active'    => $data['is_active'] ?? true,
            ]
        );

        $engine->flushCache();

        return response()->json([
            'success' => true,
            'message' => 'Regola salvata con successo.',
            'data'    => $rule,
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
                    $sub->where('id', $q);
                    if (Schema::hasColumn($sub->getModel()->getTable(), 'name')) {
                        $sub->orWhere('name', 'like', "%{$q}%");
                    }
                    if (Schema::hasColumn($sub->getModel()->getTable(), 'email')) {
                        $sub->orWhere('email', 'like', "%{$q}%");
                    }
                });
            }

            $users = $query->limit(15)->get()->map(function ($u) {
                return [
                    'id'    => (string) $u->getAuthIdentifier(),
                    'name'  => $u->name ?? $u->email ?? ('Utente #' . $u->getAuthIdentifier()),
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
