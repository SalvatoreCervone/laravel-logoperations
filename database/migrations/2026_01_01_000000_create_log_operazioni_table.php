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
     * Crea la tabella dei log operazioni con supporto polimorfico,
     * stack trace JSON e tracciamento transazioni.
     */
    public function up(): void
    {
        if (Schema::hasTable($this->tableName())) {
            return;
        }

        Schema::create($this->tableName(), function (Blueprint $table) {

            $table->id();

            // Relazione utente polimorfica (user_id + user_type)
            // Supporta qualsiasi modello: User, Admin, Customer, etc.
            $table->nullableMorphs('user');

            // Relazione entità target polimorfica (subject_id + subject_type)
            // Supporta qualsiasi modello: Order, Invoice, Customer, Ticket, etc.
            $table->nullableMorphs('subject');

            // Rotta completa della richiesta HTTP
            $table->string('rotta', 1024);

            // Verbo HTTP (GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD)
            $table->string('verbo', 10)->index();

            // Controller@metodo associato alla rotta
            $table->string('controllermethod', 255)->nullable();

            // Codice di stato HTTP della risposta
            $table->integer('codicehttp')->index();

            // Indirizzo IP del client (supporto IPv4 e IPv6)
            $table->string('client_ip', 45)->nullable()->index();

            // Data e ora dell'operazione
            $table->timestamp('dataoperazione')->index();

            // Parametri della richiesta (POST, querystring, route params)
            // Tipo JSON per evitare il troncamento del vecchio string(255)
            $table->json('parametri')->nullable();

            // Messaggio di errore e/o stack trace dell'eccezione
            $table->longText('error')->nullable();

            // Stack trace delle chiamate interne (Livello 1 Core + Livello 2 Full)
            // Array JSON con flag is_core per ogni frame
            $table->json('stack_trace')->nullable();

            // Step e trace personalizzati registrati via LogOperations::step() / trace()
            $table->json('custom_traces')->nullable();

            // Nome dell'applicazione (per ambienti multi-app)
            $table->string('nomeapplicazione', 100)->index();

            // Durata della richiesta in millisecondi
            $table->integer('duration_ms')->nullable();

            // Stato transazione pendente rilevata dal middleware
            // Valori: null, 'rolled_back', 'committed', 'rolled_back_dangling_on_success'
            $table->string('transaction_status', 50)->nullable();

            // Livello di transazione al momento dell'intercettazione
            $table->integer('transaction_level')->nullable();

            $table->timestamps();

            // Indici composti per le query più frequenti
            $table->index(['dataoperazione', 'codicehttp']);
            $table->index(['nomeapplicazione', 'dataoperazione']);
        });
    }

    /**
     * Elimina la tabella dei log operazioni.
     */
    public function down(): void
    {
        Schema::dropIfExists($this->tableName());
    }
};
