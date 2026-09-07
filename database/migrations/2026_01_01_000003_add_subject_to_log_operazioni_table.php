<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migrazione incrementale per aggiungere il supporto al record target polimorfico (subject).
 *
 * Aggiunge:
 * - subject_type (string, nullable)
 * - subject_id (string/unsignedBigInteger, nullable)
 * - indice composto su ['subject_type', 'subject_id']
 */
return new class extends Migration
{
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

    public function up(): void
    {
        $table = $this->tableName();
        $schema = Schema::connection($this->getConnection());

        if (!$schema->hasTable($table)) {
            return;
        }

        $schema->table($table, function (Blueprint $blueprint) use ($table, $schema) {
            if (!$schema->hasColumn($table, 'subject_type')) {
                $blueprint->nullableMorphs('subject');
            }
        });
    }

    public function down(): void
    {
        $table = $this->tableName();
        $schema = Schema::connection($this->getConnection());

        if (!$schema->hasTable($table)) {
            return;
        }

        $schema->table($table, function (Blueprint $blueprint) use ($table, $schema) {
            if ($schema->hasColumn($table, 'subject_type')) {
                $blueprint->dropMorphs('subject');
            }
        });
    }
};
