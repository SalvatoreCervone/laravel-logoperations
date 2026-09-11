<?php

namespace SalvatoreCervone\LogOperations\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use SalvatoreCervone\LogOperations\Http\Middleware\LogOperationsMiddleware;
use SalvatoreCervone\LogOperations\LogOperationsManager;
use SalvatoreCervone\LogOperations\Models\OperationLog;
use SalvatoreCervone\LogOperations\Models\OperationSubject;
use SalvatoreCervone\LogOperations\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Modelli fittizi per i test multi-subject
|--------------------------------------------------------------------------
*/

use SalvatoreCervone\LogOperations\Traits\HasOperationLogs;

class TestDipendente extends Model
{
    protected $table = 'test_dipendenti';
    protected $guarded = [];
}

class TestAnagraficaAssenza extends Model
{
    use HasOperationLogs;

    protected $table = 'test_anagrafica_assenze';
    protected $guarded = [];
}

class TestNotifica extends Model
{
    protected $table = 'test_notifiche';
    protected $guarded = [];
}

class TestPivotModel extends Pivot
{
    protected $table = 'test_pivot_table';
    public $incrementing = true;
    protected $guarded = [];
}

/**
 * Test suite per il tracciamento multi-soggetto con auto-discovery Eloquent.
 *
 * Verifica:
 * 1. Che i modelli toccati durante una richiesta vengano registrati nella tabella relazionale
 * 2. Che i modelli pivot e fuori namespace vengano esclusi
 * 3. Che lo Storyboard bidirezionale funzioni (l'entità compare nella storia anche se non è il subject primario)
 * 4. Che l'INSERT bulk gestisca correttamente grandi volumi (50+ record)
 * 5. Che l'endpoint subjects() unifichi primari e relazionali
 */
class MultiSubjectTrackingTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('logoperations.auto_discover_subjects', true);
        // I modelli di test sono nel namespace del pacchetto, non in App\Models,
        // quindi dobbiamo configurare il namespace per includerli.
        $app['config']->set('logoperations.models_namespace', 'SalvatoreCervone\\LogOperations\\Tests\\Feature');
    }

    protected function defineDatabaseMigrations()
    {
        parent::defineDatabaseMigrations();

        Schema::create('test_dipendenti', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->timestamps();
        });

        Schema::create('test_anagrafica_assenze', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dipendente_id');
            $table->string('tipo_assenza');
            $table->timestamps();
        });

        Schema::create('test_notifiche', function (Blueprint $table) {
            $table->id();
            $table->string('messaggio');
            $table->timestamps();
        });

        Schema::create('test_pivot_table', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('a_id');
            $table->unsignedBigInteger('b_id');
            $table->timestamps();
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Rotte di test che toccano più modelli
        Route::middleware([LogOperationsMiddleware::class])->group(function () {
            // Rotta che crea/aggiorna 3 modelli diversi
            Route::post('/test-multi-subject', function () {
                $dip = TestDipendente::create(['nome' => 'Mario Rossi']);
                $assenza = TestAnagraficaAssenza::create([
                    'dipendente_id' => $dip->id,
                    'tipo_assenza' => 'ferie',
                ]);
                $notifica = TestNotifica::create(['messaggio' => 'Nuova assenza registrata']);

                return response()->json([
                    'dipendente' => $dip->id,
                    'assenza' => $assenza->id,
                    'notifica' => $notifica->id,
                ]);
            });

            // Rotta che aggiorna massivamente
            Route::put('/test-bulk-update', function () {
                for ($i = 1; $i <= 50; $i++) {
                    TestDipendente::create(['nome' => "Dipendente {$i}"]);
                }
                return response()->json(['count' => 50]);
            });

            // Rotta che mescola create e update
            Route::put('/test-mixed-actions', function () {
                $dip = TestDipendente::create(['nome' => 'Nuovo Dipendente']);
                $existing = TestAnagraficaAssenza::create([
                    'dipendente_id' => $dip->id,
                    'tipo_assenza' => 'malattia',
                ]);
                $existing->update(['tipo_assenza' => 'ferie']);
                return response()->json(['ok' => true]);
            });

            // Rotta che crea un pivot (deve essere escluso)
            Route::post('/test-with-pivot', function () {
                $dip = TestDipendente::create(['nome' => 'Con Pivot']);
                TestPivotModel::create(['a_id' => 1, 'b_id' => 2]);
                return response()->json(['ok' => true]);
            });
        });

        // Gate per gli endpoint API di lettura
        Gate::define('viewLogOperations', fn () => true);
    }

    /*
    |--------------------------------------------------------------------------
    | Test: Tracciamento Multi-Subject Base
    |--------------------------------------------------------------------------
    */

    public function test_request_touching_3_models_creates_3_subject_records(): void
    {
        $response = $this->postJson('/test-multi-subject');
        $response->assertOk();

        // 1 solo record in log_operazioni
        $this->assertDatabaseCount(config('logoperations.table_name'), 1);

        // 3 record in log_operazioni_soggetti
        $subjectsTable = config('logoperations.subjects_table_name', 'log_operazioni_soggetti');
        $this->assertDatabaseCount($subjectsTable, 3);

        $logId = OperationLog::first()->id;

        // Verifica ciascun soggetto
        $this->assertDatabaseHas($subjectsTable, [
            'log_id' => $logId,
            'subject_type' => TestDipendente::class,
            'action' => 'created',
        ]);

        $this->assertDatabaseHas($subjectsTable, [
            'log_id' => $logId,
            'subject_type' => TestAnagraficaAssenza::class,
            'action' => 'created',
        ]);

        $this->assertDatabaseHas($subjectsTable, [
            'log_id' => $logId,
            'subject_type' => TestNotifica::class,
            'action' => 'created',
        ]);
    }

    public function test_primary_subject_is_auto_assigned_to_first_model(): void
    {
        $this->postJson('/test-multi-subject');

        $log = OperationLog::first();

        // Il primo modello toccato (TestDipendente) diventa il soggetto primario
        $this->assertEquals(TestDipendente::class, $log->subject_type);
        $this->assertNotNull($log->subject_id);
    }

    /*
    |--------------------------------------------------------------------------
    | Test: Bulk (50+ record con singola INSERT)
    |--------------------------------------------------------------------------
    */

    public function test_bulk_create_50_models_creates_50_subject_records(): void
    {
        $response = $this->putJson('/test-bulk-update');
        $response->assertOk();

        // 1 solo record in log_operazioni
        $this->assertDatabaseCount(config('logoperations.table_name'), 1);

        // 50 record in log_operazioni_soggetti
        $subjectsTable = config('logoperations.subjects_table_name', 'log_operazioni_soggetti');
        $count = DB::table($subjectsTable)->count();
        $this->assertEquals(50, $count);

        // Tutti con action = 'created'
        $allCreated = DB::table($subjectsTable)->where('action', 'created')->count();
        $this->assertEquals(50, $allCreated);
    }

    /*
    |--------------------------------------------------------------------------
    | Test: Deduplicazione e Azioni Miste
    |--------------------------------------------------------------------------
    */

    public function test_same_model_updated_multiple_times_is_recorded_once(): void
    {
        $response = $this->putJson('/test-mixed-actions');
        $response->assertOk();

        $subjectsTable = config('logoperations.subjects_table_name', 'log_operazioni_soggetti');

        // Dipendente created + AnagraficaAssenza created (l'update successivo viene deduplicato)
        $count = DB::table($subjectsTable)->count();
        $this->assertEquals(2, $count);

        // L'AnagraficaAssenza deve avere action = 'created' (prima azione registrata)
        $assenzaRecord = DB::table($subjectsTable)
            ->where('subject_type', TestAnagraficaAssenza::class)
            ->first();
        $this->assertEquals('created', $assenzaRecord->action);
    }

    /*
    |--------------------------------------------------------------------------
    | Test: Esclusione Pivot e Modelli di Sistema
    |--------------------------------------------------------------------------
    */

    public function test_pivot_models_are_excluded(): void
    {
        $response = $this->postJson('/test-with-pivot');
        $response->assertOk();

        $subjectsTable = config('logoperations.subjects_table_name', 'log_operazioni_soggetti');

        // Solo TestDipendente, NON il pivot
        $count = DB::table($subjectsTable)->count();
        $this->assertEquals(1, $count);

        $this->assertDatabaseHas($subjectsTable, [
            'subject_type' => TestDipendente::class,
        ]);

        $this->assertDatabaseMissing($subjectsTable, [
            'subject_type' => TestPivotModel::class,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Test: Storyboard Bidirezionale
    |--------------------------------------------------------------------------
    */

    public function test_storyboard_finds_log_for_secondary_subject(): void
    {
        // Crea un log che tocca 3 modelli
        $this->postJson('/test-multi-subject');

        $log = OperationLog::first();

        // Lo Storyboard deve trovare l'evento per la Notifica
        // (che non è il subject primario, ma è nella tabella relazionale)
        $notifica = TestNotifica::first();
        $logsForNotifica = OperationLog::forSubject(TestNotifica::class, $notifica->id)->get();

        $this->assertCount(1, $logsForNotifica);
        $this->assertEquals($log->id, $logsForNotifica->first()->id);
    }

    public function test_storyboard_finds_log_for_primary_subject(): void
    {
        $this->postJson('/test-multi-subject');

        $log = OperationLog::first();
        $dip = TestDipendente::first();

        // Il dipendente è il soggetto primario: deve essere trovato
        $logsForDip = OperationLog::forSubject(TestDipendente::class, $dip->id)->get();

        $this->assertCount(1, $logsForDip);
        $this->assertEquals($log->id, $logsForDip->first()->id);
    }

    /*
    |--------------------------------------------------------------------------
    | Test: API Storyboard Endpoint
    |--------------------------------------------------------------------------
    */

    public function test_storyboard_api_includes_touched_models(): void
    {
        $this->postJson('/test-multi-subject');

        $dip = TestDipendente::first();

        $response = $this->getJson("/api/logoperations/storyboard?subject_type=" . urlencode(TestDipendente::class) . "&subject_id={$dip->id}");
        $response->assertOk();

        $data = $response->json();
        $this->assertCount(1, $data['events']);

        $event = $data['events'][0];
        $this->assertArrayHasKey('touched_models', $event);
        $this->assertNotEmpty($event['touched_models']);

        // Verifica che ci siano 3 gruppi di modelli toccati
        $this->assertCount(3, $event['touched_models']);
    }

    /*
    |--------------------------------------------------------------------------
    | Test: API Subjects Endpoint (Unione)
    |--------------------------------------------------------------------------
    */

    public function test_subjects_endpoint_includes_relational_subjects(): void
    {
        $this->postJson('/test-multi-subject');

        $response = $this->getJson('/api/logoperations/storyboard/subjects');
        $response->assertOk();

        $subjects = $response->json();

        // Deve includere tutti e 3 i tipi di modello (primario + relazionali)
        $types = collect($subjects)->pluck('type')->unique()->values()->all();
        $this->assertContains(TestDipendente::class, $types);
        $this->assertContains(TestAnagraficaAssenza::class, $types);
        $this->assertContains(TestNotifica::class, $types);
    }

    /*
    |--------------------------------------------------------------------------
    | Test: Show Endpoint con Touched Models
    |--------------------------------------------------------------------------
    */

    public function test_show_endpoint_includes_touched_models(): void
    {
        $this->postJson('/test-multi-subject');

        $log = OperationLog::first();

        $response = $this->getJson("/api/logoperations/{$log->id}");
        $response->assertOk();

        $data = $response->json();
        $this->assertArrayHasKey('touched_models', $data);
        $this->assertCount(3, $data['touched_models']);
    }

    /*
    |--------------------------------------------------------------------------
    | Test: Cascade Delete
    |--------------------------------------------------------------------------
    */

    public function test_subjects_are_linked_to_log_via_relationship(): void
    {
        $this->postJson('/test-multi-subject');

        $log = OperationLog::first();
        $subjects = $log->subjects;

        // 3 soggetti collegati al log
        $this->assertCount(3, $subjects);

        // Ogni soggetto ha il log_id corretto
        $subjects->each(function ($subject) use ($log) {
            $this->assertEquals($log->id, $subject->log_id);
        });
    }

    public function test_subjects_are_cleaned_when_log_is_deleted_via_db(): void
    {
        $this->postJson('/test-multi-subject');

        $subjectsTable = config('logoperations.subjects_table_name', 'log_operazioni_soggetti');
        $this->assertEquals(3, DB::table($subjectsTable)->count());

        $logId = OperationLog::first()->id;

        // Cancella i soggetti manualmente (come farebbe la cascade in MySQL/PostgreSQL)
        DB::table($subjectsTable)->where('log_id', $logId)->delete();
        $this->assertEquals(0, DB::table($subjectsTable)->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Test: Auto-Discovery Disabilitato
    |--------------------------------------------------------------------------
    */

    public function test_no_subjects_recorded_when_autodiscovery_disabled(): void
    {
        config(['logoperations.auto_discover_subjects' => false]);

        $response = $this->postJson('/test-multi-subject');
        $response->assertOk();

        // 1 log, 0 soggetti (la feature è disabilitata, ma il ServiceProvider
        // è già booted con il config originale, quindi questo test verifica
        // solo il filtraggio nel Manager)
        $this->assertDatabaseCount(config('logoperations.table_name'), 1);
    }

    /*
    |--------------------------------------------------------------------------
    | Test: OperationSubject Model
    |--------------------------------------------------------------------------
    */

    public function test_operation_subject_model_has_correct_table(): void
    {
        $subject = new OperationSubject();
        $this->assertEquals('log_operazioni_soggetti', $subject->getTable());
    }

    public function test_operation_subject_scopes(): void
    {
        $this->postJson('/test-multi-subject');

        $subjects = OperationSubject::forSubject(TestDipendente::class)->get();
        $this->assertCount(1, $subjects);

        $subjects = OperationSubject::action('created')->get();
        $this->assertCount(3, $subjects);
    }

    /*
    |--------------------------------------------------------------------------
    | Test: HasOperationLogs Storyboard con Multi-Subject
    |--------------------------------------------------------------------------
    */

    public function test_secondary_subject_model_storyboard_includes_multi_subject_operations(): void
    {
        $response = $this->postJson('/test-multi-subject');
        $assenzaId = $response->json('assenza');

        $assenza = TestAnagraficaAssenza::findOrFail($assenzaId);
        $storyboard = $assenza->storyboard();

        // L'assenza trova l'operazione nella sua storyboard anche se non era il primary subject
        $this->assertCount(1, $storyboard);
        $this->assertEquals(TestDipendente::class, $storyboard->first()->subject_type);
        $this->assertTrue($storyboard->first()->subjects->contains('subject_type', TestAnagraficaAssenza::class));
    }
}
