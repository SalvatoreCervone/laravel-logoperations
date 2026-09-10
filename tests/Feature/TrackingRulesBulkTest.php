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
}
