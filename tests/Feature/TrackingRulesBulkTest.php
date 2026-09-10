<?php

namespace SalvatoreCervone\LogOperations\Tests\Feature;

use SalvatoreCervone\LogOperations\Tests\TestCase;
use SalvatoreCervone\LogOperations\Models\OperationRule;

class TrackingRulesBulkTest extends TestCase
{
    public function test_bulk_save_rules_activates_multiple_routes(): void
    {
        $payload = [
            'type'      => 'route',
            'targets'   => ['api/products', 'api/orders', 'api/customers'],
            'is_active' => true,
        ];

        $response = $this->postJson('/api/logoperations/studio/rules/bulk', $payload);
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'count'   => 3,
        ]);

        $this->assertDatabaseHas('log_operazioni_regole', [
            'type'      => 'route',
            'target'    => 'api/products',
            'is_active' => 1,
        ]);
        $this->assertDatabaseHas('log_operazioni_regole', [
            'type'      => 'route',
            'target'    => 'api/orders',
            'is_active' => 1,
        ]);
        $this->assertDatabaseHas('log_operazioni_regole', [
            'type'      => 'route',
            'target'    => 'api/customers',
            'is_active' => 1,
        ]);
    }

    public function test_bulk_save_rules_updates_stack_level_for_multiple_routes(): void
    {
        // Crea le regole prima
        OperationRule::create([
            'type'        => 'route',
            'target'      => 'api/products',
            'stack_level' => 'base',
            'is_active'   => true,
        ]);
        OperationRule::create([
            'type'        => 'route',
            'target'      => 'api/orders',
            'stack_level' => 'base',
            'is_active'   => true,
        ]);

        $payload = [
            'type'        => 'route',
            'targets'     => ['api/products', 'api/orders'],
            'stack_level' => 'core',
        ];

        $response = $this->postJson('/api/logoperations/studio/rules/bulk', $payload);
        $response->assertStatus(200);

        $this->assertDatabaseHas('log_operazioni_regole', [
            'target'      => 'api/products',
            'stack_level' => 'core',
        ]);
        $this->assertDatabaseHas('log_operazioni_regole', [
            'target'      => 'api/orders',
            'stack_level' => 'core',
        ]);
    }

    public function test_bulk_save_rules_deactivates_multiple_routes(): void
    {
        OperationRule::create([
            'type'      => 'route',
            'target'    => 'api/products',
            'is_active' => true,
        ]);
        OperationRule::create([
            'type'      => 'route',
            'target'    => 'api/orders',
            'is_active' => true,
        ]);

        $payload = [
            'type'      => 'route',
            'targets'   => ['api/products', 'api/orders'],
            'is_active' => false,
        ];

        $response = $this->postJson('/api/logoperations/studio/rules/bulk', $payload);
        $response->assertStatus(200);

        $this->assertDatabaseHas('log_operazioni_regole', [
            'target'    => 'api/products',
            'is_active' => 0,
        ]);
        $this->assertDatabaseHas('log_operazioni_regole', [
            'target'    => 'api/orders',
            'is_active' => 0,
        ]);
    }

    public function test_bulk_save_rules_validation(): void
    {
        $response = $this->postJson('/api/logoperations/studio/rules/bulk', [
            'type'    => 'route',
            'targets' => [],
        ]);
        $response->assertStatus(422);
    }

    public function test_post_login_tracking_does_not_enable_get_login_tracking(): void
    {
        \Illuminate\Support\Facades\Route::get('/login', fn() => 'login form');
        \Illuminate\Support\Facades\Route::post('/login', fn() => 'login post');

        $response = $this->postJson('/api/logoperations/studio/rules', [
            'type'         => 'route',
            'target'       => 'login',
            'http_methods' => ['POST'],
            'is_active'    => true,
            'stack_level'  => 'base',
        ]);
        $response->assertStatus(200);

        $scanner = app(\SalvatoreCervone\LogOperations\Services\AppScanner::class);
        $routes = collect($scanner->getRoutes())->where('clean_uri', 'login');

        $postRoute = $routes->first(fn($r) => in_array('POST', $r['methods']));
        $getRoute = $routes->first(fn($r) => in_array('GET', $r['methods']));

        $this->assertNotNull($postRoute);
        $this->assertNotNull($getRoute);
        $this->assertTrue($postRoute['is_tracked']);
        $this->assertFalse($getRoute['is_tracked']);
    }

    public function test_saving_route_rule_with_methods_stores_and_updates_independently(): void
    {
        $resPost = $this->postJson('/api/logoperations/studio/rules', [
            'type'         => 'route',
            'target'       => 'login',
            'http_methods' => ['POST'],
            'is_active'    => true,
            'stack_level'  => 'base',
        ]);
        $resPost->assertStatus(200);

        $resGet = $this->postJson('/api/logoperations/studio/rules', [
            'type'         => 'route',
            'target'       => 'login',
            'http_methods' => ['GET'],
            'is_active'    => false,
            'stack_level'  => 'full',
        ]);
        $resGet->assertStatus(200);

        $rules = OperationRule::where('target', 'login')->get();
        $this->assertCount(2, $rules);

        $postRule = $rules->first(fn($r) => $r->http_methods === ['POST']);
        $getRule = $rules->first(fn($r) => $r->http_methods === ['GET']);

        $this->assertTrue($postRule->is_active);
        $this->assertEquals('base', $postRule->stack_level);

        $this->assertFalse($getRule->is_active);
        $this->assertEquals('full', $getRule->stack_level);
    }

    public function test_bulk_save_rules_with_routes_structure(): void
    {
        $payload = [
            'type'   => 'route',
            'routes' => [
                ['target' => 'login', 'methods' => ['POST']],
                ['target' => 'login', 'methods' => ['GET']],
            ],
            'is_active' => true,
        ];

        $response = $this->postJson('/api/logoperations/studio/rules/bulk', $payload);
        $response->assertStatus(200);

        $this->assertCount(2, OperationRule::where('target', 'login')->get());
    }
}
