<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabella relazionale per il tracciamento multi-soggetto.
 *
 * Ogni riga collega un record di log (`log_operazioni`) a un modello Eloquent
 * toccato durante la richiesta HTTP (created, updated, deleted).
 * Permette di avere la Storyboard bidirezionale per TUTTE le entità coinvolte,
 * non solo per il soggetto primario.
 */
return new class extends Migration
{
    protected function tableName(): string
    {
        return config('logoperations.subjects_table_name', 'log_operazioni_soggetti');
    }

    protected function parentTableName(): string
    {
        return config('logoperations.table_name', 'log_operazioni');
    }

    /**
     * Get the database connection for the migration.
     */
    public function getConnection(): ?string
    {
        return config('logoperations.database_connection');
    }

    public function up(): void
    {
        $schema = Schema::connection($this->getConnection());

        if ($schema->hasTable($this->tableName())) {
            return;
        }

        $schema->create($this->tableName(), function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('log_id');
            $table->foreign('log_id')
                  ->references('id')
                  ->on($this->parentTableName())
                  ->cascadeOnDelete();

            $table->string('subject_type', 255);
            $table->string('subject_id', 255);
            $table->string('action', 20); // created, updated, deleted, checkpoint

            $table->timestamp('created_at')->nullable();

            // Indice composto per query Storyboard ultra-veloci
            $table->index(['subject_type', 'subject_id'], 'los_subject_composite');
            $table->index('log_id', 'los_log_id');
        });
    }

    public function down(): void
    {
        $schema = Schema::connection($this->getConnection());
        $schema->dropIfExists($this->tableName());
    }
};
