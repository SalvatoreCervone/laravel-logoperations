<?php

namespace SalvatoreCervone\LogOperations\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use SalvatoreCervone\LogOperations\Models\OperationRule;

/**
 * Motore ad alte prestazioni per la valutazione delle regole dinamiche.
 *
 * Utilizza la cache di Laravel per azzerare l'impatto sul database:
 * le regole attive vengono caricate una sola volta in memoria/cache e
 * l'invalidazione avviene atomica ad ogni modifica da pannello.
 */
class RuleEngine
{
    public const CACHE_KEY = 'logoperations_active_rules_cache';

    /**
     * Recupera tutte le regole attive dalla cache (o da DB se non in cache).
     */
    public function getActiveRules(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, function () {
                $tableName = config('logoperations.rules_table_name', 'log_operazioni_regole');
                $connection = config('logoperations.database_connection');

                // Se la tabella non esiste ancora (es. migrazione non eseguita), ritorna array vuoto
                if (!Schema::connection($connection)->hasTable($tableName)) {
                    return [
                        'routes'        => [],
                        'methods'       => [],
                        'user_sessions' => [],
                    ];
                }

                $rules = OperationRule::active()->get();

                $routeRules = [];
                $methodRules = [];
                $userSessions = [];

                foreach ($rules as $rule) {
                    if ($rule->isExpired()) {
                        continue;
                    }

                    if ($rule->type === 'route') {
                        $routeRules[] = [
                            'id'           => $rule->id,
                            'target'       => $rule->target,
                            'http_methods' => $rule->http_methods ?: ['*'],
                            'stack_level'  => $rule->stack_level ?: 'base',
                        ];
                    } elseif ($rule->type === 'method') {
                        $methodRules[$rule->target] = [
                            'id'          => $rule->id,
                            'target'      => $rule->target,
                            'stack_level' => $rule->stack_level ?: 'core',
                        ];
                    } elseif ($rule->type === 'user_session') {
                        $userSessions[] = [
                            'id'         => $rule->id,
                            'target'     => (string) $rule->target,
                            'expires_at' => $rule->expires_at?->toIso8601String(),
                            'metadata'   => $rule->metadata ?: [],
                        ];
                    }
                }

                return [
                    'routes'        => $routeRules,
                    'methods'       => $methodRules,
                    'user_sessions' => $userSessions,
                ];
            });
        } catch (\Throwable $e) {
            return [
                'routes'        => [],
                'methods'       => [],
                'user_sessions' => [],
            ];
        }
    }

    /**
     * Invalida la cache delle regole.
     */
    public function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Valuta una richiesta HTTP in ingresso rispetto alle regole attive.
     *
     * @return array [
     *   'should_log'         => bool,
     *   'stack_level'        => string ('base'|'core'|'full'),
     *   'matched_by'         => string ('user_session'|'dynamic_route'|'default_config'|'none'),
     *   'is_user_monitored'  => bool,
     *   'rule_id'            => int|null
     * ]
     */
    public function evaluateRequest(Request $request): array
    {
        $active = $this->getActiveRules();

        // 1. Verifica se l'utente autenticato è sotto sessione di monitoraggio live a tempo
        $user = $request->user();
        if ($user) {
            $userId = (string) $user->getAuthIdentifier();
            foreach ($active['user_sessions'] as $session) {
                if ($session['target'] === $userId) {
                    // Controlla se la sessione non è scaduta
                    if (empty($session['expires_at']) || strtotime($session['expires_at']) > time()) {
                        return [
                            'should_log'        => true,
                            'stack_level'       => 'full', // massima visibilità per l'utente monitorato
                            'matched_by'        => 'user_session',
                            'is_user_monitored' => true,
                            'rule_id'           => $session['id'],
                        ];
                    }
                }
            }
        }

        // 2. Verifica regole dinamiche sulle rotte
        $path = $request->path();
        $method = $request->method();
        $route = $request->route();
        $routeUri = ($route && method_exists($route, 'uri')) ? $route->uri() : null;

        foreach ($active['routes'] as $rule) {
            if ($this->matchUri($rule['target'], $path, $routeUri) && $this->matchMethod($rule['http_methods'], $method)) {
                return [
                    'should_log'        => true,
                    'stack_level'       => $rule['stack_level'],
                    'matched_by'        => 'dynamic_route',
                    'is_user_monitored' => false,
                    'rule_id'           => $rule['id'],
                ];
            }
        }

        // 3. Fallback alla configurazione statica
        return [
            'should_log'        => false,
            'stack_level'       => null,
            'matched_by'        => 'none',
            'is_user_monitored' => false,
            'rule_id'           => null,
        ];
    }

    /**
     * Verifica se una funzione/metodo di una classe è sotto monitoraggio dinamico.
     */
    public function isMethodMonitored(string $className, string $methodName): ?array
    {
        $active = $this->getActiveRules();
        $target = $className . '@' . $methodName;

        return $active['methods'][$target] ?? $active['methods'][$className . '@*'] ?? null;
    }

    /**
     * Verifica pattern URI supportando wildcard, template Laravel (es. users/{id}) e query string.
     */
    public function matchUri(string $pattern, string $uri, ?string $routeUri = null): bool
    {
        // Rimuove eventuale query string (?foo=bar) preservando i parametri opzionali {param?}
        $pattern = trim((string) preg_replace('/\?(?![a-zA-Z0-9_]*\}).*/', '', $pattern), '/');
        $uri = trim(strtok($uri, '?'), '/');

        // 1. Corrispondenza esatta o wildcard diretta sul path
        if (\Illuminate\Support\Str::is($pattern, $uri) || $pattern === $uri) {
            return true;
        }

        // 2. Corrispondenza con il template della rotta registrata in Laravel (es. "api/users/{id}")
        if ($routeUri !== null) {
            $cleanRouteUri = trim(strtok($routeUri, '?'), '/');
            if ($pattern === $cleanRouteUri || \Illuminate\Support\Str::is($pattern, $cleanRouteUri)) {
                return true;
            }
        }

        // 3. Risoluzione dei parametri dinamici tipo {id}, {slug}, {user?} nel pattern rispetto al path effettivo
        if (str_contains($pattern, '{')) {
            $regex = preg_quote($pattern, '#');
            // Gestione parametri opzionali /{param?}
            $regex = preg_replace('/\/\\\{[a-zA-Z0-9_]+\\\\\?\\\}/', '(?:/[^/]+)?', $regex);
            // Gestione parametri obbligatori {param}
            $regex = preg_replace('/\\\{[a-zA-Z0-9_]+\\\}/', '[^/]+', $regex);
            if (preg_match('#^' . $regex . '$#i', $uri)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica metodo HTTP.
     */
    public function matchMethod(array $allowed, string $method): bool
    {
        if (empty($allowed) || in_array('*', $allowed)) {
            return true;
        }

        return in_array(strtoupper($method), array_map('strtoupper', $allowed));
    }
}
