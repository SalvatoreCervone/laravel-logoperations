<?php

namespace SalvatoreCervone\LogOperations\Console\Commands;

use Illuminate\Console\Command;
use SalvatoreCervone\LogOperations\Services\PrivacyManager;

class ForgetUserCommand extends Command
{
    /**
     * Il nome e la firma del comando console.
     *
     * @var string
     */
    protected $signature = 'logoperations:forget-user
                            {user_id : L\'identificativo dell\'utente da dimenticare}
                            {--type= : Tipo o classe del modello utente (es. App\Models\User)}
                            {--anonymize : Anonimizza i log rimuovendo dati PII invece di cancellarli fisicamente}
                            {--force : Forza l\'operazione senza richiedere conferma interattiva}';

    /**
     * La descrizione del comando console.
     *
     * @var string
     */
    protected $description = 'Esegue il Diritto all\'Oblio (GDPR Art. 17) eliminando o anonimizzando i log dell\'utente';

    /**
     * Esegue il comando console.
     */
    public function handle(PrivacyManager $privacyManager): int
    {
        $userId = $this->argument('user_id');
        $userType = $this->option('type') ?: null;
        $anonymize = (bool) $this->option('anonymize');
        $force = (bool) $this->option('force');

        $actionText = $anonymize
            ? 'anonimizzare i dati personali conservando i metadati tecnici'
            : 'cancellare DEFINITIVAMENTE tutti i log';

        if (!$force) {
            $question = sprintf(
                'Sei sicuro di voler %s per l\'utente ID: %s%s?',
                $actionText,
                $userId,
                $userType ? " (Tipo: {$userType})" : ''
            );

            if (!$this->confirm($question, false)) {
                $this->warn('Operazione annullata dall\'utente.');
                return self::SUCCESS;
            }
        }

        $this->comment("Elaborazione in corso per l'utente ID: {$userId}...");

        $affected = $privacyManager->forgetUser($userId, $userType, $anonymize);

        if ($affected === 0) {
            $this->warn("Nessun record di log trovato per l'utente specificato [ID: {$userId}].");
            return self::SUCCESS;
        }

        $resultAction = $anonymize
            ? 'anonimizzati con successo (PII rimossi, metadati tecnici conservati)'
            : 'eliminati definitivamente';

        $this->info("Operazione completata: {$affected} record di log {$resultAction} per l'utente ID: {$userId}.");

        return self::SUCCESS;
    }
}
