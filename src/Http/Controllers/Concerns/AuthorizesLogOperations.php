<?php

namespace SalvatoreCervone\LogOperations\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;

/**
 * Trait per la gestione dell'autorizzazione all'accesso
 * degli endpoint del pacchetto LogOperations.
 */
trait AuthorizesLogOperations
{
    /**
     * Verifica l'autorizzazione all'accesso per la richiesta corrente.
     *
     * Regole di valutazione:
     * 1. Se 'allow_in_local' è abilitato e ci troviamo in ambiente 'local' o 'testing', l'accesso è consentito.
     * 2. Se un Gate specifico è configurato (default 'viewLogOperations') ed è registrato nell'app host, viene valutato.
     * 3. In ambienti di produzione (production/staging), se non è presente un utente autenticato o il Gate nega l'accesso, viene restituito 403 Forbidden.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function authorizeAccess(Request $request): void
    {
        // Se siamo in ambiente locale o test e allow_in_local è true, consenti l'accesso
        if (config('logoperations.allow_in_local', true) && (App::environment('local') || App::environment('testing'))) {
            return;
        }

        $gateName = config('logoperations.gate', 'viewLogOperations');

        // Se il Gate è definito nell'applicazione host
        if ($gateName && Gate::has($gateName)) {
            if (!Gate::allows($gateName, [$request->user()])) {
                abort(403, 'Accesso non autorizzato alla console di LogOperations.');
            }
            return;
        }

        // In produzione, se il gate non è definito ma c'è richiesta di protezione
        if (App::environment('production') || App::environment('staging')) {
            $user = $request->user();
            if (!$user) {
                abort(401, 'Autenticazione richiesta per accedere a LogOperations.');
            }
        }
    }
}
