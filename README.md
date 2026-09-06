# Log Operations - Pacchetto Laravel + Vue 3

Pacchetto Composer Laravel per il **tracciamento, monitoraggio e analisi delle operazioni applicative** con gestione intelligente delle transazioni database, stack trace a 2 livelli e **Centro di Controllo Zero-Code (Tracking Studio)** per attivare i log senza modificare codice.

---

## 🚀 Caratteristiche Principali

- 🎛️ **Centro di Controllo Zero-Code (Tracking Studio)**:
  - 🗺️ **Studio Rotte**: scansione automatica delle rotte web e API con attivazione/disattivazione permanente a 1 click (ON/OFF) e selezione del livello di dettaglio.
  - ⚙️ **Studio Funzioni**: scansione delle classi di servizio in `app/` e intercettazione dinamica dei metodi PHP (parametri, durata in ms, eccezioni) via Service Container senza modificare i file sorgente.
  - 👤 **Monitor Utente Live a Tempo**: tracciamento mirato di un singolo utente per una finestra temporale (5, 15, 30, 60 minuti) con countdown live e disattivazione automatica, ideale per l'assistenza clienti.
- 🔐 **Supporto Utente Polimorfico** (`nullableMorphs('user')`): supporta qualsiasi modello autenticabile (`User`, `Admin`, `Customer`, ecc.).
- 🔄 **Rollback Ciclico Transazioni**: rileva transazioni SQL rimaste aperte a causa di errori e le annulla automaticamente fino a livello 0, garantendo l'integrità del database.
- 📚 **Stack Trace a 2 Livelli**:
  - **🎯 Livello Core**: isola e mostra solo i file del progetto (cartella `app/`), filtrando il rumore del framework.
  - **🔍 Livello Completo**: visualizza l'intera catena di chiamate incluse librerie vendor e framework.
- 🛡️ **Mascheramento Dati Sensibili**: offuscamento automatico di password, token, carte di credito e chiavi API.
- ⏱️ **Misurazione Precisa della Latenza**: durata di ogni chiamata registrata in millisecondi con indicatori di lentezza.
- 📊 **Dashboard & Registro Log Vue 3**: visualizzatore moderno con statistiche KPI, filtri rapidi, query builder avanzato e drawer laterale di ispezione.

---

## 📦 Installazione

### 1. Includere il pacchetto nel progetto

```bash
composer require salvatorecervone/logoperations
```

Il `LogOperationsServiceProvider` e la facade `LogOperations` vengono registrati automaticamente tramite package auto-discovery.

### 2. Pubblicare configurazione e migrazioni

```bash
php artisan vendor:publish --tag=logoperations-config
php artisan vendor:publish --tag=logoperations-migrations
```

### 3. Eseguire le migrazioni

```bash
php artisan migrate
```

Verranno create le tabelle:
- `log_operazioni`: archivio storico di tutte le operazioni eseguite.
- `log_operazioni_regole`: regole dinamiche configurate da interfaccia per rotte, metodi e sessioni utente.

### 4. Pubblicare i componenti Vue 3 (opzionale)

```bash
php artisan vendor:publish --tag=logoperations-vue
```

I componenti verranno copiati in `resources/js/vendor/logoperations/`.

---

## ⚙️ Configurazione

Il file `config/logoperations.php` offre il controllo completo su ogni aspetto:

```php
return [
    // Abilitazione globale del logger
    'enabled' => env('LOG_OPERATIONS_ENABLED', true),

    // Nome della tabella principale (default: log_operazioni)
    'table_name' => env('LOG_OPERATIONS_TABLE', 'log_operazioni'),

    // Connessione database dedicata (opzionale, per isolare i log dal DB applicativo)
    'database_connection' => env('LOG_OPERATIONS_DB_CONNECTION', null),

    // Verbi HTTP monitorati: ['*'] per tutti oppure lista specifica ['POST', 'PUT', 'DELETE']
    'allowed_methods' => ['*'],

    // Codici di stato esclusi dal logging
    'excluded_status_codes' => [422],

    // Rotte escluse (per evitare loop di auto-logging)
    'excluded_routes' => ['api/logoperations*', 'api/log-operations*', 'telescope*'],

    // Configurazione Stack Trace a 2 livelli
    'stack_trace' => [
        'enabled' => true,
        'only_on_error' => false,
        'default_view' => 'core',         // 'core' (app/) o 'full' (completo)
        'project_paths' => ['app/'],      // Cartelle considerate proprietarie
        'max_frames' => 100,
        'trace_db_callers' => true,
    ],

    // Gestione transazioni non terminate
    'transactions' => [
        'manage_unfinished' => true,
        'rollback_on_error' => true,      // Esegue rollback ciclico su HTTP >= 400
        'commit_on_success' => false,     // false = rollback di sicurezza anche su successo
        'log_transaction_state' => true,
    ],

    // Campi sensibili mascherati automaticamente nei parametri
    'mask_fields' => ['password', 'password_confirmation', 'token', 'secret', 'authorization', 'credit_card'],

    // Campi ricercabili per il modello utente
    'user_search_fields' => ['name', 'cognome', 'email'],

    // Nome identificativo dell'applicazione (per ambienti multi-app)
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

### 🎛️ Modalità 1: Globale Selettivo (Zero-Code da Pannello) — *Consigliata*
Registri il middleware a livello globale, ma configuri `'mode' => 'selective'` (in `config/logoperations.php` o `.env` con `LOG_OPERATIONS_MODE=selective`).
- **Comportamento**: Di default **non logga alcuna rotta**.
- **Controllo**: Apri il **Tracking Studio** e attivi `[ON]` con 1 click **solo le rotte che ti interessano** (es. ordini, pagamenti, contratti).
- **Vantaggi**: Zero modifiche ai file di rotta, rotte tipologiche/lookup escluse di default, database sempre leggero e pulito.

---

### 🎯 Modalità 2: Mirato da Codice (Gruppi o Singole Rotte)
Non registri il middleware globale. Applichi l'alias `log.operations` solo ai gruppi di rotte o risorse business che devono avere una Storyboard:

```php
// Rotte tipologiche / lookup: NESSUN LOG
Route::prefix('tipologiche')->group(function () {
    Route::get('stati-ordine', [LookupController::class, 'orderStatuses']);
    Route::get('comuni', [LookupController::class, 'cities']);
});

// Rotte operative di business: MONITORATE per la Storyboard
Route::middleware('log.operations')->group(function () {
    Route::resource('ordini', OrderController::class);
    Route::resource('fatture', InvoiceController::class);
});
```

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
| `LogTrackingStudio` | Centro di controllo zero-code (Studio Rotte, Studio Funzioni, Monitor Utente) |
| `LogQueryBuilder` | Costruttore di query avanzate con gruppi logici AND / OR |
| `LogDetailModal` | Drawer/modal con ispezione del payload, transazioni e Stack Trace |
| `LogStatsBar` | Barra riassuntiva dei KPI con tassi di errore e tempi medi |

---

## 🧪 Playground & Demo Live Incorporata (`demo/`)

All'interno della cartella `demo/` è presente un'applicazione Laravel pronta all'uso con database SQLite, dati di test (Mario Rossi, Luigi Bianchi) e un simulatore interattivo.

### Avvio della Demo:

```bash
cd demo
php artisan serve
```

Apri il browser su `http://localhost:8000`:
* In testata trovi la **Barra di Simulazione** per generare con 1 click scenari reali (Ordine con successo, Errore 500 con eccezione, Transazione SQL non chiusa, Richiesta lenta).
* Puoi passare istantaneamente dal **Registro Operazioni** allo **Studio Rotte**, allo **Studio Funzioni** e alla **Sessione Utente Live**.
* I tooltip interattivi e le guide rapide integrate forniscono spiegazioni chiare su ogni funzionalità.

---

## 📄 Licenza

Distribuito con licenza MIT.
