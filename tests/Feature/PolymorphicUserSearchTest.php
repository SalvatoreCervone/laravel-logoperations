<?php

namespace SalvatoreCervone\LogOperations\Tests\Feature;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use SalvatoreCervone\LogOperations\Models\OperationLog;
use SalvatoreCervone\LogOperations\Models\OperationRule;
use SalvatoreCervone\LogOperations\Services\RuleEngine;
use SalvatoreCervone\LogOperations\Tests\TestCase;

class TestUserCustomer extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'test_customers';
    protected $guarded = [];
}

class TestUserStaff extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'test_staff';
    protected $guarded = [];
}

class PolymorphicUserSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('test_customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });

        Schema::create('test_staff', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });

        Relation::morphMap([
            'customer' => TestUserCustomer::class,
            'staff' => TestUserStaff::class,
        ]);
    }

    protected function tearDown(): void
    {
        Relation::morphMap([], false);
        Schema::dropIfExists('test_customers');
        Schema::dropIfExists('test_staff');
        parent::tearDown();
    }

    public function test_polymorphic_user_search_resolves_morph_map_and_prevents_cross_contamination(): void
    {
        $customer = TestUserCustomer::create(['id' => 1, 'name' => 'Mario Rossi', 'email' => 'mario@example.com']);
        $staff = TestUserStaff::create(['id' => 1, 'name' => 'Luigi Verdi', 'email' => 'luigi@example.com']);

        $customerLog = OperationLog::create([
            'user_type' => 'customer',
            'user_id' => $customer->id,
            'rotta' => '/orders/create',
            'verbo' => 'post',
            'codicehttp' => 201,
            'dataoperazione' => now(),
            'nomeapplicazione' => 'laravel-app',
        ]);

        $staffLog = OperationLog::create([
            'user_type' => 'staff',
            'user_id' => $staff->id,
            'rotta' => '/admin/settings',
            'verbo' => 'get',
            'codicehttp' => 200,
            'dataoperazione' => now(),
            'nomeapplicazione' => 'laravel-app',
        ]);

        // Cerca Mario: deve restituire ESCLUSIVAMENTE il log di Mario (customer #1), NON quello di Luigi (staff #1)
        $responseMario = $this->getJson('/api/logoperations?user=Mario');
        $responseMario->assertStatus(200);
        $this->assertEquals(1, $responseMario->json('total'));
        $this->assertEquals($customerLog->id, $responseMario->json('data.0.id'));

        // Cerca Luigi: deve restituire ESCLUSIVAMENTE il log di Luigi (staff #1)
        $responseLuigi = $this->getJson('/api/logoperations?user=Luigi');
        $responseLuigi->assertStatus(200);
        $this->assertEquals(1, $responseLuigi->json('total'));
        $this->assertEquals($staffLog->id, $responseLuigi->json('data.0.id'));
    }

    public function test_index_eager_loads_polymorphic_users_preventing_n_plus_one(): void
    {
        // Crea 5 clienti e relativi log
        for ($i = 1; $i <= 5; $i++) {
            $c = TestUserCustomer::create([
                'id' => $i,
                'name' => "Cliente $i",
                'email' => "cliente$i@example.com",
            ]);
            OperationLog::create([
                'user_type' => 'customer',
                'user_id' => $c->id,
                'rotta' => "/test-eager/$i",
                'verbo' => 'get',
                'codicehttp' => 200,
                'dataoperazione' => now(),
                'nomeapplicazione' => 'laravel-app',
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson('/api/logoperations?per_page=5');
        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        // Query attese: 1 COUNT(), 1 SELECT log_operazioni, 1 SELECT test_customers (eager loading per 'customer')
        // In totale al massimo 3-4 query, MAI 1 query per riga (evita N+1)
        $customerQueries = array_filter($queries, function ($q) {
            return str_contains($q['query'], 'test_customers');
        });

        $this->assertCount(1, $customerQueries, 'Eager loading polimorfico deve eseguire esattamente 1 query per tipo utente');
    }

    public function test_clear_expired_rules_command_flushes_rule_engine_cache(): void
    {
        $engine = app(RuleEngine::class);

        // Crea una regola scaduta
        OperationRule::create([
            'name' => 'Monitoraggio Temporaneo',
            'type' => 'user_session',
            'target' => '999',
            'is_active' => true,
            'expires_at' => now()->subMinutes(10),
        ]);

        // Preriscalda la cache
        Cache::put(RuleEngine::CACHE_KEY, ['dummy' => 'cache_val'], 3600);
        $this->assertTrue(Cache::has(RuleEngine::CACHE_KEY));

        // Esegui comando
        $this->artisan('logoperations:clear-expired-rules')
            ->assertSuccessful();

        // Verifica che la cache sia stata invalidata
        $this->assertFalse(Cache::has(RuleEngine::CACHE_KEY));
    }

    public function test_tracking_rules_search_users_with_non_numeric_query(): void
    {
        config(['auth.providers.users.model' => TestUserCustomer::class]);

        TestUserCustomer::create([
            'id' => 10,
            'name' => 'Salvatore',
            'email' => 'salvatore@example.com',
        ]);

        $response = $this->getJson('/api/logoperations/studio/users?q=Salvatore');
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals(10, $response->json('data.0.id'));
    }
}
