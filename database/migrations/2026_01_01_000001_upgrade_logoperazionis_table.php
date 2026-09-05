<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migrazione di aggiornamento per installazioni con la vecchia tabella 'logoperazionis'.
 *
 * Aggiunge le colonne mancanti:
 * - user_type (per supporto polimorfico)
 * - stack_trace, custom_traces (tracciamento a 2 livelli)
 * - duration_ms (latenza)
 * - transaction_status, transaction_level (gestione transazioni)
 * - nomeapplicazione (se mancante)
 *
 * E converte:
 * - parametri da VARCHAR(255) a JSON/LONGTEXT
 * - error da VARCHAR a LONGTEXT (se necessario)
 */
return new class extends Migration
{
    protected function tableName(): string
    {
        return config('logoperations.table_name', 'log_operazioni');
    }

    public function up(): void
    {
        $table = $this->tableName();

        // Esegui solo se la tabella esiste già (è un upgrade, non una creazione)
        if (!Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table) {

            // Aggiunta del campo user_type per relazione polimorfica
            if (!Schema::hasColumn($table, 'user_type')) {
                $blueprint->string('user_type')->nullable()->after('user_id');
                $blueprint->index(['user_type', 'user_id']);
            }

            // Conversione parametri da string(255) a longText se necessario
            // Nota: il tipo JSON richiede che il contenuto sia valido JSON.
            // Usiamo longText per compatibilità con dati esistenti non-JSON.
            if (Schema::hasColumn($table, 'parametri')) {
                $blueprint->longText('parametri')->nullable()->change();
            }

            // Conversione error a longText se presente
            if (Schema::hasColumn($table, 'error')) {
                $blueprint->longText('error')->nullable()->change();
            }

            // Stack trace chiamate interne (JSON)
            if (!Schema::hasColumn($table, 'stack_trace')) {
                $blueprint->json('stack_trace')->nullable();
            }

            // Step e trace personalizzati
            if (!Schema::hasColumn($table, 'custom_traces')) {
                $blueprint->json('custom_traces')->nullable();
            }

            // Durata richiesta in ms
            if (!Schema::hasColumn($table, 'duration_ms')) {
                $blueprint->integer('duration_ms')->nullable();
            }

            // Stato della transazione intercettata
            if (!Schema::hasColumn($table, 'transaction_status')) {
                $blueprint->string('transaction_status', 50)->nullable();
            }

            // Livello di transazione al momento dell'intercettazione
            if (!Schema::hasColumn($table, 'transaction_level')) {
                $blueprint->integer('transaction_level')->nullable();
            }

            // Nome applicazione (aggiunto successivamente nelle vecchie installazioni)
            if (!Schema::hasColumn($table, 'nomeapplicazione')) {
                $blueprint->string('nomeapplicazione', 100)
                    ->default('legacy')
                    ->nullable(false)
                    ->index();
            }

            // Client IP se mancante
            if (!Schema::hasColumn($table, 'client_ip')) {
                $blueprint->string('client_ip', 45)->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        $table = $this->tableName();

        if (!Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table) {
            $columns = [
                'user_type', 'stack_trace', 'custom_traces',
                'duration_ms', 'transaction_status', 'transaction_level',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $blueprint->dropColumn($column);
                }
            }
        });
    }
};
