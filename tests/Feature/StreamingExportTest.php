<?php

namespace SalvatoreCervone\LogOperations\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SalvatoreCervone\LogOperations\Models\OperationLog;
use SalvatoreCervone\LogOperations\Tests\TestCase;

class StreamingExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_streaming_export_downloads_utf8_bom_and_correct_headers_and_data(): void
    {
        $now = now();
        OperationLog::create([
            'rotta' => '/api/orders',
            'verbo' => 'POST',
            'codicehttp' => 201,
            'client_ip' => '192.168.1.50',
            'duration_ms' => 125,
            'dataoperazione' => $now,
            'user_id' => 10,
            'nomeapplicazione' => 'ecommerce_app',
        ]);

        $response = $this->get('/api/logoperations/export?format=csv');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename=logoperations_export_', $response->headers->get('Content-Disposition'));

        $content = $response->streamedContent();

        // Verifica UTF-8 BOM
        $this->assertTrue(str_starts_with($content, "\xEF\xBB\xBF"), 'Il CSV deve iniziare con UTF-8 BOM per Excel');

        // Verifica intestazione
        $this->assertStringContainsString('ID,"Data e Ora",Metodo,"URI / Rotta","Status Code"', $content);

        // Verifica record
        $this->assertStringContainsString('/api/orders', $content);
        $this->assertStringContainsString('POST', $content);
        $this->assertStringContainsString('201', $content);
        $this->assertStringContainsString('192.168.1.50', $content);
        $this->assertStringContainsString('ecommerce_app', $content);
    }

    public function test_json_streaming_export_downloads_valid_json_array(): void
    {
        $now = now();
        for ($i = 1; $i <= 3; $i++) {
            OperationLog::create([
                'rotta' => '/api/resource/' . $i,
                'verbo' => 'GET',
                'codicehttp' => 200,
                'client_ip' => '127.0.0.1',
                'dataoperazione' => $now->copy()->subMinutes($i),
            ]);
        }

        $response = $this->get('/api/logoperations/export?format=json');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
        $this->assertStringContainsString('attachment; filename=logoperations_export_', $response->headers->get('Content-Disposition'));

        $content = $response->streamedContent();

        $decoded = json_decode($content, true);
        $this->assertIsArray($decoded);
        $this->assertCount(3, $decoded);
        $this->assertEquals('/api/resource/1', $decoded[0]['rotta']);
    }

    public function test_streaming_export_respects_active_filters(): void
    {
        $now = now();

        OperationLog::create([
            'rotta' => '/api/success',
            'verbo' => 'get',
            'codicehttp' => 200,
            'client_ip' => '10.0.0.1',
            'dataoperazione' => $now,
        ]);

        OperationLog::create([
            'rotta' => '/api/fail',
            'verbo' => 'post',
            'codicehttp' => 500,
            'client_ip' => '10.0.0.2',
            'error' => 'Database connection timeout',
            'dataoperazione' => $now,
        ]);

        // Filtro per verbo POST
        $csvResponse = $this->get('/api/logoperations/export?format=csv&verb=POST');
        $csvContent = $csvResponse->streamedContent();
        $this->assertStringContainsString('/api/fail', $csvContent);
        $this->assertStringNotContainsString('/api/success', $csvContent);

        // Filtro per solo errori
        $jsonResponse = $this->get('/api/logoperations/export?format=json&has_error=1');
        $jsonContent = $jsonResponse->streamedContent();
        $decoded = json_decode($jsonContent, true);
        $this->assertCount(1, $decoded);
        $this->assertEquals('/api/fail', $decoded[0]['rotta']);
    }
}
