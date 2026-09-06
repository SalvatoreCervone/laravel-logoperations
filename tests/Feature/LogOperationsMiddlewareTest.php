<?php

namespace SalvatoreCervone\LogOperations\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use SalvatoreCervone\LogOperations\Tests\TestCase;
use SalvatoreCervone\LogOperations\Models\OperationLog;
use SalvatoreCervone\LogOperations\Http\Middleware\LogOperationsMiddleware;

class LogOperationsMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Registrazione rotte di test
        Route::middleware(LogOperationsMiddleware::class)->group(function () {
            Route::get('/test-get', function () {
                return response()->json(['message' => 'success']);
            });

            Route::post('/test-post', function () {
                return response()->json(['message' => 'created'], 201);
            });

            Route::post('/test-sensitive', function () {
                return response()->json(['message' => 'ok']);
            });

            Route::get('/test-error', function () {
                return response()->json(['error' => 'not found'], 404);
            });

            Route::get('/test-transaction-rollback', function () {
                DB::beginTransaction();
                DB::table('log_operazioni_regole')->insert([
                    'type' => 'test_pending',
                    'target' => 'pending_record',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                // Lasciamo la transazione intenzionalmente aperta con un errore 500
                return response()->json(['error' => 'server error'], 500);
            });
        });
    }

    public function test_it_logs_http_get_requests(): void
    {
        $response = $this->getJson('/test-get');
        $response->assertStatus(200);

        $this->assertDatabaseHas('log_operazioni', [
            'rotta' => '/test-get',
            'verbo' => 'get',
            'codicehttp' => 200,
        ]);
    }

    public function test_it_logs_http_post_requests_with_parameters(): void
    {
        $payload = ['title' => 'Test Item', 'quantity' => 5];
        $response = $this->postJson('/test-post', $payload);
        $response->assertStatus(201);

        $log = OperationLog::where('rotta', '/test-post')->first();
        $this->assertNotNull($log);
        $this->assertEquals('post', $log->verbo);
        $this->assertEquals(201, $log->codicehttp);
        $this->assertEquals('Test Item', $log->parametri['post']['title'] ?? null);
    }

    public function test_it_masks_sensitive_fields(): void
    {
        $payload = [
            'username' => 'salvatore',
            'password' => 'segreta123',
            'token' => 'jwt-token-valore',
            'credit_card' => '1234-5678-9012-3456',
        ];

        $response = $this->postJson('/test-sensitive', $payload);
        $response->assertStatus(200);

        $log = OperationLog::where('rotta', '/test-sensitive')->first();
        $this->assertNotNull($log);
        $this->assertEquals('***MASKED***', $log->parametri['post']['password']);
        $this->assertEquals('***MASKED***', $log->parametri['post']['token']);
        $this->assertEquals('***MASKED***', $log->parametri['post']['credit_card']);
        $this->assertEquals('salvatore', $log->parametri['post']['username']);
    }

    public function test_it_rolls_back_unfinished_transactions_on_error(): void
    {
        $response = $this->getJson('/test-transaction-rollback');
        $response->assertStatus(500);

        // La transazione aperta deve essere stata annullata (rollback a livello 0)
        $this->assertEquals(0, DB::transactionLevel());

        // Il record temporaneo inserito nella transazione non deve esistere
        $this->assertDatabaseMissing('log_operazioni_regole', [
            'target' => 'pending_record',
        ]);

        // Il log deve indicare lo stato 'rolled_back'
        $log = OperationLog::where('rotta', '/test-transaction-rollback')->first();
        $this->assertNotNull($log);
        $this->assertEquals('rolled_back', $log->transaction_status);
        $this->assertEquals(500, $log->codicehttp);
    }

    public function test_selective_mode_does_not_log_unspecified_routes(): void
    {
        config(['logoperations.mode' => 'selective']);

        // Rotta registrata con middleware globale ma SENZA alias esplicito o regola attiva
        Route::middleware(LogOperationsMiddleware::class)->get('/unmonitored-route', function () {
            return response()->json(['status' => 'ok']);
        });

        $response = $this->getJson('/unmonitored-route');
        $response->assertStatus(200);

        $this->assertDatabaseMissing('log_operazioni', [
            'rotta' => '/unmonitored-route',
        ]);
    }

    public function test_selective_mode_logs_routes_with_explicit_alias(): void
    {
        config(['logoperations.mode' => 'selective']);

        Route::middleware('log.operations')->get('/monitored-route', function () {
            return response()->json(['status' => 'ok']);
        });

        $response = $this->getJson('/monitored-route');
        $response->assertStatus(200);

        $this->assertDatabaseHas('log_operazioni', [
            'rotta' => '/monitored-route',
        ]);
    }
}
