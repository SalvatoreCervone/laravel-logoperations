<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nome della tabella configurabile tramite config/logoperations.php.
     */
    protected function tableName(): string
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

    /**
     * Aggiunge l'indice composito per massimizzare le prestazioni di paginazione
     * e ordinamento deterministico (dataoperazione DESC, id DESC).
     */
    public function up(): void
    {
        $schema = Schema::connection($this->getConnection());

        if (!$schema->hasTable($this->tableName())) {
            return;
        }

        $schema->table($this->tableName(), function (Blueprint $table) {
            $table->index(['dataoperazione', 'id'], 'idx_log_op_pagination');
        });
    }

    /**
     * Rimuove l'indice composito di paginazione.
     */
    public function down(): void
    {
        $schema = Schema::connection($this->getConnection());

        if (!$schema->hasTable($this->tableName())) {
            return;
        }

        $schema->table($this->tableName(), function (Blueprint $table) {
            $table->dropIndex('idx_log_op_pagination');
        });
    }
};
