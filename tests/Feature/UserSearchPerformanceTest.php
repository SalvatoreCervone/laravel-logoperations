<?php

namespace SalvatoreCervone\LogOperations\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use SalvatoreCervone\LogOperations\Models\OperationLog;
use SalvatoreCervone\LogOperations\Services\RuleEngine;
use SalvatoreCervone\LogOperations\Tests\TestCase;

class UserSearchPerformanceTest extends TestCase
{
    public function test_get_distinct_user_types_uses_cache(): void
    {
        OperationLog::create([
            'verbo' => 'get',
            'codicehttp' => 200,
            'rotta' => '/test',
            'user_type' => 'App\\Models\\User',
            'user_id' => '1',
            'dataoperazione' => now(),
        ]);

        $controller = new class extends \SalvatoreCervone\LogOperations\Http\Controllers\LogOperationsController {
            public function __construct()
            {
                // bypass middleware for test
            }

            public function testGetDistinctUserTypes(): array
            {
                return $this->getDistinctUserTypes();
            }
        };

        Cache::forget('logoperations_distinct_user_types');

        $typesFirst = $controller->testGetDistinctUserTypes();
        $this->assertEquals(['App\\Models\\User'], $typesFirst);
        $this->assertTrue(Cache::has('logoperations_distinct_user_types'));

        // Se aggiungiamo un nuovo tipo nel DB, finché la cache è valida deve ritornare il valore in cache
        OperationLog::create([
            'verbo' => 'get',
            'codicehttp' => 200,
            'rotta' => '/test-admin',
            'user_type' => 'App\\Models\\Admin',
            'user_id' => '2',
            'dataoperazione' => now(),
        ]);

        $typesCached = $controller->testGetDistinctUserTypes();
        $this->assertEquals(['App\\Models\\User'], $typesCached);

        // Quando flushCache viene chiamato, la cache si svuota e rileva il nuovo tipo
        app(RuleEngine::class)->flushCache();
        $this->assertFalse(Cache::has('logoperations_distinct_user_types'));

        $typesRefreshed = $controller->testGetDistinctUserTypes();
        $this->assertContains('App\\Models\\User', $typesRefreshed);
        $this->assertContains('App\\Models\\Admin', $typesRefreshed);
    }

    public function test_get_distinct_user_types_respects_user_models_config(): void
    {
        config(['logoperations.user_models' => ['App\\Models\\SuperAdmin']]);

        $controller = new class extends \SalvatoreCervone\LogOperations\Http\Controllers\LogOperationsController {
            public function __construct()
            {
            }

            public function testGetDistinctUserTypes(): array
            {
                return $this->getDistinctUserTypes();
            }
        };

        $types = $controller->testGetDistinctUserTypes();
        $this->assertEquals(['App\\Models\\SuperAdmin'], $types);
    }
}
