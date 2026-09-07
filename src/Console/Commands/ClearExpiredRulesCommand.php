<?php

namespace SalvatoreCervone\LogOperations\Console\Commands;

use Illuminate\Console\Command;
use SalvatoreCervone\LogOperations\Models\OperationRule;

class ClearExpiredRulesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logoperations:clear-expired-rules
                            {--dry-run : Mostra le regole scadute senza eliminarle}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rimuove le sole sessioni di monitoraggio temporanee scadute dalla tabella delle regole, preservando tutti i log storici';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Ricerca regole e sessioni temporanee scadute...');

        $query = OperationRule::whereNotNull('expires_at')
            ->where('expires_at', '<', now());

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('✅ Nessuna sessione o regola temporanea scaduta da rimuovere.');
            return Command::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("⚠️  Trovate {$count} regole temporanee scadute. [Dry-Run: nessuna eliminazione effettuata]");
            $this->line('ℹ️  Nota: I log registrati in log_operazioni non vengono mai toccati.');
            return Command::SUCCESS;
        }

        $deleted = $query->delete();

        $this->info("🧹 Rimosse {$deleted} regole temporanee scadute con successo.");
        $this->line('ℹ️  Nota: I dati storici e le Storyboard in log_operazioni rimangono preservati al 100%.');

        return Command::SUCCESS;
    }
}
