<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Abilitazione Globale
    |--------------------------------------------------------------------------
    |
    | Imposta su false per disattivare completamente il logging delle
    | operazioni senza rimuovere il middleware dalla pipeline.
    |
    */

    'enabled' => env('LOG_OPERATIONS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Nome Tabella
    |--------------------------------------------------------------------------
    |
    | Il nome della tabella dove verranno salvati i log.
    | Default: 'log_operazioni'. Cambiare in 'logoperazionis' se si dispone
    | già della vecchia tabella con dati di produzione.
    |
    */

    'table_name' => env('LOG_OPERATIONS_TABLE', 'log_operazioni'),

    /*
    |--------------------------------------------------------------------------
    | Nome Tabella Regole Dinamiche
    |--------------------------------------------------------------------------
    |
    | Tabella per la persistenza delle regole di tracciamento Zero-Code.
    |
    */

    'rules_table_name' => env('LOG_OPERATIONS_RULES_TABLE', 'log_operazioni_regole'),

    /*
    |--------------------------------------------------------------------------
    | Connessione Database Dedicata
    |--------------------------------------------------------------------------
    |
    | Nome della connessione database da utilizzare per il salvataggio
    | dei log. Impostare su null per usare la connessione predefinita.
    | Una connessione dedicata garantisce l'isolamento rispetto alle
    | transazioni dell'applicazione.
    |
    */

    'database_connection' => env('LOG_OPERATIONS_DB_CONNECTION', null),

    /*
    |--------------------------------------------------------------------------
    | Verbi HTTP Consentiti
    |--------------------------------------------------------------------------
    |
    | Array dei metodi HTTP che il middleware deve intercettare.
    | Usare ['*'] per intercettare tutti i verbi.
    | Esempio specifico: ['POST', 'PUT', 'PATCH', 'DELETE']
    |
    */

    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Codici di Stato HTTP Esclusi
    |--------------------------------------------------------------------------
    |
    | Array di codici di stato HTTP per i quali non verrà generato
    | alcun record di log (es. 422 per errori di validazione).
    |
    */

    'excluded_status_codes' => [422],

    /*
    |--------------------------------------------------------------------------
    | Prefisso Rotte API
    |--------------------------------------------------------------------------
    |
    | Prefisso per gli endpoint REST esposti dal pacchetto.
    |
    */

    'api_prefix' => env('LOG_OPERATIONS_API_PREFIX', 'api/logoperations'),

    /*
    |--------------------------------------------------------------------------
    | Rotte Escluse
    |--------------------------------------------------------------------------
    |
    | Pattern di rotte da escludere dal logging. Accetta wildcard (*).
    | Le rotte API del pacchetto stesso vengono escluse automaticamente
    | per evitare loop di auto-logging.
    |
    */

    'excluded_routes' => [
        'api/logoperations*',
        'api/log-operations*',
        'telescope*',
        '_debugbar*',
        'horizon*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracciamento Stack a 2 Livelli
    |--------------------------------------------------------------------------
    |
    | Livello 1 (Core): solo le chiamate a funzioni/classi del codice
    |   proprietario dell'applicazione (es. app/).
    | Livello 2 (Full): l'intero stack, incluse le chiamate interne di
    |   Laravel (Illuminate/...) e dei package di terze parti (vendor/).
    |
    | Il campo 'default_view' controlla quale livello viene mostrato
    | di default nel componente Vue (l'utente può sempre commutare).
    |
    */

    'stack_trace' => [

        // Abilita il tracciamento dello stack delle chiamate
        'enabled' => env('LOG_OPERATIONS_STACK_ENABLED', true),

        // Se true, lo stack viene registrato solo su risposte di errore (>= 400)
        'only_on_error' => false,

        // Vista di default nel componente Vue: 'core' oppure 'full'
        'default_view' => 'core',

        // Percorsi considerati codice proprietario (Livello 1 - Core)
        // Ogni frame il cui file inizia con uno di questi percorsi
        // viene classificato come is_core = true
        'project_paths' => [
            'app/',
            'routes/',
        ],

        // Percorsi da escludere dallo stack (anche dal livello Full)
        'exclude_paths' => [
            'vendor/laravel/framework/src/Illuminate/Routing/Pipeline.php',
        ],

        // Numero massimo di frame da salvare per evitare payload eccessivi
        'max_frames' => 100,

        // Rileva quale funzione del codice applicativo ha originato le query DB
        'trace_db_callers' => true,

    ],

    /*
    |--------------------------------------------------------------------------
    | Gestione Transazioni Pendenti
    |--------------------------------------------------------------------------
    |
    | Il middleware controlla DB::transactionLevel() dopo la risposta.
    | Se ci sono transazioni non terminate (livello > 0), interviene
    | per prevenire lock persistenti e dati non reali (non committed).
    |
    */

    'transactions' => [

        // Abilita il controllo sulle transazioni pendenti
        'manage_unfinished' => true,

        // Se la risposta è un errore (>= 400) e ci sono transazioni aperte:
        // esegue il rollback ciclico di tutti i livelli
        'rollback_on_error' => true,

        // Se la risposta è di successo (< 400) ma ci sono transazioni aperte:
        // true  = esegue il commit (i dati diventano definitivi)
        // false = esegue il rollback (precauzione, i dati non sono affidabili
        //         se la transazione non è stata chiusa esplicitamente)
        'commit_on_success' => false,

        // Traccia lo stato della transazione nel record di log
        // (campo transaction_status: 'rolled_back', 'committed', etc.)
        'log_transaction_state' => true,

    ],

    /*
    |--------------------------------------------------------------------------
    | Mascheramento Campi Sensibili
    |--------------------------------------------------------------------------
    |
    | Elenco dei nomi di campo i cui valori verranno sostituiti con
    | '***MASKED***' prima del salvataggio, sia nei parametri POST
    | che nei parametri route e query string.
    | La ricerca è case-insensitive e ricorsiva su array annidati.
    |
    */

    'mask_fields' => [
        'password',
        'password_confirmation',
        'token',
        'secret',
        'authorization',
        'card_number',
        'credit_card',
        'cvv',
        'numero_carta',
        'cvv_sicurezza',
        'card_token',
        'api_key',
        'api_secret',
    ],

    /*
    |--------------------------------------------------------------------------
    | Campi Ricercabili sull'Utente Polimorfico
    |--------------------------------------------------------------------------
    |
    | Colonne sulla tabella dell'utente autenticato sulle quali il
    | controller eseguirà la ricerca testuale (LIKE).
    | Vengono verificate dinamicamente: se una colonna non esiste
    | nella tabella, viene silenziosamente ignorata.
    |
    */

    'user_search_fields' => ['name', 'cognome', 'email'],

    /*
    |--------------------------------------------------------------------------
    | Nome Applicazione
    |--------------------------------------------------------------------------
    |
    | Identificativo dell'applicazione che genera i log.
    | Utile in ambienti multi-applicazione che condividono la stessa
    | tabella di log.
    |
    */

    'app_name' => env('LOG_OPERATIONS_APP_NAME', env('APP_NAME', 'laravel')),

];
