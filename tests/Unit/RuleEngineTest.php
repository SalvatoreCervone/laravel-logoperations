<?php

namespace SalvatoreCervone\LogOperations\Tests\Unit;

use Illuminate\Http\Request;
use SalvatoreCervone\LogOperations\Tests\TestCase;
use SalvatoreCervone\LogOperations\Services\RuleEngine;
use SalvatoreCervone\LogOperations\Models\OperationRule;

class RuleEngineTest extends TestCase
{
    protected RuleEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new RuleEngine();
    }

    public function test_it_caches_and_flushes_active_rules(): void
    {
        OperationRule::create([
            'type' => 'route',
            'target' => '/api/test-rule',
            'http_methods' => ['GET'],
            'stack_level' => 'core',
            'is_active' => true,
        ]);

        $this->engine->flushCache();
        $rules = $this->engine->getActiveRules();

        $this->assertNotEmpty($rules['routes']);
        $this->assertEquals('/api/test-rule', $rules['routes'][0]['target']);

        // Invalida la cache
        $this->engine->flushCache();
        OperationRule::query()->delete();
        $this->engine->flushCache();

        $emptyRules = $this->engine->getActiveRules();
        $this->assertEmpty($emptyRules['routes']);
    }

    public function test_it_evaluates_active_route_rule(): void
    {
        OperationRule::create([
            'type' => 'route',
            'target' => 'api/orders*',
            'http_methods' => ['POST'],
            'stack_level' => 'full',
            'is_active' => true,
        ]);
        $this->engine->flushCache();

        // Richiesta corrispondente
        $matchingRequest = Request::create('/api/orders/123', 'POST');
        $evaluation = $this->engine->evaluateRequest($matchingRequest);

        $this->assertTrue($evaluation['should_log']);
        $this->assertEquals('full', $evaluation['stack_level']);
        $this->assertEquals('dynamic_route', $evaluation['matched_by']);

        // Richiesta non corrispondente per metodo HTTP (GET invece di POST)
        $nonMatchingRequest = Request::create('/api/orders/123', 'GET');
        $nonMatchingEvaluation = $this->engine->evaluateRequest($nonMatchingRequest);

        $this->assertFalse($nonMatchingEvaluation['should_log']);
    }

    public function test_it_ignores_expired_rules(): void
    {
        OperationRule::create([
            'type' => 'user_session',
            'target' => '42',
            'is_active' => true,
            'expires_at' => now()->subMinutes(10), // Già scaduta
        ]);
        $this->engine->flushCache();

        $rules = $this->engine->getActiveRules();
        $this->assertEmpty($rules['user_sessions']);
    }

    public function test_it_evaluates_route_with_parameters_and_querystring(): void
    {
        OperationRule::create([
            'type' => 'route',
            'target' => 'api/users/{id}',
            'http_methods' => ['GET'],
            'stack_level' => 'core',
            'is_active' => true,
        ]);
        $this->engine->flushCache();

        // GET con parametro numerico e querystring
        $req1 = Request::create('/api/users/42?foo=bar&page=1', 'GET');
        $eval1 = $this->engine->evaluateRequest($req1);
        $this->assertTrue($eval1['should_log']);
        $this->assertEquals('dynamic_route', $eval1['matched_by']);

        // GET con parametro stringa UUID
        $req2 = Request::create('/api/users/abc-123', 'GET');
        $eval2 = $this->engine->evaluateRequest($req2);
        $this->assertTrue($eval2['should_log']);

        // Non corrispondente (sub-risorsa non coperta dal pattern)
        $req3 = Request::create('/api/users/42/details', 'GET');
        $eval3 = $this->engine->evaluateRequest($req3);
        $this->assertFalse($eval3['should_log']);
    }
}
