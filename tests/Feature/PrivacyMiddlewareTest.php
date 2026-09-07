<?php

namespace SalvatoreCervone\LogOperations\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use SalvatoreCervone\LogOperations\Models\OperationLog;
use SalvatoreCervone\LogOperations\Tests\TestCase;

class PrivacyMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/test-privacy', function () {
            return response()->json(['status' => 'ok']);
        })->middleware('log.operations');
    }

    public function test_middleware_keeps_raw_ip_when_anonymization_disabled(): void
    {
        config(['logoperations.privacy.anonymize_ip' => false]);

        $this->post('/test-privacy', ['foo' => 'bar'], ['REMOTE_ADDR' => '192.168.1.150']);

        $log = OperationLog::first();
        $this->assertNotNull($log);
        $this->assertEquals('192.168.1.150', $log->client_ip);
    }

    public function test_middleware_anonymizes_ip_when_enabled(): void
    {
        config(['logoperations.privacy.anonymize_ip' => true]);
        config(['logoperations.privacy.anonymize_ip_mask' => 'xxx']);

        $this->post('/test-privacy', ['foo' => 'bar'], ['REMOTE_ADDR' => '192.168.1.150']);

        $log = OperationLog::first();
        $this->assertNotNull($log);
        $this->assertEquals('192.168.1.xxx', $log->client_ip);
    }

    public function test_middleware_captures_sanitized_headers_when_enabled(): void
    {
        config(['logoperations.privacy.log_headers' => true]);

        $this->withHeaders([
            'Authorization' => 'Bearer token-super-segreto-xyz',
            'X-Tracking-ID' => 'client-uuid-999',
            'Cookie' => 'session_id=12345',
        ])->post('/test-privacy', ['title' => 'Test Item']);

        $log = OperationLog::first();
        $this->assertNotNull($log);
        $this->assertArrayHasKey('headers', $log->parametri);

        $headers = $log->parametri['headers'];
        // L'header authorization e cookie devono essere mascherati
        $this->assertEquals(['***MASKED***'], $headers['authorization']);
        $this->assertEquals(['***MASKED***'], $headers['cookie']);
        // Header personalizzati o non sensibili devono essere registrati
        $this->assertEquals(['client-uuid-999'], $headers['x-tracking-id']);
    }
}
