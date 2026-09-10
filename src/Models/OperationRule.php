<?php

namespace SalvatoreCervone\LogOperations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Support\Str;

/**
 * Modello Eloquent per le Regole Dinamiche di Tracciamento Zero-Code.
 *
 * Supporta:
 * - Regole su rotte permanenti (type: 'route')
 * - Regole su metodi/funzioni di classi (type: 'method')
 * - Sessioni di monitoraggio utente temporanee (type: 'user_session')
 */
class OperationRule extends Model
{
    use MassPrunable;

    protected $guarded = ['id'];

    protected $casts = [
        'http_methods' => 'array',
        'is_active'    => 'boolean',
        'expires_at'   => 'datetime',
        'metadata'     => 'array',
    ];

    /**
     * Tabella configurabile da config/logoperations.php.
     */
    public function getTable(): string
    {
        return config('logoperations.rules_table_name', 'log_operazioni_regole');
    }

    /**
     * Connessione DB configurabile.
     */
    public function getConnectionName(): ?string
    {
        return config('logoperations.database_connection');
    }

    /**
     * Scope: regole attive e non scadute.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope: solo regole di rotta.
     */
    public function scopeRoutes(Builder $query): Builder
    {
        return $query->where('type', 'route');
    }

    /**
     * Scope: solo regole di metodo/funzione.
     */
    public function scopeMethods(Builder $query): Builder
    {
        return $query->where('type', 'method');
    }

    /**
     * Scope: solo sessioni utente temporanee.
     */
    public function scopeUserSessions(Builder $query): Builder
    {
        return $query->where('type', 'user_session');
    }

    /**
     * Verifica se la regola è scaduta.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Verifica se il metodo HTTP soddisfa la regola.
     */
    public function matchesHttpMethod(string $httpMethod): bool
    {
        $allowed = $this->http_methods;
        if (empty($allowed) || in_array('*', $allowed)) {
            return true;
        }

        $upperMethod = strtoupper($httpMethod);
        $upperAllowed = array_map('strtoupper', $allowed);

        return in_array($upperMethod, $upperAllowed);
    }

    /**
     * Verifica se il percorso URI soddisfa il pattern target della regola.
     */
    public function matchesUri(string $uri): bool
    {
        $pattern = trim(strtok($this->target, '?'), '/');
        $cleanUri = trim(strtok($uri, '?'), '/');

        if (Str::is($pattern, $cleanUri) || $pattern === $cleanUri) {
            return true;
        }

        if (str_contains($pattern, '{')) {
            $regex = preg_quote($pattern, '#');
            // Gestione parametri opzionali /{param?}
            $regex = preg_replace('/\/\\\{[a-zA-Z0-9_]+\\\\\?\\\}/', '(?:/[^/]+)?', $regex);
            // Gestione parametri obbligatori {param}
            $regex = preg_replace('/\\\{[a-zA-Z0-9_]+\\\}/', '[^/]+', $regex);
            if (preg_match('#^' . $regex . '$#i', $cleanUri)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Query per il pruning delle sole regole temporanee scadute.
     */
    public function prunable(): Builder
    {
        return static::whereNotNull('expires_at')->where('expires_at', '<', now());
    }
}
