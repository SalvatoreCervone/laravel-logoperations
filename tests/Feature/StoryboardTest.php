<?php

namespace SalvatoreCervone\LogOperations\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use SalvatoreCervone\LogOperations\Http\Middleware\LogOperationsMiddleware;
use SalvatoreCervone\LogOperations\Models\OperationLog;
use SalvatoreCervone\LogOperations\Tests\TestCase;
use SalvatoreCervone\LogOperations\Traits\HasOperationLogs;

/**
 * Modello Eloquent fittizio per il test dello Storyboard.
 */
class StoryboardTestOrder extends Model
{
    use HasOperationLogs;

    protected $table = 'test_orders';
    protected $guarded = [];
}

class StoryboardTest extends TestCase
{
    protected function defineDatabaseMigrations()
    {
        parent::defineDatabaseMigrations();

        // Creazione tabella di test per l'entità target
        Schema::create('test_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference');
            $table->decimal('total', 8, 2)->default(0);
            $table->timestamps();
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Binding esplicito per route model binding nei test
        Route::model('order', StoryboardTestOrder::class);

        // Rotte di test con LogOperationsMiddleware e SubstituteBindings per Route Model Binding
        Route::middleware([\Illuminate\Routing\Middleware\SubstituteBindings::class, LogOperationsMiddleware::class])->group(function () {
            Route::get('/test-orders/{order}', function (StoryboardTestOrder $order) {
                return response()->json($order);
            });

            Route::put('/test-orders/{order}', function (StoryboardTestOrder $order) {
                $order->update(request()->only('reference', 'total'));
                return response()->json($order);
            });

            Route::post('/test-orders/{order}/checkpoint', function (StoryboardTestOrder $order) {
                $order->logStep('Transazione approvata dal gateway bancario', [
                    'transaction_ref' => 'TX-9988',
                    'amount' => 150.00,
                ]);
                return response()->json(['status' => 'approved']);
            });

            Route::get('/test-orders/{order}/error', function (StoryboardTestOrder $order) {
                return response()->json(['error' => 'Fondi insufficienti'], 400);
            });
        });
    }

    public function test_it_automatically_captures_subject_via_route_model_binding(): void
    {
        $order = StoryboardTestOrder::create([
            'reference' => 'ORD-1001',
            'total' => 89.90,
        ]);

        $response = $this->getJson("/test-orders/{$order->id}");
        $response->assertStatus(200);

        $this->assertDatabaseHas('log_operazioni', [
            'rotta' => "/test-orders/{$order->id}",
            'verbo' => 'get',
            'subject_id' => (string) $order->id,
            'subject_type' => StoryboardTestOrder::class,
        ]);
    }

    public function test_it_captures_update_operations_with_subject(): void
    {
        $order = StoryboardTestOrder::create([
            'reference' => 'ORD-1002',
            'total' => 120.00,
        ]);

        $response = $this->putJson("/test-orders/{$order->id}", [
            'reference' => 'ORD-1002-MOD',
            'total' => 135.50,
        ]);
        $response->assertStatus(200);

        $this->assertDatabaseHas('log_operazioni', [
            'rotta' => "/test-orders/{$order->id}",
            'verbo' => 'put',
            'subject_id' => (string) $order->id,
            'subject_type' => StoryboardTestOrder::class,
        ]);
    }

    public function test_has_operation_logs_trait_records_log_step(): void
    {
        $order = StoryboardTestOrder::create([
            'reference' => 'ORD-1003',
            'total' => 250.00,
        ]);

        $log = $order->logStep('Spedizione partita con corriere DHL', [
            'tracking_number' => 'DHL123456789',
        ]);

        $this->assertInstanceOf(OperationLog::class, $log);
        $this->assertEquals((string) $order->id, $log->subject_id);
        $this->assertEquals(StoryboardTestOrder::class, $log->subject_type);
        $this->assertEquals('STEP', $log->verbo);
        $this->assertEquals('Spedizione partita con corriere DHL', $log->custom_traces['steps'][0]['label']);
        $this->assertEquals('DHL123456789', $log->custom_traces['steps'][0]['context']['tracking_number']);
    }

    public function test_has_operation_logs_storyboard_method_returns_chronological_timeline(): void
    {
        $order = StoryboardTestOrder::create([
            'reference' => 'ORD-1004',
            'total' => 45.00,
        ]);

        $this->getJson("/test-orders/{$order->id}");
        $order->logStep('Step Intermedio 1');
        $this->putJson("/test-orders/{$order->id}", ['total' => 50.00]);
        $order->logStep('Step Intermedio 2');

        $storyboard = $order->storyboard();

        $this->assertCount(4, $storyboard);
        $this->assertEquals('get', $storyboard[0]->verbo);
        $this->assertEquals('STEP', $storyboard[1]->verbo);
        $this->assertEquals('put', $storyboard[2]->verbo);
        $this->assertEquals('STEP', $storyboard[3]->verbo);

        // Test ordinamento decrescente
        $descStoryboard = $order->storyboard(null, 'desc');
        $this->assertEquals('STEP', $descStoryboard[0]->verbo);
        $this->assertEquals('get', $descStoryboard[3]->verbo);
    }

    public function test_storyboard_api_endpoint_returns_enriched_events_and_kpis(): void
    {
        $order = StoryboardTestOrder::create([
            'reference' => 'ORD-1005',
            'total' => 300.00,
        ]);

        // Genera sequenza: lettura, errore 422, checkpoint, update
        $this->getJson("/test-orders/{$order->id}");
        $this->getJson("/test-orders/{$order->id}/error");
        $this->postJson("/test-orders/{$order->id}/checkpoint");
        $this->putJson("/test-orders/{$order->id}", ['total' => 320.00]);

        $apiUrl = config('logoperations.api_prefix', 'api/logoperations');
        $response = $this->getJson("/{$apiUrl}/storyboard?subject_type=" . urlencode(StoryboardTestOrder::class) . "&subject_id={$order->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'subject' => ['type', 'id', 'label'],
                'kpis' => ['total_events', 'total_errors', 'total_rollbacks', 'total_checkpoints', 'first_activity', 'last_activity'],
                'events',
            ]);

        $data = $response->json();
        $this->assertEquals(5, $data['kpis']['total_events']); // 1 get, 1 error, 1 post (route), 1 checkpoint (logStep), 1 put
        $this->assertEquals(1, $data['kpis']['total_errors']);
        $this->assertEquals(1, $data['kpis']['total_checkpoints']);

        // Verifica classificazione eventi
        $hasErrorCategory = false;
        $hasCheckpointCategory = false;
        $hasUpdateCategory = false;

        foreach ($data['events'] as $event) {
            if ($event['classification']['category'] === 'error') {
                $hasErrorCategory = true;
            }
            if ($event['classification']['category'] === 'checkpoint') {
                $hasCheckpointCategory = true;
            }
            if ($event['classification']['category'] === 'update') {
                $hasUpdateCategory = true;
            }
        }

        $this->assertTrue($hasErrorCategory, 'Dovrebbe contenere la categoria error');
        $this->assertTrue($hasCheckpointCategory, 'Dovrebbe contenere la categoria checkpoint');
        $this->assertTrue($hasUpdateCategory, 'Dovrebbe contenere la categoria update');
    }

    public function test_storyboard_api_by_route_parameters(): void
    {
        $order = StoryboardTestOrder::create([
            'reference' => 'ORD-1006',
            'total' => 15.00,
        ]);

        $this->getJson("/test-orders/{$order->id}");

        $apiUrl = config('logoperations.api_prefix', 'api/logoperations');
        $encodedType = urlencode(StoryboardTestOrder::class);

        $response = $this->getJson("/{$apiUrl}/storyboard/{$encodedType}/{$order->id}");
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('events'));
    }

    public function test_storyboard_api_respects_gate_authorization(): void
    {
        config(['logoperations.allow_in_local' => false]);
        Gate::define('viewLogOperations', fn() => false);

        $apiUrl = config('logoperations.api_prefix', 'api/logoperations');
        $encodedType = urlencode(StoryboardTestOrder::class);

        $response = $this->getJson("/{$apiUrl}/storyboard/{$encodedType}/1");
        $response->assertStatus(403);
    }

    public function test_storyboard_api_filters_by_category_and_search(): void
    {
        $order = StoryboardTestOrder::create([
            'reference' => 'ORD-1007',
            'total' => 99.00,
        ]);

        $this->getJson("/test-orders/{$order->id}");
        $this->getJson("/test-orders/{$order->id}/error");
        $order->logStep('Etichetta spedizione generata');

        $apiUrl = config('logoperations.api_prefix', 'api/logoperations');
        $encodedType = urlencode(StoryboardTestOrder::class);

        // Filtro solo errori
        $resError = $this->getJson("/{$apiUrl}/storyboard?subject_type={$encodedType}&subject_id={$order->id}&has_error=1");
        $resError->assertStatus(200);
        $this->assertCount(1, $resError->json('events'));
        $this->assertEquals(400, $resError->json('events.0.codicehttp'));

        // Filtro solo checkpoint
        $resCheckpoint = $this->getJson("/{$apiUrl}/storyboard?subject_type={$encodedType}&subject_id={$order->id}&event_type=checkpoint");
        $resCheckpoint->assertStatus(200);
        $this->assertCount(1, $resCheckpoint->json('events'));
        $this->assertEquals('STEP', $resCheckpoint->json('events.0.verbo'));

        // Ricerca testuale
        $resSearch = $this->getJson("/{$apiUrl}/storyboard?subject_type={$encodedType}&subject_id={$order->id}&search=error");
        $resSearch->assertStatus(200);
        $this->assertCount(1, $resSearch->json('events'));
        $this->assertEquals(400, $resSearch->json('events.0.codicehttp'));
    }

    public function test_storyboard_subjects_api_returns_distinct_tracked_subjects(): void
    {
        $order = StoryboardTestOrder::create([
            'reference' => 'ORD-1008',
            'total' => 110.00,
        ]);

        $this->getJson("/test-orders/{$order->id}");

        $apiUrl = config('logoperations.api_prefix', 'api/logoperations');
        $response = $this->getJson("/{$apiUrl}/storyboard/subjects");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => (string) $order->id,
                'type' => StoryboardTestOrder::class,
                'label' => "StoryboardTestOrder #{$order->id}",
            ]);
    }
}
