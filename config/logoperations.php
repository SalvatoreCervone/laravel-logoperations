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
    | Modalità di Tracciamento
    |--------------------------------------------------------------------------
    |
    | Supporta tre modalità di funzionamento:
    |
    | 1. 'selective' (Consigliato con middleware globale):
    |    Il middleware è inserito a livello globale, ma NON logga nulla di default.
    |    Traccia SOLO le rotte esplicitamente attivate nel Tracking Studio
    |    o i gruppi di rotte a cui è applicato l'alias 'log.operations'.
    |    Ideale per evitare di tracciare tipologiche e consultazioni frequenti.
    |
    | 2. 'all' (Tracciamento a tappeto):
    |    Traccia tutte le rotte che corrispondono ai verbi HTTP consentiti,
    |    escluse solo quelle in 'excluded_routes'.
    |    ATTENZIONE: su ambienti di produzione ad alto traffico o con molte
    |    rotte di lookup (tipologiche), questa modalità comporta un carico
    |    pesante di memoria e rapido consumo di storage nel database.
    |
    */

    'mode' => env('LOG_OPERATIONS_MODE', 'selective'),

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
    | Usare ['*'] per intercettare tutti i verbi per le rotte monitorate.
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
    | Campionamento Richieste (Sampling Rate)
    |--------------------------------------------------------------------------
    |
    | Percentuale (da 0 a 100) delle richieste con esito positivo (2xx, 3xx)
    | da registrare. Gli errori (4xx, 5xx) e le eccezioni vengono SEMPRE
    | registrati al 100%. Impostare a 100 per registrare tutto.
    |
    */

    'sampling_rate' => (int) env('LOG_OPERATIONS_SAMPLING_RATE', 100),

    /*
    |--------------------------------------------------------------------------
    | Scrittura Asincrona tramite Coda (Queue) & Resilienza
    |--------------------------------------------------------------------------
    |
    | Se abilitato, il salvataggio dei log viene delegato a un worker di coda,
    | alleggerendo i server web ad alto volume.
    | Include fallback automatico (su DB o file di emergenza) in caso di
    | broker offline e alert email al superamento della soglia di fallimenti.
    |
    */

    'queue' => [
        'enabled' => env('LOG_OPERATIONS_QUEUE_ENABLED', false),
        'connection' => env('LOG_OPERATIONS_QUEUE_CONNECTION', null),
        'queue' => env('LOG_OPERATIONS_QUEUE_NAME', 'default'),
        'tries' => (int) env('LOG_OPERATIONS_QUEUE_TRIES', 3),
        'backoff' => [10, 30, 60],
        'failed_jobs_threshold' => (int) env('LOG_OPERATIONS_FAILED_THRESHOLD', 5),
        'alert_email' => env('LOG_OPERATIONS_ALERT_EMAIL', null),
    ],

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
    | Middleware delle Rotte API
    |--------------------------------------------------------------------------
    |
    | Middleware applicati a tutti gli endpoint REST del pacchetto (consultazione
    | log e Tracking Studio). In produzione è fortemente consigliato proteggere
    | queste rotte con autenticazione (es. ['api', 'auth:sanctum'] o ['web', 'auth']).
    |
    */

    'api_middleware' => ['api'],

    /*
    |--------------------------------------------------------------------------
    | Gate di Autorizzazione
    |--------------------------------------------------------------------------
    |
    | Nome del Gate Laravel utilizzato per autorizzare l'accesso agli endpoint
    | di consultazione e al Tracking Studio. Se impostato, il pacchetto verificherà
    | Gate::allows($gate, [$request->user()]).
    |
    | Se 'allow_in_local' è true, l'accesso è sempre consentito in ambiente 'local'.
    |
    */

    'gate' => env('LOG_OPERATIONS_GATE', 'viewLogOperations'),

    'allow_in_local' => env('LOG_OPERATIONS_ALLOW_IN_LOCAL', true),

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
        'logoperations*',
        'log-operations*',
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
        'only_on_error' => env('LOG_OPERATIONS_STACK_ONLY_ON_ERROR', true),

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
        'current_password',
        'new_password',
        'old_password',
        'pin',
        'passcode',
        'token',
        'access_token',
        'refresh_token',
        'auth_token',
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
    | Privacy, GDPR & Anonimizzazione Dati (Regolamento UE 2016/679)
    |--------------------------------------------------------------------------
    |
    | Opzioni per la conformità alla normativa europea sulla protezione dei dati.
    | Di default, l'anonimizzazione IP è disabilitata per consentire audit di
    | sicurezza completi, ma può essere attivata con LOG_OPERATIONS_ANONYMIZE_IP=true.
    |
    */

    'privacy' => [

        // Se abilitato, maschera l'ultimo ottetto degli indirizzi IPv4 (es. 192.168.1.xxx)
        // e la seconda metà degli indirizzi IPv6 rendendoli dati non identificabili.
        'anonymize_ip' => env('LOG_OPERATIONS_ANONYMIZE_IP', false),

        // Valore o maschera per l'ultimo blocco ('xxx' oppure '0')
        'anonymize_ip_mask' => env('LOG_OPERATIONS_IP_MASK', 'xxx'),

        // Se abilitato, registra anche gli header HTTP della richiesta (sanitizzati)
        'log_headers' => env('LOG_OPERATIONS_LOG_HEADERS', false),

        // Header HTTP sensibili mascherati automaticamente
        'mask_headers' => [
            'authorization',
            'cookie',
            'set-cookie',
            'x-xsrf-token',
            'x-csrf-token',
            'php-auth-pw',
            'php-auth-user',
        ],

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
    | Dashboard Web Standalone
    |--------------------------------------------------------------------------
    |
    | Configurazione per la dashboard grafica integrata nel pacchetto.
    | Non richiede build frontend, compilatori npm o configurazioni esterne.
    |
    */

    'dashboard' => [
        // Abilita o disabilita la rotta web autonoma
        'enabled' => env('LOG_OPERATIONS_DASHBOARD_ENABLED', true),

        // Percorso URI della dashboard web (es. /logoperations)
        'route' => env('LOG_OPERATIONS_DASHBOARD_ROUTE', 'logoperations'),

        // Middleware applicati alla rotta della dashboard web
        'middleware' => ['web'],

        // Numero predefinito di elementi per pagina
        'per_page' => 20,

        // Opzioni selezionabili per elementi per pagina
        'per_page_options' => [15, 20, 25, 50, 100],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sistema di Alerting e Notifiche Multi-Canale
    |--------------------------------------------------------------------------
    |
    | Notifiche in tempo reale per eventi critici (rollback pendenti, picchi
    | di errore 5xx, fallimenti di scrittura coda).
    | Canali supportati: 'mail', 'slack', 'discord', 'webhook'.
    |
    */

    'alerts' => [
        'enabled' => env('LOG_OPERATIONS_ALERTS_ENABLED', false),

        // Canali attivi per l'invio delle notifiche
        'channels' => ['mail', 'slack', 'discord', 'webhook'],

        // Configurazione Email
        'mail' => [
            'to' => env('LOG_OPERATIONS_ALERT_EMAIL', null),
        ],

        // Configurazione Slack Webhook
        'slack' => [
            'webhook_url' => env('LOG_OPERATIONS_SLACK_WEBHOOK', null),
        ],

        // Configurazione Discord Webhook
        'discord' => [
            'webhook_url' => env('LOG_OPERATIONS_DISCORD_WEBHOOK', null),
        ],

        // Configurazione Webhook generico
        'webhook' => [
            'url' => env('LOG_OPERATIONS_ALERT_WEBHOOK', null),
            'secret' => env('LOG_OPERATIONS_ALERT_WEBHOOK_SECRET', null),
        ],

        // Finestra di silenzio anti-flood in minuti per lo stesso canale ed evento
        'throttle_minutes' => (int) env('LOG_OPERATIONS_ALERT_THROTTLE', 15),

        // Notifica automatica immediata in caso di rollback di transazione non gestita
        'notify_on_rollback' => env('LOG_OPERATIONS_ALERT_ON_ROLLBACK', true),

        // Regole per il rilevamento picchi di errore (comando logoperations:check-alerts)
        'error_rate' => [
            'window_minutes' => (int) env('LOG_OPERATIONS_ALERT_WINDOW', 5),
            'threshold_percentage' => (float) env('LOG_OPERATIONS_ALERT_THRESHOLD', 10.0),
            'min_requests' => (int) env('LOG_OPERATIONS_ALERT_MIN_REQUESTS', 20),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention Policy, Salvaguardia Storyboard & Manutenzione
    |--------------------------------------------------------------------------
    |
    | LogOperations funge da Audit Trail di sistema: per default la cancellazione
    | automatica è DISABILITATA ('enabled' => false).
    | Se abilitata o eseguita manualmente via 'php artisan logoperations:prune',
    | tutti i record facenti parte di una Storyboard (subject) ed eventuali errori
    | sono rigorosamente protetti ed esclusi da qualsiasi cancellazione.
    |
    */

    'retention' => [
        // Disabilitata di default per massima sicurezza dei dati storici
        'enabled' => env('LOG_OPERATIONS_RETENTION_ENABLED', false),

        // Giorni di conservazione predefiniti se abilitata
        'days' => (int) env('LOG_OPERATIONS_RETENTION_DAYS', 365),

        // Salvaguardia Storyboard: esclude SEMPRE qualsiasi record legato a un'entità
        'preserve_storyboards' => true,

        // Salvaguardia Errori: esclude SEMPRE errori 4xx/5xx ed eccezioni
        'preserve_errors' => true,

        // Salvaguardia Scritture: esclude richieste di mutazione dati (POST, PUT, DELETE)
        'preserve_mutations' => true,

        // Dimensione dei blocchi per cancellazioni sicure senza lock del DB
        'chunk_size' => (int) env('LOG_OPERATIONS_PRUNE_CHUNK_SIZE', 1000),
    ],

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
