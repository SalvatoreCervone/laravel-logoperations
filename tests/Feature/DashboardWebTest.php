<?php

namespace SalvatoreCervone\LogOperations\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SalvatoreCervone\LogOperations\Tests\TestCase;

class DashboardWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_web_route_renders_successfully(): void
    {
        $response = $this->get('/logoperations');

        $response->assertStatus(200)
            ->assertSee('LOGOPERATIONS')
            ->assertSee('Dashboard Operazioni')
            ->assertSee('Richieste Totali');
    }

    public function test_dashboard_returns_404_when_disabled(): void
    {
        config(['logoperations.dashboard.enabled' => false]);

        $response = $this->get('/logoperations');

        $response->assertStatus(404);
    }
}
