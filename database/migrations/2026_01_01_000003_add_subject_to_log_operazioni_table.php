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

    public function up(): void
    {
        $table = $this->tableName();

        if (!Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table) {
            if (!Schema::hasColumn($table, 'subject_type')) {
                $blueprint->nullableMorphs('subject');
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
            if (Schema::hasColumn($table, 'subject_type')) {
                $blueprint->dropMorphs('subject');
            }
        });
    }
};
