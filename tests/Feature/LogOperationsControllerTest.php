<?php

namespace SalvatoreCervone\LogOperations\Tests\Feature;

use SalvatoreCervone\LogOperations\Tests\TestCase;
use SalvatoreCervone\LogOperations\Models\OperationLog;

class LogOperationsControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Popola con dati di test
        OperationLog::create([
            'rotta' => '/api/test/1',
            'verbo' => 'get',
            'codicehttp' => 200,
            'client_ip' => '127.0.0.1',
            'dataoperazione' => now(),
            'nomeapplicazione' => 'laravel-test',
            'duration_ms' => 45,
        ]);

        OperationLog::create([
            'rotta' => '/api/test/2',
            'verbo' => 'post',
            'codicehttp' => 500,
            'client_ip' => '127.0.0.1',
            'dataoperazione' => now(),
            'error' => 'Database exception',
            'nomeapplicazione' => 'laravel-test',
            'duration_ms' => 120,
            'transaction_status' => 'rolled_back',
        ]);
    }

    public function test_it_returns_paginated_logs(): void
    {
        $response = $this->getJson('/api/logoperations');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'current_page',
            'total',
            'per_page',
        ]);
        $this->assertEquals(2, $response->json('total'));
    }

    public function test_it_filters_logs_by_verb(): void
    {
        $response = $this->getJson('/api/logoperations?verb=POST');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('total'));
        $this->assertEquals('post', $response->json('data.0.verbo'));
    }

    public function test_it_filters_logs_by_has_error(): void
    {
        $response = $this->getJson('/api/logoperations?has_error=1');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('total'));
        $this->assertEquals(500, $response->json('data.0.codicehttp'));
    }

    public function test_it_returns_single_log_detail(): void
    {
        $log = OperationLog::where('verbo', 'post')->first();

        $response = $this->getJson('/api/logoperations/' . $log->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $log->id);
        $response->assertJsonPath('data.verbo', 'post');
        $response->assertJsonPath('data.codicehttp', 500);
        $response->assertJsonPath('data.transaction_status', 'rolled_back');
    }

    public function test_it_returns_stats(): void
    {
        $response = $this->getJson('/api/logoperations/stats');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'total_requests',
            'total_errors',
            'error_rate',
            'avg_duration_ms',
            'pending_transactions',
        ]);
        $this->assertEquals(2, $response->json('total_requests'));
        $this->assertEquals(1, $response->json('total_errors'));
    }

    public function test_it_returns_verbs_and_http_codes(): void
    {
        $verbsResponse = $this->getJson('/api/logoperations/verbs');
        $verbsResponse->assertStatus(200);
        $this->assertContains('get', $verbsResponse->json());
        $this->assertContains('post', $verbsResponse->json());

        $codesResponse = $this->getJson('/api/logoperations/http-codes');
        $codesResponse->assertStatus(200);
        $this->assertContains(200, $codesResponse->json());
        $this->assertContains(500, $codesResponse->json());
    }
}
