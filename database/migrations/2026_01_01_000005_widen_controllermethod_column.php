<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allarga la colonna controllermethod a 500 caratteri per ospitare in sicurezza
 * namespace lunghi, callable e closure senza errori di troncamento SQL.
 */
return new class extends Migration
{
    protected function tableName(): string
    {
        return config('logoperations.table_name', 'log_operazioni');
    }

    public function getConnection(): ?string
    {
        return config('logoperations.database_connection');
    }

    public function up(): void
    {
        $table = $this->tableName();
        $schema = Schema::connection($this->getConnection());

        if (!$schema->hasTable($table) || !$schema->hasColumn($table, 'controllermethod')) {
            return;
        }

        $schema->table($table, function (Blueprint $blueprint) {
            $blueprint->string('controllermethod', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        $table = $this->tableName();
        $schema = Schema::connection($this->getConnection());

        if (!$schema->hasTable($table) || !$schema->hasColumn($table, 'controllermethod')) {
            return;
        }

        $schema->table($table, function (Blueprint $blueprint) {
            $blueprint->string('controllermethod', 255)->nullable()->change();
        });
    }
};
