<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Get the database connection for the migration.
     */
    public function getConnection(): ?string
    {
        return config('logoperations.database_connection');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableName = config('logoperations.rules_table_name', 'log_operazioni_regole');

        if (!Schema::connection($this->getConnection())->hasTable($tableName)) {
            Schema::connection($this->getConnection())->create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('type', 32); // 'route', 'method', 'user_session'
                $table->string('target', 512); // rotta/pattern, Classe@metodo, o user_id
                $table->json('http_methods')->nullable(); // es. ["*"] o ["POST", "PUT"]
                $table->string('stack_level', 16)->default('base'); // 'base', 'core', 'full'
                $table->boolean('is_active')->default(true);
                $table->timestamp('expires_at')->nullable(); // per sessioni temporanee
                $table->json('metadata')->nullable(); // info addizionali (es. label, email utente)
                $table->timestamps();

                $table->index(['type', 'is_active']);
                $table->index('target');
                $table->index('expires_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('logoperations.rules_table_name', 'log_operazioni_regole');
        Schema::connection($this->getConnection())->dropIfExists($tableName);
    }
};
