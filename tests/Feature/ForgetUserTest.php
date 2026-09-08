<?php

namespace SalvatoreCervone\LogOperations\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SalvatoreCervone\LogOperations\Facades\LogOperations;
use SalvatoreCervone\LogOperations\Models\OperationLog;
use SalvatoreCervone\LogOperations\Tests\TestCase;

class ForgetUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_forget_user_hard_deletes_records(): void
    {
        // Crea 3 log per l'utente 42 e 1 log per l'utente 99
        OperationLog::create([
            'user_id' => '42',
            'user_type' => 'App\Models\User',
            'rotta' => '/dashboard',
            'verbo' => 'get',
            'codicehttp' => 200,
            'client_ip' => '192.168.1.100',
            'dataoperazione' => now(),
        ]);
        OperationLog::create([
            'user_id' => '42',
            'user_type' => 'App\Models\User',
            'rotta' => '/profile',
            'verbo' => 'put',
            'codicehttp' => 200,
            'client_ip' => '192.168.1.100',
            'dataoperazione' => now(),
        ]);
        OperationLog::create([
            'user_id' => '99',
            'user_type' => 'App\Models\User',
            'rotta' => '/dashboard',
            'verbo' => 'get',
            'codicehttp' => 200,
            'client_ip' => '10.0.0.5',
            'dataoperazione' => now(),
        ]);

        $this->assertEquals(3, OperationLog::count());

        // Esegue il diritto all'oblio con cancellazione fisica
        $affected = LogOperations::forgetUser(42);

        $this->assertEquals(2, $affected);
        $this->assertEquals(1, OperationLog::count());
        $tableName = (new OperationLog())->getTable();
        $this->assertDatabaseHas($tableName, ['user_id' => '99']);
        $this->assertDatabaseMissing($tableName, ['user_id' => '42']);
    }

    public function test_forget_user_anonymize_scrubs_pii_and_preserves_technical_log(): void
    {
        OperationLog::create([
            'user_id' => '50',
            'user_type' => 'App\Models\User',
            'rotta' => '/api/checkout',
            'verbo' => 'post',
            'codicehttp' => 201,
            'client_ip' => '192.168.1.50',
            'dataoperazione' => now(),
            'duration_ms' => 125,
            'parametri' => [
                'post' => [
                    'email' => 'mario@example.com',
                    'name' => 'Mario Rossi',
                    'order_id' => 'ORD-999',
                    'amount' => 49.99,
                ],
            ],
        ]);

        $affected = LogOperations::forgetUser(50, anonymize: true);

        $this->assertEquals(1, $affected);

        // Il record esiste ancora per statistiche tecniche
        $this->assertEquals(1, OperationLog::count());
        $log = OperationLog::first();

        $this->assertNull($log->user_id);
        $this->assertNull($log->user_type);
        $this->assertEquals('192.168.1.0', $log->client_ip);
        $this->assertEquals(201, $log->codicehttp);
        $this->assertEquals(125, $log->duration_ms);

        // Verifica che email e name siano stati ripuliti mentre order_id e amount sono rimasti
        $this->assertEquals('[ANONYMIZED_GDPR]', $log->parametri['post']['email']);
        $this->assertEquals('[ANONYMIZED_GDPR]', $log->parametri['post']['name']);
        $this->assertEquals('ORD-999', $log->parametri['post']['order_id']);
        $this->assertEquals(49.99, $log->parametri['post']['amount']);
    }

    public function test_artisan_forget_user_command(): void
    {
        OperationLog::create([
            'user_id' => '77',
            'user_type' => 'App\Models\User',
            'rotta' => '/settings',
            'verbo' => 'get',
            'codicehttp' => 200,
            'client_ip' => '172.16.0.4',
            'dataoperazione' => now(),
        ]);

        // Testa esecuzione con --force
        $this->artisan('logoperations:forget-user 77 --force')
            ->expectsOutputToContain('Operazione completata: 1 record di log eliminati definitivamente')
            ->assertSuccessful();

        $this->assertEquals(0, OperationLog::where('user_id', '77')->count());
    }

    public function test_artisan_forget_user_command_with_anonymize(): void
    {
        OperationLog::create([
            'user_id' => '88',
            'user_type' => 'App\Models\User',
            'rotta' => '/orders',
            'verbo' => 'get',
            'codicehttp' => 200,
            'client_ip' => '10.0.0.12',
            'dataoperazione' => now(),
        ]);

        $this->artisan('logoperations:forget-user 88 --anonymize --force')
            ->expectsOutputToContain('anonimizzati con successo')
            ->assertSuccessful();

        $this->assertEquals(0, OperationLog::where('user_id', '88')->count());
        $this->assertEquals(1, OperationLog::count());
    }

    public function test_forget_user_anonymizes_multiple_records_chunked(): void
    {
        for ($i = 0; $i < 15; $i++) {
            OperationLog::create([
                'user_id' => '123',
                'user_type' => 'App\Models\User',
                'rotta' => '/chunk-test/' . $i,
                'verbo' => 'get',
                'codicehttp' => 200,
                'client_ip' => '192.168.1.1',
                'dataoperazione' => now(),
                'parametri' => ['email' => 'user123@example.com', 'action' => 'browse'],
            ]);
        }

        $affected = LogOperations::forgetUser(123, null, true);

        $this->assertEquals(15, $affected);
        $this->assertEquals(0, OperationLog::where('user_id', '123')->count());
        $this->assertEquals(15, OperationLog::whereNull('user_id')->count());

        $first = OperationLog::first();
        $this->assertEquals('192.168.1.0', $first->client_ip);
        $this->assertEquals('[ANONYMIZED_GDPR]', $first->parametri['email']);
        $this->assertEquals('browse', $first->parametri['action']);
    }
}
