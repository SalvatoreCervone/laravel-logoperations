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
}
