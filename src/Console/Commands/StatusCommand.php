<?php

namespace SalvatoreCervone\LogOperations\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use SalvatoreCervone\LogOperations\Models\OperationLog;
use SalvatoreCervone\LogOperations\Models\OperationRule;

class StatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logoperations:status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mostra lo stato di salute, le statistiche e la configurazione attiva di LogOperations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->newLine();
        $this->line('<fg=cyan;options=bold>=========================================================</>');
        $this->line('<fg=cyan;options=bold>       📊 LogOperations — Stato & Diagnostica           </>');
        $this->line('<fg=cyan;options=bold>=========================================================</>');
        $this->newLine();

        // 1. Stato Generale & Database
        $enabled = Config::get('logoperations.enabled', true);
        $mode = Config::get('logoperations.mode', 'all');
        $table = Config::get('logoperations.table_name', 'log_operazioni');
        $dbConn = Config::get('logoperations.database_connection') ?? 'default';

        try {
            $totalLogs = OperationLog::count();
            $totalErrors = OperationLog::where('codicehttp', '>=', 400)->count();
            $totalStoryboards = OperationLog::whereNotNull('subject_id')->count();
        } catch (\Throwable $e) {
            $totalLogs = 'Errore connessione DB: ' . $e->getMessage();
            $totalErrors = 'N/A';
            $totalStoryboards = 'N/A';
        }

        $this->info('🔹 STATO GENERALE');
        $this->table(
            ['Parametro', 'Valore'],
            [
                ['Logging Globale', $enabled ? '<fg=green>ABILITATO (ON)</>' : '<fg=red>DISABILITATO (OFF)</>'],
                ['Modalità Tracciamento', strtoupper($mode) . ($mode === 'selective' ? ' (Solo rotte attivate in Studio)' : ' (Tracciamento esteso)')],
                ['Tabella Database', $table],
                ['Connessione DB', $dbConn],
                ['Log Totali Archiviati', is_numeric($totalLogs) ? number_format($totalLogs, 0, ',', '.') : $totalLogs],
                ['Log con Errori (4xx/5xx)', is_numeric($totalErrors) ? number_format($totalErrors, 0, ',', '.') : $totalErrors],
                ['Eventi Storyboard Entità', is_numeric($totalStoryboards) ? number_format($totalStoryboards, 0, ',', '.') : $totalStoryboards],
            ]
        );

        // 2. Tracking Studio & Regole Dinamiche
        try {
            $activeRoutes = OperationRule::where('type', 'route')->where('is_active', true)->count();
            $activeMethods = OperationRule::where('type', 'method')->where('is_active', true)->count();
            $activeSessions = OperationRule::where('type', 'user_session')
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->count();
        } catch (\Throwable $e) {
            $activeRoutes = 0;
            $activeMethods = 0;
            $activeSessions = 0;
        }

        $this->info('🔹 CENTRO DI CONTROLLO ZERO-CODE (TRACKING STUDIO)');
        $this->table(
            ['Tipo Regola', 'Attive'],
            [
                ['Rotte Web/API tracciate dinamicamente', $activeRoutes],
                ['Metodi di Servizio con Proxy Interceptor', $activeMethods],
                ['Sessioni di Live Monitoring Utente in corso', $activeSessions > 0 ? "<fg=green>{$activeSessions}</>" : '0'],
            ]
        );

        // 3. Sistema di Alerting
        $alertsEnabled = Config::get('logoperations.alerts.enabled', false);
        $alertChannels = Config::get('logoperations.alerts.channels', []);
        $throttleMin = Config::get('logoperations.alerts.throttle_minutes', 15);
        $rollbackAlert = Config::get('logoperations.alerts.notify_on_rollback', true);

        $this->info('🔹 SISTEMA DI ALERTING & NOTIFICHE');
        $this->table(
            ['Componente', 'Stato'],
            [
                ['Alerting Globale', $alertsEnabled ? '<fg=green>ATTIVO</>' : '<fg=yellow>DISATTIVO (configurabile)</>'],
                ['Canali Abilitati', !empty($alertChannels) ? implode(', ', $alertChannels) : 'Nessuno'],
                ['Anti-Flood Throttle', "{$throttleMin} minuti"],
                ['Allarme Immediato su Rollback SQL', $rollbackAlert ? '<fg=green>ATTIVO</>' : 'DISATTIVO'],
            ]
        );

        // 4. Retention Policy & Salvaguardia
        $retentionEnabled = Config::get('logoperations.retention.enabled', false);
        $retentionDays = Config::get('logoperations.retention.days', 365);
        $preserveStoryboards = Config::get('logoperations.retention.preserve_storyboards', true);
        $preserveErrors = Config::get('logoperations.retention.preserve_errors', true);

        $this->info('🔹 RETENTION POLICY & SALVAGUARDIA AUDIT TRAIL');
        $this->table(
            ['Regola di Protezione', 'Configurazione'],
            [
                ['Cancellazione Automatica Log', $retentionEnabled ? "<fg=yellow>ATTIVA ({$retentionDays} giorni)</>" : '<fg=green>DISABILITATA (Memoria permanente garantita)</>'],
                ['Salvaguardia Assoluta Storyboard 🛡️', $preserveStoryboards ? '<fg=green>PROTETTE AL 100% (Mai eliminate)</>' : '<fg=red>NON PROTETTO</>'],
                ['Salvaguardia Errori & Rollback 🛡️', $preserveErrors ? '<fg=green>PROTETTI AL 100% (Mai eliminati)</>' : '<fg=red>NON PROTETTO</>'],
            ]
        );

        $this->newLine();
        return Command::SUCCESS;
    }
}
