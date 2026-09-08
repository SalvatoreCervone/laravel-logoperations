# Log Operations - Pacchetto Laravel + Vue 3

Pacchetto Composer Laravel per il **tracciamento, monitoraggio e analisi delle operazioni applicative** con gestione intelligente delle transazioni database, stack trace a 2 livelli e **Centro di Controllo Zero-Code (Tracking Studio)** per attivare i log senza modificare codice.

---

## 🚀 Caratteristiche Principali

- 🖥️ **Dashboard Web Standalone Integrata (`/logoperations`)**:
  - Interfaccia web moderna ad alta risoluzione (tema scuro, Vanilla CSS + Vue 3 standalone) accessibile direttamente via browser.
  - **Zero dipendenze npm / Vite**: funziona istantaneamente su qualsiasi installazione Laravel senza dover configurare build tools frontend.
  - **Paginazione Server-Side Reale**: navigazione deterministica `(dataoperazione DESC, id DESC)`, indice composito DB ad alte prestazioni e selettore `per_page` (15, 20, 25, 50, 100).
- 📥 **Export Massivo Streaming (O(1) Memoria)**:
  - Download di grandi moli di log in formato **CSV** (con UTF-8 BOM per compatibilità Microsoft Excel) o **JSON**.
  - Utilizzo di generatori e streaming HTTP lazily-loaded per esportare centinaia di migliaia di record con consumo di RAM costante e minimo.
  - Preservazione di tutti i filtri di ricerca applicati (verbi, status HTTP, utenti, date, slow query, rollback).
- 🚨 **Sistema di Alerting Multi-Canale & Anti-Flood**:
  - Notifiche in tempo reale su 4 canali: **Email**, **Slack** (rich blocks), **Discord** (rich embeds) e **Webhook generico** (con firma crittografica HMAC).
  - Allarme immediato su **Rollback di transazioni SQL pendenti** lasciate aperte dal codice applicativo.
  - Rilevamento automatico di **picchi del tasso di errore** (> soglia % su finestra temporale) tramite comando Artisan `logoperations:check-alerts`.
  - Meccanismo di **throttle anti-flood** basato su cache Laravel per prevenire spam e saturazione dei canali.
- 📖 **Storyboard del Record (Timeline di Vita & Audit Trail)**:
  - Tracciamento cronologico e deterministico del ciclo di vita dei modelli Eloquent (`Order`, `Invoice`, `Ticket`, ecc.) tramite Route Model Binding e trait `HasOperationLogs`.
  - Componente Vue autonomo `<LogStoryboard />` e API REST dedicate per visualizzare l'intera storia di un'entità con classificazione eventi (`create`, `update`, `delete`, `checkpoint`, `error`).
- 🎛️ **Centro di Controllo Zero-Code (Tracking Studio)**:
  - 🗺️ **Studio Rotte**: scansione automatica delle rotte web e API con attivazione/disattivazione permanente a 1 click (ON/OFF) e selezione del livello di dettaglio.
  - ⚙️ **Studio Funzioni & Dynamic Proxy**: scansione delle classi di servizio in `app/` e intercettazione dei metodi con **piena compatibilità di tipo per la Dependency Injection** (`ProxyClassGenerator`).
  - 🏷️ **Attributo PHP 8 `#[Traceable]`**: tracciamento dichiarativo a zero configurazione DB su classi o singoli metodi.
  - 👤 **Monitor Utente Live a Tempo**: tracciamento mirato di un singolo utente per una finestra temporale (5, 15, 30, 60 minuti) con countdown live e disattivazione automatica.
- ⚡ **Ottimizzazione Prestazioni & Scalabilità**:
  - **Terminable Middleware**: la scrittura su DB avviene *dopo* l'invio della risposta HTTP al client (`terminate()`), azzerando la latenza percepita.
  - **Logging Asincrono su Coda**: supporto per driver di coda Laravel (`ProcessOperationLog`) con tentativi, backoff, fallback su file di emergenza e alert email automatico su soglia di fallimenti.
  - **Campionamento Richieste (Sampling Rate)**: configurazione da 0 a 100% per le chiamate di successo (gli errori HTTP >= 400 sono sempre tracciati al 100%).
- 🛡️ **Privacy, Conformità GDPR & Diritto all'Oblio**:
  - **Anonimizzazione Indirizzi IP**: mascheramento configurabile di IPv4 (`192.168.1.xxx` o `192.168.1.0`) e IPv6.
  - **Sanitizzazione Header & Token**: mascheramento automatico di header di autorizzazione (`Authorization`, `Cookie`, `X-XSRF-TOKEN`, credenziali basic auth).
  - **GDPR Art. 17 (Right to be Forgotten)**: cancellazione fisica o soft scrubbing (anonimizzazione PII mantenendo metadati tecnici) via Facade e comando Artisan `php artisan logoperations:forget-user`.
- 🔄 **Rollback Ciclico Transazioni**: rileva transazioni SQL rimaste aperte a causa di errori e le annulla automaticamente fino a livello 0, garantendo l'integrità del database.
- 📚 **Stack Trace a 2 Livelli**:
  - **🎯 Livello Core**: isola e mostra solo i file del progetto (cartella `app/`), filtrando il rumore del framework.
  - **🔍 Livello Completo**: visualizza l'intera catena di chiamate incluse librerie vendor e framework.
- 📊 **Componenti Frontend Vue 3**: libreria modulare di componenti pronti all'uso per SPA separate o progetti Inertia.

---

## 📦 Installazione

### 1. Includere il pacchetto nel progetto

```bash
composer require salvatorecervone/logoperations
```

Il `LogOperationsServiceProvider` e la facade `LogOperations` vengono registrati automaticamente tramite package auto-discovery.

### 2. Pubblicare configurazione, migrazioni e viste

```bash
# Configurazione
php artisan vendor:publish --tag=logoperations-config

# Migrazioni DB
php artisan vendor:publish --tag=logoperations-migrations

# Viste Blade della Dashboard autonoma (opzionale)
php artisan vendor:publish --tag=logoperations-views

# Componenti Vue 3 per SPA (opzionale)
php artisan vendor:publish --tag=logoperations-vue
```

### 3. Eseguire le migrazioni

```bash
php artisan migrate
```

Verranno create le tabelle:
- `log_operazioni`: archivio storico con supporto polimorfico (`user` e `subject`) e indice composito per la paginazione `(dataoperazione, id)`.
- `log_operazioni_regole`: regole dinamiche configurate da interfaccia per rotte, metodi e sessioni utente.

---

## ⚙️ Configurazione Completa (`config/logoperations.php`)

Il file `config/logoperations.php` offre il controllo granulare su ogni aspetto del pacchetto. Di seguito la configurazione completa annotata:

```php
return [
    // --------------------------------------------------------------------------
    // Abilitazione Globale & Modalità di Tracciamento
    // --------------------------------------------------------------------------
    'enabled' => env('LOG_OPERATIONS_ENABLED', true),

    // 'selective' (default consigliato): traccia solo rotte attivate in Studio o con alias
    // 'all': traccia a tappeto tutte le rotte ammesse
    'mode' => env('LOG_OPERATIONS_MODE', 'selective'),

    // Paracadute Errori 500: in modalità 'selective', cattura comunque crash ed eccezioni 500 anche su rotte non monitorate
    'log_uncaught_errors' => env('LOG_OPERATIONS_LOG_UNCAUGHT_ERRORS', true),

    // --------------------------------------------------------------------------
    // Nomi Tabelle Database & Connessione
    // --------------------------------------------------------------------------
    // Tabella principale per i log operativi, errori e Storyboard polimorfica
    'table_name' => env('LOG_OPERATIONS_TABLE', 'log_operazioni'),

    // Tabella per la memorizzazione delle regole del Tracking Studio (rotte, proxy metodi, sessioni)
    'rules_table_name' => env('LOG_OPERATIONS_RULES_TABLE', 'log_operazioni_regole'),

    // Connessione DB dedicata (null = connessione predefinita di Laravel; es. 'mysql_logs' o 'sqlite_logs')
    'database_connection' => env('LOG_OPERATIONS_DB_CONNECTION', null),

    // --------------------------------------------------------------------------
    // Filtri Richieste & Campionamento (Sampling)
    // --------------------------------------------------------------------------
    // Verbi HTTP monitorati: ['*'] per tutti i verbi sulle rotte selezionate
    // oppure lista specifica come ['POST', 'PUT', 'PATCH', 'DELETE']
    'allowed_methods' => ['*'],

    // Codici di stato esclusi dal logging (es. errori di validazione form)
    'excluded_status_codes' => [422],

    // Rotte escluse (per evitare loop di auto-logging)
    'excluded_routes' => ['api/logoperations*', 'api/log-operations*', 'telescope*'],

    // Percentuale di campionamento chiamate riuscite 2xx (0-100%). Gli errori >= 400 sono sempre loggati al 100%
    'sampling_rate' => env('LOG_OPERATIONS_SAMPLING_RATE', 100),

    // --------------------------------------------------------------------------
    // Elaborazione Asincrona su Coda (Queue Worker)
    // --------------------------------------------------------------------------
    'queue' => [
        'enabled' => env('LOG_OPERATIONS_QUEUE_ENABLED', false),
        'connection' => env('LOG_OPERATIONS_QUEUE_CONNECTION', null),
        'queue' => env('LOG_OPERATIONS_QUEUE_NAME', 'log-operations'),
        'tries' => 3,
        'backoff' => [5, 10, 30],
        'failed_jobs_threshold' => 5,
        'alert_email' => env('LOG_OPERATIONS_QUEUE_ALERT_EMAIL', null),
    ],

    // --------------------------------------------------------------------------
    // API REST & Sicurezza Accesso
    // --------------------------------------------------------------------------
    'api_prefix' => env('LOG_OPERATIONS_API_PREFIX', 'api/logoperations'),
    'api_middleware' => ['api'],
    'gate' => 'viewLogOperations',
    'allow_in_local' => true,

    // --------------------------------------------------------------------------
    // Stack Trace Intelligente a Due Livelli
    // --------------------------------------------------------------------------
    'stack_trace' => [
        'enabled' => env('LOG_OPERATIONS_STACK_ENABLED', true),
        'only_on_error' => env('LOG_OPERATIONS_STACK_ONLY_ON_ERROR', true), // Salva stack trace solo su errori (>= 400)
        'default_view' => 'core',         // 'core' (solo app/) o 'full' (intero albero vendor)
        'project_paths' => ['app/'],      // Percorsi considerati codice applicativo proprietario
        'exclude_paths' => ['vendor/'],
        'max_frames' => 100,
        'trace_db_callers' => true,
    ],

    // --------------------------------------------------------------------------
    // Gestione & Ripristino Automatico Transazioni DB
    // --------------------------------------------------------------------------
    'transactions' => [
        'manage_unfinished' => true,
        'rollback_on_error' => true,      // Esegue rollback di sicurezza su HTTP >= 400
        'commit_on_success' => false,     // false = sicurezza preventiva anche su esito 200
        'log_transaction_state' => true,
    ],

    // --------------------------------------------------------------------------
    // Privacy, GDPR & Mascheramento Dati Sensibili
    // --------------------------------------------------------------------------
    'mask_fields' => ['password', 'password_confirmation', 'token', 'secret', 'authorization', 'credit_card'],
    'privacy' => [
        'anonymize_ip' => env('LOG_OPERATIONS_ANONYMIZE_IP', false),
        'anonymize_ip_mask' => 'xxx',     // Mascheratura ultimo ottetto IPv4 ('xxx' o '0')
        'log_headers' => false,
        'mask_headers' => ['authorization', 'php-auth-pw', 'cookie', 'x-csrf-token', 'x-xsrf-token'],
    ],

    // Colonne da interrogare per la ricerca utenti nel Tracking Studio
    'user_search_fields' => ['name', 'cognome', 'email'],

    // --------------------------------------------------------------------------
    // Dashboard Web Standalone
    // --------------------------------------------------------------------------
    'dashboard' => [
        'enabled' => env('LOG_OPERATIONS_DASHBOARD_ENABLED', true),
        'route' => 'logoperations',
        'middleware' => ['web'],
        'per_page' => 20,
        'per_page_options' => [15, 20, 25, 50, 100],
    ],

    // --------------------------------------------------------------------------
    // Sistema di Allarmi & Notifiche Multi-Canale
    // --------------------------------------------------------------------------
    'alerts' => [
        'enabled' => env('LOG_OPERATIONS_ALERTS_ENABLED', false),
        'channels' => ['mail', 'slack', 'discord', 'webhook'],
        'mail' => ['to' => env('LOG_OPERATIONS_ALERT_EMAIL', null)],
        'slack' => ['webhook_url' => env('LOG_OPERATIONS_SLACK_WEBHOOK', null)],
        'discord' => ['webhook_url' => env('LOG_OPERATIONS_DISCORD_WEBHOOK', null)],
        'webhook' => [
            'url' => env('LOG_OPERATIONS_ALERT_WEBHOOK', null),
            'secret' => env('LOG_OPERATIONS_ALERT_WEBHOOK_SECRET', null),
        ],
        'throttle_minutes' => 15,
        'notify_on_rollback' => true,
        'error_rate' => [
            'window_minutes' => 5,
            'threshold_percentage' => 10.0,
            'min_requests' => 20,
        ],
    ],

    // --------------------------------------------------------------------------
    // Retention Policy & Salvaguardia Audit Trail
    // --------------------------------------------------------------------------
    'retention' => [
        'enabled' => env('LOG_OPERATIONS_RETENTION_ENABLED', false),
        'days' => env('LOG_OPERATIONS_RETENTION_DAYS', 90),
        'preserve_storyboards' => true,   // Protegge al 100% tutti i record con subject_id
        'preserve_errors' => true,        // Preserva tutti i log con codice HTTP >= 400
        'preserve_mutations' => false,    // true = preserva anche POST/PUT/DELETE
        'chunk_size' => 1000,
    ],

    // Nome identificativo dell'applicazione (per ambienti multi-app o microservizi)
    'app_name' => env('LOG_OPERATIONS_APP_NAME', env('APP_NAME', 'laravel')),
];
```

---

## 🎛️ Centro di Controllo Zero-Code (Tracking Studio)

Il Tracking Studio consente a operatori, sviluppatori e team di supporto di attivare il tracciamento direttamente dall'interfaccia grafica:

### 1. Studio Rotte (Pagine Web & API)
* Il sistema interroga `Route::getRoutes()` e genera l'albero completo delle rotte registrate nel progetto (divise per Web, API, Admin).
* Con un interruttore a un click (**ON / OFF**) è possibile attivare il tracciamento permanente su una specifica rotta.
* Per ciascuna rotta attiva è possibile selezionare il **Livello di Dettaglio**:
  * **Base**: salva parametri, IP, durata e status HTTP.
  * **🎯 Core (Consigliato)**: salva i dati base ed evidenzia solo i file in `app/` nello stack trace.
  * **🔍 Completo**: include l'intero albero di esecuzione con chiamate interne del framework e dei vendor.
* Le modifiche sono memorizzate in cache in tempo reale e non richiedono modifiche ai file di routing o nuovi deploy.

### 2. Studio Funzioni & Servizi (Metodi PHP)
* Scansiona automaticamente le classi di servizio e logica business in `app/`.
* Cliccando sul toggle di un metodo, il pacchetto registra un **Proxy Dinamico** sul Service Container di Laravel (`MethodInterceptor`).
* Ogni esecuzione del metodo cattura automaticamente argomenti, tempo impiegato ed eventuali eccezioni sollevate.

### 3. Monitoraggio Utente Live a Tempo
* Consente di mettere sotto osservazione un utente specifico selezionando la durata desiderata (**5, 15, 30 o 60 minuti**).
* Durante la sessione, tutte le azioni compiute dall'utente vengono registrate con il massimo livello di dettaglio.
* Un **countdown live** mostra il tempo residuo, con possibilità di arresto anticipato a 1 click.
* Scaduti i minuti, il monitoraggio si disattiva da solo a costo zero sulle risorse.

---

## 🚦 Modalità di Utilizzo del Middleware

Il pacchetto supporta **3 diverse modalità di utilizzo** per adattarsi a qualsiasi architettura applicativa:

---

### 🎛️ Modalità 1: Globale Selettivo (Zero-Code da Pannello) — *Consigliata e Default in v1.3+*
Registri il middleware a livello globale con il default di fabbrica `'mode' => 'selective'`.
- **Comportamento**: Di default **non logga alcuna rotta standard** con esito 200/300/404.
- **Controllo Zero-Code**: Apri il **Tracking Studio** e attivi `[ON]` con 1 click solo le rotte o metodi che ti interessano.
- **Paracadute Errori 500 (Safety Net)**: Se si verifica un'eccezione non gestita o un crash `HTTP 500` anche su una rotta non monitorata, il middleware la cattura automaticamente con l'intero stack trace, garantendo che nessun disservizio passi inosservato (disattivabile con `LOG_OPERATIONS_LOG_UNCAUGHT_ERRORS=false`).
- **Vantaggi**: Zero modifiche al codice, tipologiche e polling esclusi di default, database sempre leggero e pulito.

---

### 🎯 Modalità 2: Mirato da Codice (Gruppi o Singole Rotte)
Applichi il middleware direttamente alle rotte o ai gruppi di risorse business. Sono supportati indifferentemente sia l'alias testuale sia la classe (consigliata per Laravel 11/12):

```php
use SalvatoreCervone\LogOperations\Http\Middleware\LogOperationsMiddleware;

// 🚫 Rotte tipologiche / lookup / statiche: NESSUN LOG
Route::prefix('tipologiche')->group(function () {
    Route::get('stati-ordine', [LookupController::class, 'orderStatuses']);
    Route::get('comuni', [LookupController::class, 'cities']);
});

// ✅ Rotte operative di business: MONITORATE per la Storyboard
// Supporta sia l'alias 'log.operations' sia la classe LogOperationsMiddleware::class:
Route::middleware(LogOperationsMiddleware::class)->group(function () {
    Route::apiResource('ordini', OrderController::class);
    Route::apiResource('fatture', InvoiceController::class);
    Route::post('pagamenti/checkout', [PaymentController::class, 'checkout']);
});
```

> [!TIP]
> **Best Practice per `Route::resource` vs `Route::apiResource`:**
> Se utilizzi `Route::resource()` per viste web HTML tradizionali insieme a `allowed_methods => ['*']`, verranno intercettate anche le semplici visualizzazioni dei form HTML vuoti (`GET /ordini/create`, `GET /ordini/{id}/edit`).
> Per evitarlo, prediligi `Route::apiResource()` per gli endpoint REST, oppure aggiungi `'*/create'` e `'*/edit'` in `excluded_routes`.

---

### 🌊 Modalità 3: Globale a Tappeto (`mode => 'all'`)
Registri il middleware globale e mantieni `'mode' => 'all'`. Il sistema intercetta tutte le rotte che corrispondono ai verbi HTTP consentiti, escluse solo quelle in `excluded_routes`.

> [!WARNING]
> **Attenzione al consumo di memoria e storage:**
> Su applicazioni reali ad alto traffico o con molteplici chiamate a tabelle di lookup (tipologiche, elenchi statici, polling), la modalità a tappeto comporta un **carico pesante di memoria e un rapido consumo di spazio nel database** dovuto alla serializzazione di parametri e stack trace JSON. Se utilizzi questa modalità, assicurati di escludere opportunamente i prefissi delle tipologiche in `excluded_routes` o di passare alla Modalità 1.

---

### Registrazione del Middleware

#### In Laravel 11+ (`bootstrap/app.php`):
```php
use SalvatoreCervone\LogOperations\Http\Middleware\LogOperationsMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware) {
        // Registrazione globale (per Modalità 1 o Modalità 3)
        $middleware->append(LogOperationsMiddleware::class);
    })
    // ...
```

#### In Laravel 10 (`app/Http/Kernel.php`):
```php
protected $middleware = [
    // ...
    \SalvatoreCervone\LogOperations\Http\Middleware\LogOperationsMiddleware::class,
];
```


---

## 🔍 Tracciamento Manuale (Facade)

Se desideri aggiungere checkpoint o tracciare blocchi di codice specifici all'interno dei tuoi Controller o Service:

```php
use SalvatoreCervone\LogOperations\Facades\LogOperations;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        // Checkpoint manuale
        LogOperations::step('Inizio validazione ordine');

        // Traccia l'esecuzione di un blocco con misurazione automatica della durata
        $totale = LogOperations::trace('Calcolo totale carrello', function () {
            return $this->calcolaTotale();
        });

        LogOperations::step('Ordine completato con successo');

        return response()->json(['success' => true, 'totale' => $totale]);
    }
}
```

---

## 📡 API REST Incluse

Il pacchetto espone automaticamente i seguenti endpoint REST sotto `/api/logoperations`:

### Consultazione e Analisi Log
| Metodo | Endpoint | Descrizione |
| :--- | :--- | :--- |
| `GET` | `/api/logoperations` | Elenco paginato dei log con filtri combinabili (vedi parametri sotto) |
| `GET` | `/api/logoperations/{id}` | Dettaglio completo di un log con stack trace |
| `GET` | `/api/logoperations/stats` | Statistiche aggregate KPI (richieste, errori, latenza) |
| `GET` | `/api/logoperations/http-codes` | Elenco codici di stato registrati |
| `GET` | `/api/logoperations/verbs` | Verbi HTTP registrati |
| `GET` | `/api/logoperations/applications` | Nomi applicazioni registrate |

**Parametri di Filtro supportati da `GET /api/logoperations`:**
- `status_codes[]`: Codice o array di codici HTTP (es. `500` per soli errori critici, `404`, `200`).
- `user`: Ricerca su utente: supporta testo (nome/cognome/email), ID numerico esatto o `'guest'` per soli visitatori non autenticati.
- `has_error`: `1` per isolare tutti gli errori (HTTP >= 400).
- `has_unfinished_transaction`: `1` per isolare transazioni DB chiuse con Rollback di sicurezza.
- `min_duration`: Durata minima in ms per individuare richieste lente (es. `1000` per chiamate > 1s).
- `verb`: Verbo HTTP (`GET`, `POST`, `PUT`, `DELETE`).
- `text`: Ricerca testuale parziale su rotta, controller/metodo o messaggio di errore/eccezione.
- `date_from` / `date_to`: Intervallo temporale ISO o formato data.
- `ip`: Filtro per indirizzo IP client.
- `per_page`: Elementi per pagina (default 20, max 100).


### Gestione Regole Tracking Studio (Zero-Code)
| Metodo | Endpoint | Descrizione |
| :--- | :--- | :--- |
| `GET` | `/api/logoperations/studio/routes` | Mappa di tutte le rotte rilevate con stato log |
| `GET` | `/api/logoperations/studio/classes` | Albero delle classi e dei metodi applicativi scansionati |
| `GET` | `/api/logoperations/studio/rules` | Elenco di tutte le regole dinamiche attive |
| `POST` | `/api/logoperations/studio/rules` | Attiva o aggiorna una regola (rotta o metodo) |
| `DELETE` | `/api/logoperations/studio/rules/{id}` | Elimina una regola di tracciamento |
| `POST` | `/api/logoperations/studio/user-session` | Avvia il monitoraggio temporizzato di un utente |
| `DELETE` | `/api/logoperations/studio/user-session/{id}` | Interrompe anticipatamente una sessione utente |
| `GET` | `/api/logoperations/studio/users` | Ricerca rapida utenti per nome o email |

---

### 🔐 Protezione e Autorizzazione API (Gate & Middleware)

Per impostazione predefinita, in ambiente locale (`APP_ENV=local`) o di test le API sono accessibili liberamente. In ambiente di produzione o staging, è **fondamentale proteggere l'accesso**:

#### 1. Configurare i Middleware in `config/logoperations.php`:
```php
// Esempio con autenticazione Sanctum o sessione Web
'api_middleware' => ['web', 'auth'],
// oppure:
'api_middleware' => ['api', 'auth:sanctum'],
```

#### 2. Definire il Gate di Autorizzazione (es. in `AppServiceProvider.php` o `AuthServiceProvider.php`):
Il pacchetto verifica automaticamente il Gate `viewLogOperations`:

```php
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('viewLogOperations', function ($user = null) {
        // Consenti l'accesso solo agli amministratori
        return $user && in_array($user->email, [
            'admin@tuodominio.com',
            'supporto@tuodominio.com',
        ]);
    });
}
```

Se il Gate restituisce `false`, le API bloccheranno l'accesso con codice `403 Forbidden`.

---

## 💻 Integrazione Vue 3

### Utilizzo Componente Completo

```vue
<script setup>
import { LogOperationsViewer } from './vendor/logoperations'
</script>

<template>
  <LogOperationsViewer
    api-base="/api/logoperations"
    :per-page="25"
  />
</template>
```

### Componenti Singoli Disponibili

| Componente | Descrizione |
| :--- | :--- |
| `LogOperationsViewer` | Interfaccia unificata con navigazione tra Registro Log e Tracking Studio |
| `LogStoryboard` | Timeline interattiva e audit trail verticale per singoli record/modelli Eloquent |
| `LogTrackingStudio` | Centro di controllo zero-code (Studio Rotte, Studio Funzioni, Monitor Utente) |
| `LogQueryBuilder` | Costruttore di query avanzate con gruppi logici AND / OR |
| `LogDetailModal` | Drawer/modal con ispezione del payload, transazioni e Stack Trace a 2 livelli |
| `LogStatsBar` | Barra riassuntiva dei KPI con tassi di errore e tempi medi |

---

## 🖥️ Dashboard Web Standalone (`/logoperations`)

Il pacchetto include una **Dashboard Web autonoma completa**, accessibile via browser senza dover compilare o includere asset npm/Vite nel progetto host:

```bash
# Apri nel browser:
http://tuodominio.test/logoperations
```

- **Zero setup frontend**: layout dark mode responsivo in Vanilla CSS e runtime Vue 3 servito via CDN.
- **Paginazione Server-Side Reale**:
  - Ordinamento deterministico garantito `(dataoperazione DESC, id DESC)`.
  - Indice composito di database per query ad altissima velocità.
  - Selettore elementi per pagina (`15, 20, 25, 50, 100`) e salto rapido a qualsiasi pagina.
  - Mantenimento automatico di tutti i filtri di ricerca durante la navigazione.
- **Configurazione percorso e middleware** in `config/logoperations.php`:
  ```php
  'dashboard' => [
      'enabled' => env('LOG_OPERATIONS_DASHBOARD_ENABLED', true),
      'route' => env('LOG_OPERATIONS_DASHBOARD_ROUTE', 'logoperations'),
      'middleware' => ['web'],
      'per_page' => 20,
      'per_page_options' => [15, 20, 25, 50, 100],
  ],
  ```

---

## 📖 Storyboard del Record & Audit Trail

La Storyboard ricostruisce la **timeline completa di vita** di qualsiasi entità del tuo dominio applicativo (ordini, fatture, ticket, utenti).

### 1. Aggiungere il Trait al Modello Eloquent:
```php
use Illuminate\Database\Eloquent\Model;
use SalvatoreCervone\LogOperations\Traits\HasOperationLogs;

class Order extends Model
{
    use HasOperationLogs;
}
```

### 2. Rilevamento Automatico tramite Route Model Binding:
Se una rotta utilizza Route Model Binding (es. `/api/ordini/{order}`), il middleware associa automaticamente il modello come target (`subject`) del log:
```php
Route::put('/ordini/{order}', [OrderController::class, 'update'])->middleware('log.operations');
```

### 3. Checkpoint Applicativi ed Esplorazione Storyboard:
```php
// Registra un checkpoint di business collegato all'ordine:
$order->logStep('Autorizzazione pagamento ricevuta da Stripe', ['payment_id' => 'ch_123']);

// Recupera l'intera storia cronologica del record:
$timeline = $order->storyboard();
```

### 4. Componente Vue Dedicato:
```vue
<script setup>
import { LogStoryboard } from './vendor/logoperations'
</script>

<template>
  <LogStoryboard
    :subject-id="order.id"
    subject-type="App\Models\Order"
    api-base="/api/logoperations"
  />
</template>
```

---

## 🏷️ Tracciamento Dichiarativo con Attributo PHP 8 (`#[Traceable]`)

Oltre al pannello zero-code, puoi tracciare classi di servizio e metodi in modo dichiarativo tramite l'attributo nativo `#[Traceable]`:

```php
use SalvatoreCervone\LogOperations\Attributes\Traceable;

// Traccia tutti i metodi pubblici della classe:
#[Traceable]
class PaymentGatewayService
{
    public function charge(Order $order): bool { ... }
}

// Oppure traccia solo singoli metodi:
class OrderService
{
    #[Traceable(label: 'Calcolo Giudiziario e Totali', level: 'core')]
    public function calcolaTotale(Order $order): float
    {
        return 99.50;
    }
}
```

- **Piena compatibilità con la Dependency Injection**: le classi decorate o tracciate vengono intercettate tramite sottoclassi dinamiche generate a runtime (`ProxyClassGenerator`). L'istanza generata soddisfa sempre `$proxy instanceof OrderService === true`, eliminando qualsiasi `TypeError` nei controller.

---

## ⚡ Prestazioni: Terminable Middleware, Coda Asincrona & Sampling

Il pacchetto è progettato per ambienti di produzione ad alto traffico:

1. **Terminable Middleware (`terminate()`)**:
   La persistenza dei log su database avviene solo **dopo che la risposta HTTP è stata inviata al client**, con latenza percepita pari a **zero**.

2. **Logging Asincrono su Coda**:
   ```php
   // config/logoperations.php
   'queue' => [
       'enabled' => env('LOG_OPERATIONS_QUEUE_ENABLED', false),
       'connection' => env('LOG_OPERATIONS_QUEUE_CONNECTION', null),
       'queue' => env('LOG_OPERATIONS_QUEUE_NAME', 'log-operations'),
       'tries' => 3,
       'backoff' => [10, 30, 60],
       'alert_email' => env('LOG_OPERATIONS_ALERT_EMAIL', null),
       'failed_jobs_threshold' => 5,
   ],
   ```
   - In caso di broker di coda offline, il pacchetto esegue un **fallback automatico** su scrittura diretta o file di emergenza (`storage/logs/logoperations-emergency.log`).
   - Allerta email automatica con rate-limiting se il numero di job falliti supera la soglia.

3. **Campionamento Richieste di Successo (Sampling Rate)**:
   ```php
   // Registra solo il 10% delle chiamate con esito 200 OK
   'sampling_rate' => 10,
   ```
   Gli errori (HTTP >= 400 ed eccezioni) vengono **sempre registrati al 100%**.

---

## 🛡️ Privacy, GDPR Art. 17 & Diritto all'Oblio

1. **Anonimizzazione Indirizzi IP** (disabilitata di default per non alterare gli audit intranet):
   ```env
   LOG_OPERATIONS_ANONYMIZE_IP=true
   ```
   Maschera l'ultimo ottetto IPv4 (`192.168.1.xxx` o `192.168.1.0`) e la porzione utente IPv6.

2. **Sanitizzazione Header Sensibili**:
   Gli header `Authorization`, `Cookie`, `Set-Cookie`, `X-XSRF-TOKEN`, `X-CSRF-TOKEN` e credenziali HTTP vengono automaticamente mascherati.

3. **Diritto all'Oblio (GDPR Art. 17 - Forget User)**:
   ```php
   use SalvatoreCervone\LogOperations\Facades\LogOperations;

   // 1. Cancellazione fisica di tutti i log dell'utente:
   LogOperations::forgetUser($userId);

   // 2. Soft Scrub (preserva durata, rotte e codici HTTP, ma azzera dati personali e IP):
   LogOperations::forgetUser($userId, anonymize: true);
   ```

   **Comando Artisan Console**:
   ```bash
   # Elimina con conferma interattiva:
   php artisan logoperations:forget-user 42

   # Anonimizza conservando i metadati tecnici:
   php artisan logoperations:forget-user 42 --anonymize

   # Forza l'esecuzione in script o scheduler:
   php artisan logoperations:forget-user 42 --force
   ```

---

## 📥 Export Massivo Streaming & 🚨 Alerting Multi-Canale

### 1. Export in Streaming O(1) di Memoria
Il pacchetto include endpoint dedicati per lo scaricamento di grandi moli di log senza saturare la memoria RAM del server PHP:

- **Endpoint API**:
  - `GET /api/logoperations/export?format=csv` (oppure `format=json`)
- **Filtri di ricerca**: supporta tutti i filtri disponibili per la consultazione (`verb`, `status_codes`, `date_from`, `date_to`, `user`, `ip`, `controller`, `text`, `has_error`, `has_unfinished_transaction`, `min_duration`).
- **CSV Excel-Ready**: include automaticamente il BOM UTF-8 (`\xEF\xBB\xBF`) per evitare problemi di codifica caratteri e accenti in Microsoft Excel.
- **Pulsanti Dashboard**: integrati direttamente nella toolbar della dashboard standalone (`/logoperations`) e del playground demo.

### 2. Sistema di Alerting Multi-Canale
Notifiche in tempo reale al verificarsi di anomalie o eventi critici.

#### Canali Supportati:
- **Email**: invio notifiche formattate all'indirizzo dell'amministratore/team di supporto.
- **Slack**: Webhook con payload interattivo, allegati colorati e timestamp.
- **Discord**: Webhook con rich embeds formattati.
- **Webhook generico**: invio payload POST JSON con firma di autenticazione crittografica HMAC opzionale (`X-LogOperations-Signature`).

#### Dove e come impostare i parametri (Variabili di Ambiente `.env`):
I parametri di alert possono essere configurati direttamente nel file `.env` della tua applicazione oppure in `config/logoperations.php`:

```env
# ==============================================================================
# LOGOPERATIONS: SISTEMA DI ALERTING E NOTIFICHE MULTI-CANALE
# ==============================================================================

# 1. Abilitazione globale del sistema di alert (default: false)
LOG_OPERATIONS_ALERTS_ENABLED=true

# 2. Canale EMAIL: destinatario delle notifiche di allarme
LOG_OPERATIONS_ALERT_EMAIL=admin@example.com

# 3. Canale SLACK: Incoming Webhook URL del canale di monitoraggio
LOG_OPERATIONS_SLACK_WEBHOOK=https://hooks.slack.com/services/T000/B000/XXXXX

# 4. Canale DISCORD: Webhook URL del canale del server Discord
LOG_OPERATIONS_DISCORD_WEBHOOK=https://discord.com/api/webhooks/123456789/abcdefgh

# 5. Canale WEBHOOK GENERICO: Endpoint HTTP POST e Secret HMAC per validazione firma
LOG_OPERATIONS_ALERT_WEBHOOK=https://api.mycompany.com/alerts/receiver
LOG_OPERATIONS_ALERT_WEBHOOK_SECRET=chiave_segreta_hmac_256

# 6. Finestra di silenzio anti-flood in minuti per canale/evento (default: 15 minuti)
LOG_OPERATIONS_ALERT_THROTTLE=15

# 7. Allarme immediato su Rollback di transazioni SQL non chiuse (default: true)
LOG_OPERATIONS_ALERT_ON_ROLLBACK=true

# 8. Parametri per il monitoraggio del picco del tasso di errore (logoperations:check-alerts)
LOG_OPERATIONS_ALERT_WINDOW=5          # Finestra temporale di osservazione in minuti
LOG_OPERATIONS_ALERT_THRESHOLD=10.0    # Soglia percentuale errori (es. 10%)
LOG_OPERATIONS_ALERT_MIN_REQUESTS=20   # Volume minimo di richieste nel periodo per calcolare la percentuale
```

#### Selezione selettiva dei canali attivi (`config/logoperations.php`):
Se desideri attivare solo specifici canali (ad esempio solo Slack ed Email escludendo Discord), puoi personalizzare l'array `'channels'` nel file `config/logoperations.php`:

```php
'alerts' => [
    'enabled' => env('LOG_OPERATIONS_ALERTS_ENABLED', false),

    // Array dei canali abilitati all'invio:
    'channels' => ['mail', 'slack'], // oppure ['slack', 'discord', 'webhook']

    // ...
],
```

#### Eventi Monitorati:
1. **Rollback di Transazioni SQL Pendenti**: notifica immediata quando una richiesta termina lasciando transazioni aperte che vengono chiuse con rollback di emergenza.
2. **Picchi del Tasso di Errore**: monitoraggio della percentuale di richieste in errore (status >= 400 o eccezioni) su una finestra temporale (es. ultimi 5 minuti).

#### Comando Artisan di Monitoraggio:
```bash
php artisan logoperations:check-alerts --window=5 --threshold=10 --min-requests=20
```
Può essere configurato nello scheduler di Laravel (`routes/console.php`):
```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('logoperations:check-alerts --window=5 --threshold=10')
    ->everyFiveMinutes();
```

#### Meccanismo Anti-Flood Throttle:
Tutti gli allarmi sono protetti da un rate-limiting intelligente basato su Cache (`throttle_minutes => 15`) per evitare che un disservizio continuativo intasi la casella email o i canali di messaggistica.

---

## 🛡️ Retention Policy, Salvaguardia Storyboard & Manutenzione

LogOperations non è un semplice file di log temporaneo, ma un **Audit Trail permanente e Storyboard di vita delle entità aziendali** (Ordini, Fatture, Ticket). Per questo motivo adotta una **politica conservativa e ultra-sicura**:

### 1. Salvaguardia Assoluta della Storyboard
- **Disabilitata di default**: la cancellazione automatica dei log è disattivata al 100% (`retention.enabled => false`).
- **Scudo Storyboard attivo**: qualsiasi record collegato a un'entità business (`subject_type` o `subject_id` non nulli) è **rigorosamente protetto ed escluso da qualsiasi operazione di pulizia**.
- **Scudo Errori attivo**: tutti i record con esito anomalo (`HTTP >= 400`, rollback o eccezioni) rimangono intatti nel database per audit forensi.

### 2. Pulizia delle Regole di Sessione Scadute
Quando imposti sessioni a tempo per monitorare un utente (es. 15 minuti), la regola termina la sua validità una volta superato l'orario. Il comando rimuove le sole righe scadute dalla tabella `log_operazioni_regole` **senza toccare minimamente i log operativi**, che rimangono salvati per sempre in `log_operazioni`:

```bash
# Rimuove le sole sessioni/regole scadute:
php artisan logoperations:clear-expired-rules

# Simulazione (dry-run):
php artisan logoperations:clear-expired-rules --dry-run
```

Può essere pianificato nello scheduler:
```php
Schedule::command('logoperations:clear-expired-rules')->daily();
```

### 3. Pruning Conservativo per Grandi Volumi
In ambienti ad altissimo traffico (milioni di richieste HTTP al giorno) in cui si desideri alleggerire le sole letture generiche anonime obsolete:

```bash
# Esegue una simulazione con riepilogo dettagliato dei record protetti:
php artisan logoperations:prune --days=180 --dry-run

# Pulizia manuale sicura con conferma interattiva (Storyboards ed errori sempre protetti):
php artisan logoperations:prune --days=180

# Specificando una finestra in ore:
php artisan logoperations:prune --hours=72 --force
```

### 4. Configurazione `.env`
```env
# Retention disabilitata di default
LOG_OPERATIONS_RETENTION_ENABLED=false

# Giorni di conservazione predefiniti se abilitata
LOG_OPERATIONS_RETENTION_DAYS=365

# Dimensione chunk per cancellazioni sicure senza lock
LOG_OPERATIONS_PRUNE_CHUNK_SIZE=1000
```

### 5. Diagnostica & Stato del Pacchetto (`logoperations:status`)
Per verificare rapidamente la salute operativa del pacchetto, le regole attive, la configurazione degli allarmi e i conteggi del database:

```bash
php artisan logoperations:status
```

Fornisce un output diagnostico suddiviso in 4 tabelle riassuntive:
- **Stato Generale**: connessione DB, tabella, log totali, errori e conteggio eventi Storyboard entità.
- **Centro di Controllo Zero-Code (Tracking Studio)**: rotte web/API dinamiche, metodi proxy e sessioni utente attive.
- **Sistema di Alerting & Notifiche**: canali abilitati (Mail, Slack, Discord, Webhook), anti-flood throttle e alert rollback SQL.
- **Retention Policy & Salvaguardia**: stato cancellazione automatica e scudi protettivi 100% per Storyboard ed Errori.

---

## 🧪 Playground & Demo Live Incorporata (`demo/`)

All'interno della cartella `demo/` è presente un'applicazione Laravel pronta all'uso con database SQLite, dati di test (Mario Rossi, Luigi Bianchi) e un simulatore interattivo.

### Avvio della Demo:

```bash
cd demo
php artisan serve
```

Apri il browser su:
- **Playground Interattivo**: `http://localhost:8000`
- **Dashboard Web Standalone**: `http://localhost:8000/logoperations`

---

## 📦 Versioning & Changelog

- **v1.3.0** *(Raccomandata)*:
  - **Default Architetturale Selettivo (Scenario A)**: `mode` predefinito su `'selective'`, `allowed_methods` su `['*']` e `stack_trace.only_on_error` su `true`. Massima pulizia del DB e zero sovraccarico per tipologiche e consultazioni ordinarie.
  - **Supporto FQCN Middleware**: Supporto completo a `Route::middleware(LogOperationsMiddleware::class)` oltre ai classici alias stringa `'log.operations'` / `'logoperations'`.
  - **Paracadute Errori 500 (Safety Net)**: Aggiunta opzione `log_uncaught_errors` (default: `true`) che intercetta e registra automaticamente con full stack trace qualsiasi crash `HTTP 500` anche su rotte non esplicitamente monitorate.
  - **Best Practice Risorse Web**: Linee guida per l'esclusione di viste form HTML (`*/create`, `*/edit`) in `excluded_routes`.
- **v1.2.x**: Storyboard polimorfica per entità Eloquent, Tracking Studio dinamico Zero-Code, Alerting multi-canale (Slack, Discord, Mail, Webhook), Retention Policy e interfaccia Dashboard Dark-Slate.

---

## 📄 Licenza

Distribuito con licenza MIT.


