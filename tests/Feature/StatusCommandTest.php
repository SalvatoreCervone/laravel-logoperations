<?php

namespace SalvatoreCervone\LogOperations\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SalvatoreCervone\LogOperations\Models\OperationLog;
use SalvatoreCervone\LogOperations\Models\OperationRule;
use SalvatoreCervone\LogOperations\Tests\TestCase;

class StatusCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_command_executes_successfully_and_displays_diagnostics(): void
    {
        OperationLog::create([
            'rotta' => '/api/test',
            'verbo' => 'get',
            'codicehttp' => 200,
            'client_ip' => '127.0.0.1',
            'dataoperazione' => now(),
        ]);

        OperationLog::create([
            'rotta' => '/api/orders/5',
            'verbo' => 'get',
            'codicehttp' => 500,
            'subject_type' => 'App\Models\Order',
            'subject_id' => '5',
            'client_ip' => '127.0.0.1',
            'dataoperazione' => now(),
        ]);

        OperationRule::create([
            'type' => 'route',
            'target' => 'api/test',
            'is_active' => true,
        ]);

        $this->artisan('logoperations:status')
            ->expectsOutputToContain('LogOperations — Stato & Diagnostica')
            ->expectsOutputToContain('STATO GENERALE')
            ->expectsOutputToContain('CENTRO DI CONTROLLO ZERO-CODE (TRACKING STUDIO)')
            ->expectsOutputToContain('SISTEMA DI ALERTING & NOTIFICHE')
            ->expectsOutputToContain('RETENTION POLICY & SALVAGUARDIA AUDIT TRAIL')
            ->assertExitCode(0);
    }
}
