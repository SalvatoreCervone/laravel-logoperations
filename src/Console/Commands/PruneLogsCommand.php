<?php

namespace SalvatoreCervone\LogOperations\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use SalvatoreCervone\LogOperations\Models\OperationLog;

class PruneLogsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logoperations:prune
                            {--days= : Numero di giorni di conservazione (default: config)}
                            {--hours= : Numero di ore di conservazione (ha priorità su days)}
                            {--keep-errors : Preserva rigorosamente tutti i log con errori HTTP >= 400 o eccezioni}
                            {--keep-storyboards : Preserva rigorosamente tutti i log associati a una Storyboard (subject)}
                            {--chunk=1000 : Dimensione dei blocchi di cancellazione per prevenire lock del DB}
                            {--force : Esegue la pulizia senza richiedere conferma interattiva}
                            {--dry-run : Esegue una simulazione mostrando i record protetti senza cancellare}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Esegue la pulizia selettiva e protetta dei log storici preservando Storyboard ed errori critici';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = $this->option('hours') ? (int) $this->option('hours') : null;
        $days = $this->option('days') ? (int) $this->option('days') : null;

        if ($hours !== null && $hours > 0) {
            $cutoff = now()->subHours($hours);
            $retentionDesc = "{$hours} ore";
        } else {
            $days = $days ?? (int) Config::get('logoperations.retention.days', 365);
            $cutoff = now()->subDays($days);
            $retentionDesc = "{$days} giorni";
        }

        $keepStoryboards = $this->hasOption('keep-storyboards') && $this->option('keep-storyboards')
            ? true
            : (bool) Config::get('logoperations.retention.preserve_storyboards', true);

        $keepErrors = $this->hasOption('keep-errors') && $this->option('keep-errors')
            ? true
            : (bool) Config::get('logoperations.retention.preserve_errors', true);

        $preserveMutations = (bool) Config::get('logoperations.retention.preserve_mutations', true);
        $chunkSize = max(100, (int) ($this->option('chunk') ?? Config::get('logoperations.retention.chunk_size', 1000)));

        $this->info("🛡️  Verifica log operativi antecedenti alla soglia ({$retentionDesc} fa: {$cutoff->toDateTimeString()})...");

        // Conteggio totale record antecedenti
        $baseOlderQuery = OperationLog::where('dataoperazione', '<=', $cutoff);
        $totalOlder = (clone $baseOlderQuery)->count();

        if ($totalOlder === 0) {
            $this->info("✅ Nessun record presente più vecchio di {$retentionDesc}. Database già pulito.");
            return Command::SUCCESS;
        }

        // Conteggio record Storyboard protetti
        $storyboardProtected = (clone $baseOlderQuery)
            ->where(function ($q) {
                $q->whereNotNull('subject_type')
                  ->orWhereNotNull('subject_id');
            })
            ->count();

        // Conteggio record con errori protetti
        $errorsProtected = (clone $baseOlderQuery)
            ->where(function ($q) {
                $q->where('codicehttp', '>=', 400)
                  ->orWhere(function ($sub) {
                      $sub->whereNotNull('error')->where('error', '!=', '');
                  });
            })
            ->count();

        // Costruzione query dei soli record effettivamente eleggibili per la rimozione
        $eligibleQuery = clone $baseOlderQuery;

        if ($keepStoryboards) {
            $eligibleQuery->whereNull('subject_type')->whereNull('subject_id');
        }

        if ($keepErrors) {
            $eligibleQuery->where('codicehttp', '<', 400)
                ->where(function ($q) {
                    $q->whereNull('error')->orWhere('error', '');
                });
        }

        if ($preserveMutations) {
            $eligibleQuery->whereIn('verbo', ['get', 'head', 'options']);
        }

        $eligibleCount = (clone $eligibleQuery)->count();

        $this->table(
            ['Parametro / Categoria', 'Conteggio'],
            [
                ['Data Limite Retention', $cutoff->toDateTimeString() . " ({$retentionDesc})"],
                ['Record Totali Antecedenti', $totalOlder],
                ['Record Storyboard Protetti 🛡️', $storyboardProtected],
                ['Record Errori Protetti 🛡️', $errorsProtected],
                ['Record Eleggibili per Rimozione', $eligibleCount],
            ]
        );

        if ($eligibleCount === 0) {
            $this->info('✅ Tutti i record antecedenti alla soglia sono protetti (Storyboard o Errori). Nessuna rimozione necessaria.');
            return Command::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("⚠️  [Dry-Run attivo]: verrebbero rimossi {$eligibleCount} log non protetti. Nessuna cancellazione eseguita.");
            return Command::SUCCESS;
        }

        if (!$this->option('force')) {
            $confirm = $this->confirm("Sei sicuro di voler eliminare definitivamente {$eligibleCount} record non protetti?", false);
            if (!$confirm) {
                $this->warn('Operazione annullata dall\'utente. Nessun record eliminato.');
                return Command::SUCCESS;
            }
        }

        $this->info("🧹 Eliminazione sicura a blocchi di {$chunkSize} record in corso...");

        $deletedCount = 0;
        do {
            $ids = (clone $eligibleQuery)->limit($chunkSize)->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }

            $deleted = OperationLog::whereIn('id', $ids)->delete();
            $deletedCount += $deleted;
            $this->line("  ... eliminati {$deletedCount} / {$eligibleCount} record");
        } while ($ids->count() >= $chunkSize);

        $this->info("✨ Pulizia completata con successo! Eliminati {$deletedCount} log obsoleti.");
        $this->line("🛡️  Tutti i record Storyboard ({$storyboardProtected}) ed Errori ({$errorsProtected}) sono rimasti perfettamente intatti nel database.");

        return Command::SUCCESS;
    }
}
