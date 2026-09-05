# Log Operations - Pacchetto Laravel + Vue 3

Pacchetto Composer Laravel per il **tracciamento automatico delle operazioni HTTP** con:

- 🔐 **Supporto utente polimorfico** (`morphTo`) per qualsiasi modello autenticabile
- 🔄 **Gestione transazioni pendenti** — rollback automatico di transazioni non chiuse
- 📚 **Stack trace a 2 livelli** — Livello 1 (solo codice core app/) e Livello 2 (stack completo con Laravel/vendor)
- 🎛️ **Middleware configurabile** per tutti i verbi HTTP (o solo specifici)
- 🛡️ **Mascheramento automatico** di campi sensibili (password, token, ecc.)
- ⏱️ **Misurazione della durata** di ogni richiesta (ms)
- 🖥️ **Componente Vue 3 moderno** con KPI, query builder avanzato (AND/OR/NOT) e visualizzatore stack interattivo

---

## Installazione

### 1. Aggiungere il pacchetto

```bash
composer require salvatorecervone/logoperations
```

Il ServiceProvider e la Facade vengono registrati automaticamente tramite auto-discovery.

### 2. Pubblicare configurazione e migrazioni

```bash
php artisan vendor:publish --tag=logoperations-config
php artisan vendor:publish --tag=logoperations-migrations
```

### 3. Eseguire le migrazioni

```bash
php artisan migrate
```

### 4. Pubblicare i componenti Vue (opzionale)

```bash
php artisan vendor:publish --tag=logoperations-vue
```

I componenti verranno copiati in `resources/js/vendor/logoperations/`.

---

## Configurazione

Il file `config/logoperations.php` offre il controllo completo:

```php
return [
    // Abilitazione globale
    'enabled' => env('LOG_OPERATIONS_ENABLED', true),

    // Nome tabella (cambiare in 'logoperazionis' per retrocompatibilità)
    'table_name' => env('LOG_OPERATIONS_TABLE', 'log_operazioni'),

    // Connessione DB dedicata per isolare i log dalle transazioni app
    'database_connection' => env('LOG_OPERATIONS_DB_CONNECTION', null),

    // Verbi HTTP monitorati: ['*'] per tutti
    'allowed_methods' => ['*'],

    // Codici HTTP esclusi dal logging
    'excluded_status_codes' => [422],

    // Rotte escluse (anti-loop)
    'excluded_routes' => ['api/logoperations*', 'api/log-operations*', 'telescope*'],

    // Stack trace a 2 livelli
    'stack_trace' => [
        'enabled' => true,
        'only_on_error' => false,
        'default_view' => 'core',         // 'core' o 'full'
        'project_paths' => ['app/'],      // Percorsi del codice proprietario
        'max_frames' => 100,
        'trace_db_callers' => true,       // Traccia l'origine delle query DB
    ],

    // Gestione transazioni non terminate
    'transactions' => [
        'manage_unfinished' => true,
        'rollback_on_error' => true,      // Rollback su HTTP >= 400
        'commit_on_success' => false,     // false = rollback preventivo anche su successo
        'log_transaction_state' => true,
    ],

    // Campi mascherati automaticamente
    'mask_fields' => ['password', 'password_confirmation', 'token', 'secret', 'authorization'],

    // Campi ricercabili sull'utente polimorfico
    'user_search_fields' => ['name', 'cognome', 'email'],

    // Nome applicazione per ambienti multi-app
    'app_name' => env('LOG_OPERATIONS_APP_NAME', env('APP_NAME', 'laravel')),
];
```

---

## Uso del Middleware

### Registrazione globale (consigliato)

In `app/Http/Kernel.php` (Laravel 10) o `bootstrap/app.php` (Laravel 11+):

```php
// Laravel 10 - Kernel.php
protected $middleware = [
    // ...
    \SalvatoreCervone\LogOperations\Http\Middleware\LogOperationsMiddleware::class,
];

// Laravel 11+ - bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(
        \SalvatoreCervone\LogOperations\Http\Middleware\LogOperationsMiddleware::class
    );
})
```

### Su rotte specifiche (tramite alias)

```php
Route::middleware('log.operations')->group(function () {
    Route::resource('pippo', PippoController::class);
});
```

---

## Tracciamento Manuale (Step & Trace)

All'interno del codice applicativo, puoi registrare checkpoint e tracciare funzioni:

```php
use SalvatoreCervone\LogOperations\Facades\LogOperations;

class PippoController extends Controller
{
    public function update(Request $request, Pippo $pippo)
    {
        // Registra un checkpoint
        LogOperations::step('Inizio aggiornamento pippo #' . $pippo->id);

        // Traccia l'esecuzione di una funzione con durata
        $risultato = LogOperations::trace('Verifica permessi', function () use ($pippo) {
            return $this->verificaPermessi($pippo);
        });

        LogOperations::step('Permessi verificati, procedo al salvataggio');

        $pippo->update($request->validated());

        LogOperations::step('Salvataggio completato');

        return response()->json($pippo);
    }
}
```

Tutti gli step e i trace vengono salvati automaticamente nel campo `custom_traces` del log e visualizzati nel componente Vue nella tab "Traces".

---

## API REST

Il pacchetto espone automaticamente le seguenti API sotto `/api/logoperations` (con alias retrocompatibile `/api/log-operations`):

| Metodo | Endpoint                                  | Descrizione                              |
|--------|-------------------------------------------|------------------------------------------|
| GET    | `/api/logoperations`                      | Lista paginata con filtri                |
| GET    | `/api/logoperations/{id}`                 | Dettaglio singolo log con stack          |
| GET    | `/api/logoperations/stats`                | Statistiche KPI (errori, durata, ecc.)   |
| GET    | `/api/logoperations/http-codes`           | Codici HTTP registrati                   |
| GET    | `/api/logoperations/verbs`                | Verbi HTTP registrati                    |
| GET    | `/api/logoperations/applications`         | Applicazioni registrate                  |

### Parametri di ricerca (GET)

| Parametro                    | Tipo        | Descrizione                              |
|------------------------------|-------------|------------------------------------------|
| `user`                       | string      | Ricerca utente (nome, cognome, email)    |
| `verb` / `verb[]`           | string/array| Verbi HTTP (get, post, put, delete...)   |
| `status_codes[]`            | array       | Codici HTTP (200, 404, 500...)           |
| `date_from`                 | datetime    | Data inizio range                        |
| `date_to`                   | datetime    | Data fine range                          |
| `ip`                        | string      | Indirizzo IP (ricerca parziale)          |
| `controller`                | string      | Controller@metodo (ricerca parziale)     |
| `app`                       | string      | Nome applicazione                        |
| `text`                      | string      | Ricerca libera (rotta, errore, ecc.)     |
| `has_error`                 | boolean     | Solo risposte con errore (>= 400)        |
| `has_unfinished_transaction`| boolean     | Solo transazioni pendenti rilevate       |
| `per_page`                  | integer     | Elementi per pagina (default 20, max 100)|
| `g`                         | string      | Gruppi ricerca base64 (retrocompatibilità)|

---

## Componente Vue 3

### Importazione e uso

```vue
<script setup>
import { LogOperationsViewer } from './vendor/logoperations'
</script>

<template>
  <LogOperationsViewer
    api-base="/api/logoperations"
    :per-page="20"
  />
</template>
```

### Come plugin globale Vue

```js
import LogOperationsPlugin from './vendor/logoperations'

const app = createApp(App)
app.use(LogOperationsPlugin)
app.mount('#app')
```

Poi nel template:

```vue
<LogOperationsViewer />
```

### Componenti esportati

| Componente              | Descrizione                                        |
|------------------------|----------------------------------------------------|
| `LogOperationsViewer`  | Componente principale completo                     |
| `LogQueryBuilder`      | Query builder a gruppi logici (AND/OR/NOT)        |
| `LogDetailModal`       | Modal dettaglio con Stack a 2 livelli e JSON viewer|
| `LogStatsBar`          | Barra KPI con statistiche rapide                   |

---

## Stack Trace a 2 Livelli

Il componente Vue include un toggle per visualizzare lo stack delle chiamate:

- **🎯 Solo Codice Core**: mostra solo le funzioni del codice proprietario dell'applicazione (file in `app/`), filtrando tutto il rumore del framework
- **🔍 Stack Completo**: mostra l'intera catena di esecuzione, incluse le chiamate interne di Laravel, Symfony e package di terze parti

Ogni frame è classificato con un flag `is_core` e nel componente i frame del codice applicativo sono evidenziati con un badge "CORE" e un bordo colorato.

---

## Gestione Transazioni

Il middleware rileva automaticamente transazioni DB lasciate aperte:

- **Su errore (HTTP >= 400)**: esegue il rollback ciclico di tutti i livelli per ripulire dati non reali e rilasciare i lock
- **Su successo con transazione aperta**: comportamento configurabile (`commit_on_success`)
- Il log viene scritto **dopo** la risoluzione della transazione per non essere revocato
- Lo stato viene tracciato nel campo `transaction_status` e visualizzato con un badge di avviso nel componente Vue

---

## Upgrade da installazioni esistenti

Se hai già la vecchia tabella `logoperazionis`, imposta nel `.env`:

```env
LOG_OPERATIONS_TABLE=logoperazionis
```

La migrazione di upgrade `2026_01_01_000001_upgrade_logoperazionis_table.php` aggiungerà automaticamente le nuove colonne (`user_type`, `stack_trace`, `custom_traces`, `duration_ms`, `transaction_status`, ecc.) senza perdere i dati esistenti.

---

## Pubblicazione su Packagist e Gestione Tag

Per pubblicare il pacchetto su **Packagist** e renderlo disponibile per l'installazione tramite Composer:

### 1. Tag di ricerca (Keywords)
Nel `composer.json` sono configurati tutti i tag per l'indicizzazione:
`laravel`, `log`, `logging`, `operations`, `audit`, `audit-log`, `activity-log`, `http-logger`, `middleware`, `stack-trace`, `tracing`, `transactions`, `monitoring`, `vue`, `vue3`, `dashboard`, `viewer`.

### 2. Creazione del repository Git e rilascio versione (Git Tags)
Packagist rileva le versioni e i rilasci del pacchetto basandosi sui tag Git (Semantic Versioning `vX.Y.Z`):

```bash
# Inizializza il repository (se non ancora presente)
git init
git add .
git commit -m "feat: initial release of salvatorecervone/logoperations"

# Crea il tag di versione per Packagist
git tag -a v1.0.0 -m "Release v1.0.0 - Tracciamento HTTP, transazioni, stack a 2 livelli e Vue 3"

# Collega il repository remoto (es. GitHub/GitLab) e invia codice e tag
git remote add origin https://github.com/SalvatoreCervone/logoperations.git
git branch -M main
git push -u origin main --tags
```

### 3. Registrazione su Packagist
1. Accedi a [packagist.org](https://packagist.org)
2. Clicca su **Submit** e incolla l'URL del tuo repository Git
3. Configura il webhook GitHub/GitLab per gli aggiornamenti automatici a ogni nuovo commit/tag

---

## Licenza

MIT

