<?php

namespace SalvatoreCervone\LogOperations\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use SalvatoreCervone\LogOperations\Tests\TestCase;

class SecurityAuthorizationTest extends TestCase
{
    public function test_api_is_accessible_in_testing_environment(): void
    {
        config(['logoperations.allow_in_local' => true]);

        $response = $this->getJson('/api/logoperations');
        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'current_page', 'total']);
    }

    public function test_api_is_blocked_when_gate_denies_access(): void
    {
        // Disabilitiamo il bypass locale per forzare la valutazione del Gate
        config(['logoperations.allow_in_local' => false]);
        config(['logoperations.gate' => 'viewLogOperations']);

        Gate::define('viewLogOperations', function ($user = null) {
            return false; // Accesso negato
        });

        $response = $this->getJson('/api/logoperations');
        $response->assertStatus(403);
    }

    public function test_api_is_accessible_when_gate_allows_access(): void
    {
        config(['logoperations.allow_in_local' => false]);
        config(['logoperations.gate' => 'viewLogOperations']);

        Gate::define('viewLogOperations', function ($user = null) {
            return true; // Accesso consentito
        });

        $response = $this->getJson('/api/logoperations');
        $response->assertStatus(200);
    }

    public function test_tracking_studio_routes_are_protected_by_gate(): void
    {
        config(['logoperations.allow_in_local' => false]);
        config(['logoperations.gate' => 'viewLogOperations']);

        Gate::define('viewLogOperations', function ($user = null) {
            return false;
        });

        $response = $this->getJson('/api/logoperations/studio/routes');
        $response->assertStatus(403);

        $saveResponse = $this->postJson('/api/logoperations/studio/rules', [
            'type' => 'route',
            'target' => '/test',
        ]);
        $saveResponse->assertStatus(403);
    }
}
