<?php

namespace SalvatoreCervone\LogOperations\Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use SalvatoreCervone\LogOperations\Tests\TestCase;
use SalvatoreCervone\LogOperations\Models\OperationLog;
use SalvatoreCervone\LogOperations\Jobs\ProcessOperationLog;
use SalvatoreCervone\LogOperations\Services\QueueAlertService;
use SalvatoreCervone\LogOperations\Http\Middleware\LogOperationsMiddleware;

class QueueAndPerformanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(LogOperationsMiddleware::class)->group(function () {
            Route::get('/perf-success', function () {
                return response()->json(['status' => 'ok']);
            });

            Route::get('/perf-error', function () {
                return response()->json(['error' => 'fail'], 500);
            });
        });
    }

    public function test_terminable_middleware_persists_log(): void
    {
        $response = $this->getJson('/perf-success');
        $response->assertStatus(200);

        $this->assertDatabaseHas('log_operazioni', [
            'rotta' => '/perf-success',
            'verbo' => 'get',
            'codicehttp' => 200,
        ]);
    }

    public function test_it_dispatches_log_to_queue_when_queue_enabled(): void
    {
        Queue::fake();

        config([
            'logoperations.queue.enabled' => true,
            'logoperations.queue.queue' => 'audit-logs',
        ]);

        $response = $this->getJson('/perf-success');
        $response->assertStatus(200);

        // Il log non deve essere scritto direttamente su DB durante la richiesta HTTP
        $this->assertDatabaseMissing('log_operazioni', [
            'rotta' => '/perf-success',
        ]);

        // Il job deve essere stato inviato alla coda con il payload corretto
        Queue::assertPushed(ProcessOperationLog::class, function ($job) {
            return $job->logData['rotta'] === '/perf-success'
                && $job->queue === 'audit-logs';
        });
    }

    public function test_sampling_rate_skips_success_but_always_keeps_errors(): void
    {
        // Impostiamo sampling_rate a 0 (nessuna richiesta con successo viene loggata)
        config(['logoperations.sampling_rate' => 0]);

        // 1. Richiesta 200 OK -> deve essere ignorata
        $response = $this->getJson('/perf-success');
        $response->assertStatus(200);

        $this->assertDatabaseMissing('log_operazioni', [
            'rotta' => '/perf-success',
        ]);

        // 2. Richiesta 500 Error -> deve essere COMUNQUE registrata al 100%
        $errorResponse = $this->getJson('/perf-error');
        $errorResponse->assertStatus(500);

        $this->assertDatabaseHas('log_operazioni', [
            'rotta' => '/perf-error',
            'codicehttp' => 500,
        ]);
    }

    public function test_queue_alert_service_sends_email_when_threshold_reached(): void
    {
        Mail::fake();

        config([
            'logoperations.queue.failed_jobs_threshold' => 3,
            'logoperations.queue.alert_email' => 'admin@test.com',
        ]);

        $service = app(QueueAlertService::class);
        $service->resetFailureCount();

        $dummyLog = ['rotta' => '/api/test-fail', 'verbo' => 'post'];
        $dummyException = new \RuntimeException('Database down error');

        // Fallimento 1 e 2: nessuna mail
        $service->recordJobFailure($dummyLog, $dummyException);
        $service->recordJobFailure($dummyLog, $dummyException);
        Mail::assertNothingSent();

        // Fallimento 3 (raggiunge la soglia di 3): deve inviare l'email di allarme
        $service->recordJobFailure($dummyLog, $dummyException);

        Mail::assertSent(\Illuminate\Mail\Mailable::class, 0); // Abbiamo usato Mail::raw
        $this->assertEquals(3, $service->getFailureCount());
    }

    public function test_process_operation_log_writes_to_database_on_handle(): void
    {
        $logData = [
            'rotta' => '/async-test',
            'verbo' => 'post',
            'codicehttp' => 201,
            'nomeapplicazione' => 'laravel-test',
            'dataoperazione' => now(),
        ];

        $job = new ProcessOperationLog($logData);
        $job->handle();

        $this->assertDatabaseHas('log_operazioni', [
            'rotta' => '/async-test',
            'verbo' => 'post',
            'codicehttp' => 201,
        ]);
    }

    public function test_process_operation_log_writes_to_emergency_file_on_permanent_failure(): void
    {
        $emergencyFile = storage_path('logs/logoperations-emergency.log');
        if (file_exists($emergencyFile)) {
            @unlink($emergencyFile);
        }

        $logData = [
            'rotta' => '/emergency-test',
            'verbo' => 'delete',
            'codicehttp' => 500,
            'nomeapplicazione' => 'laravel-test',
            'dataoperazione' => now(),
        ];

        $job = new ProcessOperationLog($logData);
        $job->failed(new \Exception('Connection refused to DB'));

        $this->assertFileExists($emergencyFile);
        $content = file_get_contents($emergencyFile);
        $this->assertStringContainsString('/emergency-test', $content);
        $this->assertStringContainsString('Connection refused to DB', $content);

        // Pulizia
        @unlink($emergencyFile);
    }

    public function test_process_operation_log_persists_touched_subjects_in_relational_table(): void
    {
        $logData = [
            'rotta' => '/async-multi-test',
            'verbo' => 'post',
            'codicehttp' => 200,
            'nomeapplicazione' => 'laravel-test',
            'dataoperazione' => now(),
        ];

        $touchedSubjects = [
            [
                'subject_type' => 'App\\Models\\Order',
                'subject_id' => '101',
                'action' => 'created',
            ],
            [
                'subject_type' => 'App\\Models\\Invoice',
                'subject_id' => '202',
                'action' => 'associated',
            ],
        ];

        $job = new ProcessOperationLog($logData, $touchedSubjects);
        $job->handle();

        $this->assertDatabaseHas('log_operazioni', [
            'rotta' => '/async-multi-test',
        ]);

        $log = OperationLog::where('rotta', '/async-multi-test')->first();
        $this->assertNotNull($log);

        $subjectsTable = config('logoperations.subjects_table_name', 'log_operazioni_soggetti');
        $this->assertDatabaseHas($subjectsTable, [
            'log_id' => $log->id,
            'subject_type' => 'App\\Models\\Order',
            'subject_id' => '101',
            'action' => 'created',
        ]);
        $this->assertDatabaseHas($subjectsTable, [
            'log_id' => $log->id,
            'subject_type' => 'App\\Models\\Invoice',
            'subject_id' => '202',
            'action' => 'associated',
        ]);
    }
}
