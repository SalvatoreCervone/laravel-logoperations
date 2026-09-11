<?php

namespace SalvatoreCervone\LogOperations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * Modello Eloquent per i log delle operazioni.
 *
 * Supporta:
 * - Relazione utente polimorfica (morphTo)
 * - Cast nativi per parametri JSON, stack trace e date
 * - Scopes di ricerca avanzati per tutti i campi
 */
class OperationLog extends Model
{
    /**
     * La tabella è configurabile tramite config/logoperations.php.
     */
    public function getTable(): string
    {
        return config('logoperations.table_name', 'log_operazioni');
    }

    /**
     * La connessione è configurabile per isolare i log.
     */
    public function getConnectionName(): ?string
    {
        return config('logoperations.database_connection') ?? parent::getConnectionName();
    }

    /**
     * Factory personalizzata per le relazioni MorphTo (user, subject).
     *
     * Quando OperationLog risiede su una connessione dedicata (es. 'logoperazioni'),
     * impedisce a Laravel di forzare i modelli target polimorfici (es. App\Models\User)
     * sulla connessione dei log se tali modelli non definiscono una connessione esplicita.
     * In tal caso, imposta la connessione predefinita dell'applicazione (config('database.default')).
     */
    protected function newMorphTo(Builder $query, Model $parent, $foreignKey, $ownerKey, $type, $relation)
    {
        return new class($query, $parent, $foreignKey, $ownerKey, $type, $relation) extends MorphTo {
            public function createModelByType($type)
            {
                $class = Model::getActualClassNameForMorph($type);

                return tap(new $class, function ($instance) {
                    if (! $instance->getConnectionName()) {
                        $instance->setConnection(config('database.default'));
                    }
                });
            }
        };
    }

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected static function booted(): void
    {
        static::creating(function ($log) {
            if (empty($log->nomeapplicazione)) {
                $log->nomeapplicazione = config('logoperations.app_name', 'laravel');
            }
        });
    }

    /**
     * Cast nativi per i campi JSON e datetime.
     */
    protected $casts = [
        'parametri' => 'array',
        'stack_trace' => 'array',
        'custom_traces' => 'array',
        'dataoperazione' => 'datetime',
        'codicehttp' => 'integer',
        'duration_ms' => 'integer',
        'transaction_level' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relazioni
    |--------------------------------------------------------------------------
    */

    /**
     * Relazione polimorfica all'utente.
     * Può essere User, Admin, Customer, o qualsiasi altro modello.
     */
    public function user(): MorphTo
    {
        return $this->morphTo('user');
    }

    /**
     * Relazione polimorfica all'entità target dell'operazione (subject).
     * Supporta qualsiasi modello Eloquent: Order, Invoice, Ticket, etc.
     */
    public function subject(): MorphTo
    {
        return $this->morphTo('subject');
    }

    /**
     * Relazione ai soggetti collegati (modelli toccati durante la stessa richiesta HTTP).
     * Ogni riga rappresenta un modello Eloquent created/updated/deleted.
     */
    public function subjects(): HasMany
    {
        return $this->hasMany(OperationSubject::class, 'log_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes di Ricerca
    |--------------------------------------------------------------------------
    */

    /**
     * Filtra per range di date.
     */
    public function scopeDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        if ($from) {
            $query->where('dataoperazione', '>=', $from);
        }
        if ($to) {
            $query->where('dataoperazione', '<=', $to);
        }
        return $query;
    }

    /**
     * Filtra per verbo HTTP.
     */
    public function scopeVerb(Builder $query, string|array $verb): Builder
    {
        if (is_array($verb)) {
            return $query->whereIn('verbo', array_map('strtolower', $verb));
        }
        return $query->where('verbo', strtolower($verb));
    }

    /**
     * Filtra per codici di stato HTTP.
     */
    public function scopeHttpStatus(Builder $query, int|array $codes): Builder
    {
        if (is_array($codes)) {
            return $query->whereIn('codicehttp', $codes);
        }
        return $query->where('codicehttp', $codes);
    }

    /**
     * Filtra per indirizzo IP (ricerca parziale).
     */
    public function scopeIpAddress(Builder $query, string $ip): Builder
    {
        return $query->where('client_ip', 'like', '%' . $ip . '%');
    }

    /**
     * Filtra per controller/metodo (ricerca parziale).
     */
    public function scopeController(Builder $query, string $controller): Builder
    {
        return $query->where('controllermethod', 'like', '%' . $controller . '%');
    }

    /**
     * Filtra per nome applicazione.
     */
    public function scopeApplication(Builder $query, string $app): Builder
    {
        return $query->where('nomeapplicazione', $app);
    }

    /**
     * Filtra solo errori (status >= 400).
     */
    public function scopeOnlyErrors(Builder $query): Builder
    {
        return $query->where('codicehttp', '>=', 400);
    }

    /**
     * Filtra per presenza di errore testuale.
     */
    public function scopeHasError(Builder $query): Builder
    {
        return $query->whereNotNull('error')->where('error', '!=', '');
    }

    /**
     * Filtra per transazioni non terminate.
     */
    public function scopeHasUnfinishedTransaction(Builder $query): Builder
    {
        return $query->whereNotNull('transaction_status');
    }

    /**
     * Ricerca testuale su rotta, parametri, errore.
     */
    public function scopeTextSearch(Builder $query, string $text): Builder
    {
        $like = '%' . $text . '%';
        return $query->where(function ($q) use ($like) {
            $q->where('rotta', 'like', $like)
              ->orWhere('controllermethod', 'like', $like)
              ->orWhere('error', 'like', $like);
        });
    }

    /**
     * Filtra per entità target polimorfica (subject).
     */
    public function scopeForSubject(Builder $query, Model|string $subject, int|string|null $id = null): Builder
    {
        $type = $subject instanceof Model ? $subject->getMorphClass() : $subject;
        $subjectId = $subject instanceof Model ? (string) $subject->getKey() : ($id !== null ? (string) $id : null);

        return $query->where(function (Builder $q) use ($type, $subjectId) {
            // Soggetto primario (colonne sulla tabella principale)
            $q->where(function (Builder $primary) use ($type, $subjectId) {
                $primary->where('subject_type', $type);
                if ($subjectId !== null) {
                    $primary->where('subject_id', $subjectId);
                }
            })
            // OPPURE soggetto collegato nella tabella relazionale
            ->orWhereHas('subjects', function (Builder $rel) use ($type, $subjectId) {
                $rel->where('subject_type', $type);
                if ($subjectId !== null) {
                    $rel->where('subject_id', $subjectId);
                }
            });
        });
    }

    /**
     * Filtra per tag assegnato alla richiesta.
     */
    public function scopeTag(Builder $query, string $tag): Builder
    {
        return $query->where(function ($q) use ($tag) {
            $q->whereJsonContains('parametri->tags', $tag)
              ->orWhere('parametri', 'like', '%"tags":%' . $tag . '%');
        });
    }

    /**
     * Filtra per chiave di contesto personalizzato.
     */
    public function scopeContext(Builder $query, string $key, mixed $value = null): Builder
    {
        if ($value === null) {
            return $query->whereNotNull("parametri->context->$key");
        }
        return $query->where("parametri->context->$key", $value);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Restituisce solo i frame dello stack che appartengono
     * al codice core applicativo (Livello 1).
     */
    public function getCoreStackAttribute(): array
    {
        return array_values(
            array_filter(
                $this->stack_trace ?? [],
                fn ($frame) => !empty($frame['is_core'])
            )
        );
    }

    /**
     * Restituisce lo stack completo (Livello 2) — tutti i frame.
     */
    public function getFullStackAttribute(): array
    {
        return $this->stack_trace ?? [];
    }

    /**
     * Indica se la richiesta ha generato un errore.
     */
    public function getIsErrorAttribute(): bool
    {
        return $this->codicehttp >= 400;
    }

    /**
     * Indica se è stata rilevata una transazione pendente.
     */
    public function getHadPendingTransactionAttribute(): bool
    {
        return !empty($this->transaction_status);
    }
}
