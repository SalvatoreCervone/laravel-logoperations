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
        $baseLevel = DB::transactionLevel();
        $response = $this->getJson('/test-transaction-rollback');
        $response->assertStatus(500);

        // La transazione aperta durante la richiesta deve essere stata annullata
        $this->assertEquals($baseLevel, DB::transactionLevel());

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

        // Middleware attivo nella pipeline globale
        $this->app->make(\Illuminate\Contracts\Http\Kernel::class)->pushMiddleware(LogOperationsMiddleware::class);

        Route::get('/unmonitored-route', function () {
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

    public function test_selective_mode_logs_routes_with_explicit_class_name(): void
    {
        config(['logoperations.mode' => 'selective']);

        Route::middleware(LogOperationsMiddleware::class)->get('/monitored-by-class', function () {
            return response()->json(['status' => 'ok']);
        });

        $response = $this->getJson('/monitored-by-class');
        $response->assertStatus(200);

        $this->assertDatabaseHas('log_operazioni', [
            'rotta' => '/monitored-by-class',
        ]);
    }

    public function test_selective_mode_catches_500_errors_on_unmonitored_routes_with_safety_net(): void
    {
        config([
            'logoperations.mode' => 'selective',
            'logoperations.log_uncaught_errors' => true,
        ]);

        $this->app->make(\Illuminate\Contracts\Http\Kernel::class)->pushMiddleware(LogOperationsMiddleware::class);

        Route::get('/unmonitored-crash', function () {
            return response()->json(['error' => 'Fatal crash'], 500);
        });

        $response = $this->getJson('/unmonitored-crash');
        $response->assertStatus(500);

        $this->assertDatabaseHas('log_operazioni', [
            'rotta' => '/unmonitored-crash',
            'codicehttp' => 500,
        ]);

        $log = OperationLog::where('rotta', '/unmonitored-crash')->first();
        $this->assertNotNull($log);
        $this->assertNotNull($log->stack_trace);
    }

    public function test_selective_mode_ignores_500_errors_on_unmonitored_routes_when_safety_net_disabled(): void
    {
        config([
            'logoperations.mode' => 'selective',
            'logoperations.log_uncaught_errors' => false,
        ]);

        $this->app->make(\Illuminate\Contracts\Http\Kernel::class)->pushMiddleware(LogOperationsMiddleware::class);

        Route::get('/unmonitored-crash-disabled', function () {
            return response()->json(['error' => 'Fatal crash'], 500);
        });

        $response = $this->getJson('/unmonitored-crash-disabled');
        $response->assertStatus(500);

        $this->assertDatabaseMissing('log_operazioni', [
            'rotta' => '/unmonitored-crash-disabled',
        ]);
    }

    public function test_it_excludes_get_requests_when_allowed_methods_excludes_get(): void
    {
        config(['logoperations.allowed_methods' => ['POST', 'PUT', 'PATCH', 'DELETE']]);

        $response = $this->getJson('/test-get');
        $response->assertStatus(200);

        $this->assertDatabaseMissing('log_operazioni', [
            'rotta' => '/test-get',
        ]);

        $postResponse = $this->postJson('/test-post', ['key' => 'value']);
        $postResponse->assertStatus(201);

        $this->assertDatabaseHas('log_operazioni', [
            'rotta' => '/test-post',
            'verbo' => 'post',
        ]);
    }

    public function test_only_on_error_does_not_capture_stack_trace_on_200(): void
    {
        config([
            'logoperations.allowed_methods' => ['*'],
            'logoperations.stack_trace.enabled' => true,
            'logoperations.stack_trace.only_on_error' => true,
        ]);

        $response = $this->postJson('/test-post', ['item' => 1]);
        $response->assertStatus(201);

        $log = OperationLog::where('rotta', '/test-post')->first();
        $this->assertNotNull($log);
        $this->assertNull($log->stack_trace);
    }

    public function test_only_on_error_does_not_inject_db_callers_into_custom_traces_on_200(): void
    {
        config([
            'logoperations.allowed_methods' => ['*'],
            'logoperations.stack_trace.enabled' => true,
            'logoperations.stack_trace.only_on_error' => true,
            'logoperations.stack_trace.trace_db_callers' => true,
        ]);

        Route::middleware(LogOperationsMiddleware::class)->get('/test-db-query-on-200', function () {
            DB::table('log_operazioni')->count();
            return response()->json(['status' => 'ok']);
        });

        $response = $this->getJson('/test-db-query-on-200');
        $response->assertStatus(200);

        $log = OperationLog::where('rotta', '/test-db-query-on-200')->first();
        $this->assertNotNull($log);
        $this->assertNull($log->stack_trace);
        $this->assertNull($log->custom_traces);
    }

    public function test_db_callers_are_capped_to_max_db_callers(): void
    {
        $tracer = new \SalvatoreCervone\LogOperations\Services\StackTracer([
            'enabled' => true,
            'trace_db_callers' => true,
            'max_db_callers' => 5,
        ]);

        for ($i = 0; $i < 10; $i++) {
            $tracer->recordDbCaller(['sql' => "SELECT $i"]);
        }

        $this->assertCount(5, $tracer->getDbCallers());
    }

    public function test_custom_dashboard_route_is_excluded_from_anti_loop(): void
    {
        config([
            'logoperations.mode' => 'all',
            'logoperations.dashboard.route' => 'custom-admin/logs-panel',
        ]);

        Route::middleware(LogOperationsMiddleware::class)->get('/custom-admin/logs-panel', function () {
            return response()->json(['dashboard' => 'ui']);
        });

        $response = $this->getJson('/custom-admin/logs-panel');
        $response->assertStatus(200);

        $this->assertDatabaseMissing('log_operazioni', [
            'rotta' => '/custom-admin/logs-panel',
        ]);
    }

    public function test_it_logs_flagged_get_route_with_parameters_and_querystring_in_selective_mode(): void
    {
        config(['logoperations.mode' => 'selective']);

        // Crea la regola per la rotta con parametro dinamico
        \SalvatoreCervone\LogOperations\Models\OperationRule::create([
            'type' => 'route',
            'target' => 'api/products/{id}',
            'http_methods' => ['GET'],
            'stack_level' => 'core',
            'is_active' => true,
        ]);
        app(\SalvatoreCervone\LogOperations\Services\RuleEngine::class)->flushCache();

        Route::middleware(LogOperationsMiddleware::class)->get('/api/products/{id}', function ($id) {
            return response()->json(['product_id' => $id]);
        });

        // Esegui la richiesta GET con parametro di rotta e querystring
        $response = $this->getJson('/api/products/42?color=blue&size=M');
        $response->assertStatus(200);

        // Verifica che sia stato loggato nonostante la modalità sia selective
        $log = OperationLog::where('rotta', 'like', '/api/products/42%')->first();
        $this->assertNotNull($log, 'Il log per la rotta GET con parametri deve esistere.');
        $this->assertEquals('get', $log->verbo);
        $this->assertEquals(200, $log->codicehttp);

        // Verifica che i parametri di rotta e query string siano stati catturati correttamente
        $this->assertIsArray($log->parametri);
        $this->assertEquals('42', $log->parametri['route']['id'] ?? null);
        $this->assertEquals('blue', $log->parametri['querystring']['color'] ?? null);
        $this->assertEquals('M', $log->parametri['querystring']['size'] ?? null);
    }

    public function test_auto_register_middleware_injects_into_web_and_api_groups(): void
    {
        $router = app('router');
        $groups = $router->getMiddlewareGroups();

        $this->assertContains(LogOperationsMiddleware::class, $groups['web']);
        $this->assertContains(LogOperationsMiddleware::class, $groups['api']);
    }

    public function test_route_rule_with_core_stack_captures_core_frames_on_200_even_when_only_on_error_is_true(): void
    {
        config([
            'logoperations.mode' => 'selective',
            'logoperations.stack_trace.enabled' => true,
            'logoperations.stack_trace.only_on_error' => true,
        ]);

        \SalvatoreCervone\LogOperations\Models\OperationRule::create([
            'type' => 'route',
            'target' => 'api/test-core-stack',
            'http_methods' => ['GET'],
            'stack_level' => 'core',
            'is_active' => true,
        ]);
        app(\SalvatoreCervone\LogOperations\Services\RuleEngine::class)->flushCache();

        Route::middleware(LogOperationsMiddleware::class)->get('/api/test-core-stack', function () {
            return response()->json(['status' => 'ok']);
        });

        $response = $this->getJson('/api/test-core-stack');
        $response->assertStatus(200);

        $log = OperationLog::where('rotta', '/api/test-core-stack')->first();
        $this->assertNotNull($log);
        $this->assertNotNull($log->stack_trace, 'Lo stack trace deve essere presente per il livello core anche su 200 OK');
        $this->assertNotEmpty($log->stack_trace);
        // Tutti i frame catturati devono essere 'is_core' = true
        foreach ($log->stack_trace as $frame) {
            $this->assertTrue($frame['is_core']);
        }
    }

    public function test_route_rule_with_full_stack_captures_full_frames_on_200_even_when_only_on_error_is_true(): void
    {
        config([
            'logoperations.mode' => 'selective',
            'logoperations.stack_trace.enabled' => true,
            'logoperations.stack_trace.only_on_error' => true,
        ]);

        \SalvatoreCervone\LogOperations\Models\OperationRule::create([
            'type' => 'route',
            'target' => 'api/test-full-stack',
            'http_methods' => ['GET'],
            'stack_level' => 'full',
            'is_active' => true,
        ]);
        app(\SalvatoreCervone\LogOperations\Services\RuleEngine::class)->flushCache();

        Route::middleware(LogOperationsMiddleware::class)->get('/api/test-full-stack', function () {
            return response()->json(['status' => 'ok']);
        });

        $response = $this->getJson('/api/test-full-stack');
        $response->assertStatus(200);

        $log = OperationLog::where('rotta', '/api/test-full-stack')->first();
        $this->assertNotNull($log);
        $this->assertNotNull($log->stack_trace, 'Lo stack trace deve essere presente per il livello full');
        $this->assertNotEmpty($log->stack_trace);
        // Nel livello full ci sono anche i frame del framework/pipeline
        $this->assertGreaterThanOrEqual(1, count($log->stack_trace));
    }

    public function test_route_rule_with_base_stack_returns_null_stack_on_200(): void
    {
        config([
            'logoperations.mode' => 'selective',
            'logoperations.stack_trace.enabled' => true,
            'logoperations.stack_trace.only_on_error' => true,
        ]);

        \SalvatoreCervone\LogOperations\Models\OperationRule::create([
            'type' => 'route',
            'target' => 'api/test-base-stack',
            'http_methods' => ['GET'],
            'stack_level' => 'base',
            'is_active' => true,
        ]);
        app(\SalvatoreCervone\LogOperations\Services\RuleEngine::class)->flushCache();

        Route::middleware(LogOperationsMiddleware::class)->get('/api/test-base-stack', function () {
            return response()->json(['status' => 'ok']);
        });

        $response = $this->getJson('/api/test-base-stack');
        $response->assertStatus(200);

        $log = OperationLog::where('rotta', '/api/test-base-stack')->first();
        $this->assertNotNull($log);
        $this->assertNull($log->stack_trace, 'Lo stack trace deve essere null per il livello base su 200 OK');
    }

    public function test_excluded_status_codes_are_not_logged_even_if_route_is_flagged(): void
    {
        config([
            'logoperations.mode' => 'selective',
            'logoperations.excluded_status_codes' => [422],
        ]);

        \SalvatoreCervone\LogOperations\Models\OperationRule::create([
            'type' => 'route',
            'target' => 'api/test-validation-route',
            'http_methods' => ['POST'],
            'stack_level' => 'core',
            'is_active' => true,
        ]);
        app(\SalvatoreCervone\LogOperations\Services\RuleEngine::class)->flushCache();

        Route::middleware(LogOperationsMiddleware::class)->post('/api/test-validation-route', function () {
            return response()->json(['errors' => ['name' => ['Campo obbligatorio']]], 422);
        });

        $response = $this->postJson('/api/test-validation-route', []);
        $response->assertStatus(422);

        // Il codice 422 è escluso da config, quindi NON deve essere loggato
        $this->assertDatabaseMissing('log_operazioni', [
            'rotta' => '/api/test-validation-route',
        ]);
    }

    public function test_status_code_is_logged_when_removed_from_excluded_status_codes(): void
    {
        config([
            'logoperations.mode' => 'selective',
            'logoperations.excluded_status_codes' => [], // nessun codice escluso
        ]);

        \SalvatoreCervone\LogOperations\Models\OperationRule::create([
            'type' => 'route',
            'target' => 'api/test-validation-route-allowed',
            'http_methods' => ['POST'],
            'stack_level' => 'core',
            'is_active' => true,
        ]);
        app(\SalvatoreCervone\LogOperations\Services\RuleEngine::class)->flushCache();

        Route::middleware(LogOperationsMiddleware::class)->post('/api/test-validation-route-allowed', function () {
            return response()->json(['errors' => ['name' => ['Campo obbligatorio']]], 422);
        });

        $response = $this->postJson('/api/test-validation-route-allowed', []);
        $response->assertStatus(422);

        // Ora che 422 non è tra gli esclusi, deve essere loggato
        $this->assertDatabaseHas('log_operazioni', [
            'rotta' => '/api/test-validation-route-allowed',
            'codicehttp' => 422,
        ]);
    }
}
