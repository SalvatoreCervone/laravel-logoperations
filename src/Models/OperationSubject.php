<?php

namespace SalvatoreCervone\LogOperations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Modello Eloquent per i soggetti collegati a un'operazione di log.
 *
 * Ogni record rappresenta un modello Eloquent toccato (created/updated/deleted)
 * durante una singola richiesta HTTP tracciata.
 *
 * Relazioni:
 *   - log(): BelongsTo → OperationLog (il record di log principale)
 *   - subject(): MorphTo → il modello Eloquent toccato
 */
class OperationSubject extends Model
{
    /**
     * Solo created_at, senza updated_at.
     */
    const UPDATED_AT = null;

    /**
     * La tabella è configurabile tramite config/logoperations.php.
     */
    public function getTable(): string
    {
        return config('logoperations.subjects_table_name', 'log_operazioni_soggetti');
    }

    /**
     * La connessione è configurabile per isolare i log.
     */
    public function getConnectionName(): ?string
    {
        return config('logoperations.database_connection') ?? parent::getConnectionName();
    }

    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relazioni
    |--------------------------------------------------------------------------
    */

    /**
     * Il record di log principale a cui questo soggetto è collegato.
     */
    public function log(): BelongsTo
    {
        return $this->belongsTo(OperationLog::class, 'log_id');
    }

    /**
     * Relazione polimorfica al modello Eloquent toccato.
     */
    public function subject()
    {
        return $this->morphTo('subject');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Filtra per entità target polimorfica.
     */
    public function scopeForSubject(Builder $query, string $type, int|string|null $id = null): Builder
    {
        $query->where('subject_type', $type);
        if ($id !== null) {
            $query->where('subject_id', (string) $id);
        }
        return $query;
    }

    /**
     * Filtra per tipo di azione Eloquent.
     */
    public function scopeAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }
}
