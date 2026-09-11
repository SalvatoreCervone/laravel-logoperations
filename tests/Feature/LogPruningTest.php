<?php

namespace SalvatoreCervone\LogOperations\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SalvatoreCervone\LogOperations\Models\OperationLog;
use SalvatoreCervone\LogOperations\Models\OperationRule;
use SalvatoreCervone\LogOperations\Tests\TestCase;

class LogPruningTest extends TestCase
{
    use RefreshDatabase;

    public function test_clear_expired_rules_deletes_only_expired_rules_and_preserves_logs(): void
    {
        // Regola permanente (senza scadenza)
        OperationRule::create([
            'type' => 'route',
            'target' => 'api/orders',
            'is_active' => true,
            'expires_at' => null,
        ]);

        // Sessione utente ancora attiva (scade tra 10 minuti)
        OperationRule::create([
            'type' => 'user_session',
            'target' => '1',
            'is_active' => true,
            'expires_at' => now()->addMinutes(10),
        ]);

        // Sessione utente scaduta 1 ora fa
        OperationRule::create([
            'type' => 'user_session',
            'target' => '2',
            'is_active' => true,
            'expires_at' => now()->subHour(),
        ]);

        // Log operativo registrato nel DB
        $log = OperationLog::create([
            'rotta' => '/api/orders',
            'verbo' => 'get',
            'codicehttp' => 200,
            'client_ip' => '127.0.0.1',
            'dataoperazione' => now(),
        ]);

        $this->artisan('logoperations:clear-expired-rules')
            ->expectsOutputToContain('Rimosse 1 regole temporanee scadute')
            ->assertExitCode(0);

        // Verifica che solo la regola scaduta sia stata rimossa
        $this->assertDatabaseCount('log_operazioni_regole', 2);
        $this->assertDatabaseMissing('log_operazioni_regole', ['target' => '2']);
        $this->assertDatabaseHas('log_operazioni_regole', ['target' => 'api/orders']);
        $this->assertDatabaseHas('log_operazioni_regole', ['target' => '1']);

        // Verifica che la tabella log_operazioni non sia minimamente toccata
        $this->assertDatabaseCount('log_operazioni', 1);
        $this->assertDatabaseHas('log_operazioni', ['id' => $log->id]);
    }

    public function test_clear_expired_rules_dry_run_does_not_delete(): void
    {
        OperationRule::create([
            'type' => 'user_session',
            'target' => '99',
            'is_active' => true,
            'expires_at' => now()->subDays(2),
        ]);

        $this->artisan('logoperations:clear-expired-rules --dry-run')
            ->expectsOutputToContain('Dry-Run: nessuna eliminazione effettuata')
            ->assertExitCode(0);

        $this->assertDatabaseCount('log_operazioni_regole', 1);
    }

    public function test_prune_logs_command_strictly_preserves_storyboards(): void
    {
        $oldDate = now()->subDays(60);

        // 1. Record con Storyboard (Ordine aziendale collegato)
        $storyboardLog = OperationLog::create([
            'rotta' => '/api/orders/10',
            'verbo' => 'get',
            'codicehttp' => 200,
            'client_ip' => '127.0.0.1',
            'subject_type' => 'App\Models\Order',
            'subject_id' => '10',
            'dataoperazione' => $oldDate,
        ]);

        // 2. Record generico anonimo non collegato ad alcuna entità
        $genericLog = OperationLog::create([
            'rotta' => '/api/lookup-countries',
            'verbo' => 'get',
            'codicehttp' => 200,
            'client_ip' => '127.0.0.1',
            'subject_type' => null,
            'subject_id' => null,
            'dataoperazione' => $oldDate,
        ]);

        $this->artisan('logoperations:prune --days=30 --force')
            ->expectsOutputToContain('Record Storyboard Protetti 🛡️')
            ->assertExitCode(0);

        // Il record generico è stato pulito
        $this->assertDatabaseMissing('log_operazioni', ['id' => $genericLog->id]);

        // La Storyboard dell'ordine è stata rigorosamente preservata e protetta!
        $this->assertDatabaseHas('log_operazioni', ['id' => $storyboardLog->id]);
    }

    public function test_prune_logs_command_strictly_preserves_errors(): void
    {
        $oldDate = now()->subDays(60);

        // Record con errore HTTP 500
        $error500Log = OperationLog::create([
            'rotta' => '/api/payment/checkout',
            'verbo' => 'get',
            'codicehttp' => 500,
            'client_ip' => '127.0.0.1',
            'dataoperazione' => $oldDate,
        ]);

        // Record con eccezione applicativa
        $exceptionLog = OperationLog::create([
            'rotta' => '/api/sync',
            'verbo' => 'get',
            'codicehttp' => 200,
            'error' => 'Connection to payment provider timed out',
            'client_ip' => '127.0.0.1',
            'dataoperazione' => $oldDate,
        ]);

        // Record 200 OK senza errori
        $cleanLog = OperationLog::create([
            'rotta' => '/api/ping',
            'verbo' => 'get',
            'codicehttp' => 200,
            'client_ip' => '127.0.0.1',
            'dataoperazione' => $oldDate,
        ]);

        $this->artisan('logoperations:prune --days=30 --force')
            ->assertExitCode(0);

        // Il ping pulito è stato eliminato
        $this->assertDatabaseMissing('log_operazioni', ['id' => $cleanLog->id]);

        // Entrambi i record con anomalie sono rigorosamente protetti
        $this->assertDatabaseHas('log_operazioni', ['id' => $error500Log->id]);
        $this->assertDatabaseHas('log_operazioni', ['id' => $exceptionLog->id]);
    }

    public function test_prune_logs_command_dry_run_preserves_all_records(): void
    {
        $oldDate = now()->subDays(45);

        OperationLog::create([
            'rotta' => '/api/test',
            'verbo' => 'get',
            'codicehttp' => 200,
            'client_ip' => '127.0.0.1',
            'dataoperazione' => $oldDate,
        ]);

        $this->artisan('logoperations:prune --days=30 --dry-run')
            ->expectsOutputToContain('[Dry-Run attivo]: verrebbero rimossi 1 log non protetti')
            ->assertExitCode(0);

        $this->assertDatabaseCount('log_operazioni', 1);
    }

    public function test_prune_logs_command_supports_hours_option(): void
    {
        // 5 ore fa (deve essere eliminato con --hours=3)
        $olderLog = OperationLog::create([
            'rotta' => '/api/five-hours-ago',
            'verbo' => 'get',
            'codicehttp' => 200,
            'client_ip' => '127.0.0.1',
            'dataoperazione' => now()->subHours(5),
        ]);

        // 1 ora fa (deve essere preservato con --hours=3)
        $newerLog = OperationLog::create([
            'rotta' => '/api/one-hour-ago',
            'verbo' => 'get',
            'codicehttp' => 200,
            'client_ip' => '127.0.0.1',
            'dataoperazione' => now()->subHour(),
        ]);

        $this->artisan('logoperations:prune --hours=3 --force')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('log_operazioni', ['id' => $olderLog->id]);
        $this->assertDatabaseHas('log_operazioni', ['id' => $newerLog->id]);
    }

    public function test_operation_rule_mass_prunable_trait(): void
    {
        OperationRule::create([
            'type' => 'user_session',
            'target' => 'expired-user',
            'is_active' => true,
            'expires_at' => now()->subMinute(),
        ]);

        OperationRule::create([
            'type' => 'user_session',
            'target' => 'active-user',
            'is_active' => true,
            'expires_at' => now()->addMinutes(15),
        ]);

        $prunableCount = (new OperationRule)->prunable()->count();
        $this->assertEquals(1, $prunableCount);
    }

    public function test_prune_logs_preserves_logs_with_relational_subjects(): void
    {
        // Log vecchio di 400 giorni SENZA subject primario, ma CON soggetto collegato in log_operazioni_soggetti
        $storyboardLog = OperationLog::create([
            'rotta' => '/api/old-batch-operation',
            'verbo' => 'post',
            'codicehttp' => 200,
            'client_ip' => '127.0.0.1',
            'dataoperazione' => now()->subDays(400),
            'subject_type' => null,
            'subject_id' => null,
        ]);

        \SalvatoreCervone\LogOperations\Models\OperationSubject::create([
            'log_id' => $storyboardLog->id,
            'subject_type' => 'App\\Models\\Order',
            'subject_id' => '999',
            'action' => 'created',
        ]);

        // Altro log vecchio SENZA alcun soggetto (deve essere eliminato)
        $orphanLog = OperationLog::create([
            'rotta' => '/api/old-orphan',
            'verbo' => 'get',
            'codicehttp' => 200,
            'client_ip' => '127.0.0.1',
            'dataoperazione' => now()->subDays(400),
            'subject_type' => null,
            'subject_id' => null,
        ]);

        $this->artisan('logoperations:prune --days=365 --keep-storyboards --force')
            ->assertExitCode(0);

        // Il log orfano deve essere stato eliminato
        $this->assertDatabaseMissing('log_operazioni', ['id' => $orphanLog->id]);

        // Il log collegato alla Storyboard dell'ordine 999 deve essere stato rigorosamente protetto!
        $this->assertDatabaseHas('log_operazioni', ['id' => $storyboardLog->id]);
        $this->assertDatabaseHas('log_operazioni_soggetti', [
            'log_id' => $storyboardLog->id,
            'subject_id' => '999',
        ]);
    }
}
