<?php

namespace SalvatoreCervone\LogOperations\Console\Commands;

use Illuminate\Console\Command;
use SalvatoreCervone\LogOperations\Services\AlertNotificationService;

class CheckAlertsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logoperations:check-alerts
                            {--window= : Finestra di osservazione in minuti}
                            {--threshold= : Soglia percentuale di errore (es. 15 per 15%)}
                            {--min-requests= : Numero minimo di richieste per calcolare la percentuale}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica il tasso di errore nelle operazioni recenti e invia notifiche multi-canale se supera la soglia';

    /**
     * Execute the console command.
     */
    public function handle(AlertNotificationService $alertService): int
    {
        $window = $this->option('window') ? (int) $this->option('window') : null;
        $threshold = $this->option('threshold') ? (float) $this->option('threshold') : null;
        $minRequests = $this->option('min-requests') ? (int) $this->option('min-requests') : null;

        $this->info('🔍 Controllo tasso di errore LogOperations in corso...');

        $result = $alertService->checkErrorRate($window, $threshold, $minRequests);

        $this->table(
            ['Parametro', 'Valore'],
            [
                ['Richieste Totali', $result['total_requests']],
                ['Richieste in Errore', $result['error_count']],
                ['Percentuale Errori', "{$result['error_rate']}%"],
                ['Soglia Configurata', "{$result['threshold']}%"],
                ['Stato', $result['status']],
            ]
        );

        if ($result['alert_sent']) {
            $this->warn('⚠️ Soglia superata! Notifiche inviate ai canali configurati.');
            return Command::FAILURE;
        }

        $this->info('✅ Tasso di errore nei parametri di norma. Nessun alert necessario.');
        return Command::SUCCESS;
    }
}
