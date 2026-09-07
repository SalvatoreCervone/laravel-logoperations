<?php

namespace SalvatoreCervone\LogOperations\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SalvatoreCervone\LogOperations\Models\OperationLog;
use SalvatoreCervone\LogOperations\Tests\TestCase;

class PaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_pagination_returns_expected_metadata_and_subset(): void
    {
        // Crea 65 log
        $now = now();
        for ($i = 1; $i <= 65; $i++) {
            OperationLog::create([
                'rotta' => '/test-route-' . $i,
                'verbo' => 'get',
                'codicehttp' => 200,
                'client_ip' => '127.0.0.1',
                'dataoperazione' => $now->copy()->subMinutes(65 - $i),
            ]);
        }

        $response = $this->getJson('/api/logoperations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'current_page',
                'data',
                'first_page_url',
                'from',
                'last_page',
                'last_page_url',
                'links',
                'next_page_url',
                'path',
                'per_page',
                'prev_page_url',
                'to',
                'total',
            ]);

        $data = $response->json();
        $this->assertEquals(1, $data['current_page']);
        $this->assertEquals(20, $data['per_page']);
        $this->assertEquals(4, $data['last_page']);
        $this->assertEquals(65, $data['total']);
        $this->assertEquals(1, $data['from']);
        $this->assertEquals(20, $data['to']);
        $this->assertCount(20, $data['data']);
    }

    public function test_navigation_to_page_two_and_last_page(): void
    {
        $now = now();
        for ($i = 1; $i <= 65; $i++) {
            OperationLog::create([
                'rotta' => '/test-route-' . $i,
                'verbo' => 'get',
                'codicehttp' => 200,
                'client_ip' => '127.0.0.1',
                'dataoperazione' => $now->copy()->subMinutes(65 - $i),
            ]);
        }

        // Pagina 2
        $resPage2 = $this->getJson('/api/logoperations?page=2');
        $dataPage2 = $resPage2->json();
        $this->assertEquals(2, $dataPage2['current_page']);
        $this->assertEquals(21, $dataPage2['from']);
        $this->assertEquals(40, $dataPage2['to']);
        $this->assertCount(20, $dataPage2['data']);

        // Ultima pagina (4)
        $resPage4 = $this->getJson('/api/logoperations?page=4');
        $dataPage4 = $resPage4->json();
        $this->assertEquals(4, $dataPage4['current_page']);
        $this->assertEquals(61, $dataPage4['from']);
        $this->assertEquals(65, $dataPage4['to']);
        $this->assertCount(5, $dataPage4['data']);
    }

    public function test_custom_per_page_options(): void
    {
        $now = now();
        for ($i = 1; $i <= 65; $i++) {
            OperationLog::create([
                'rotta' => '/test-route-' . $i,
                'verbo' => 'get',
                'codicehttp' => 200,
                'client_ip' => '127.0.0.1',
                'dataoperazione' => $now->copy()->subMinutes(65 - $i),
            ]);
        }

        $resPerPage50 = $this->getJson('/api/logoperations?per_page=50');
        $data = $resPerPage50->json();

        $this->assertEquals(50, $data['per_page']);
        $this->assertEquals(2, $data['last_page']);
        $this->assertEquals(65, $data['total']);
        $this->assertCount(50, $data['data']);
    }

    public function test_deterministic_ordering_on_pagination(): void
    {
        $sameTimestamp = now();
        // Crea 10 log con lo stesso identico timestamp per verificare ordinamento secondario per id DESC
        for ($i = 1; $i <= 10; $i++) {
            OperationLog::create([
                'rotta' => '/route-same-time-' . $i,
                'verbo' => 'get',
                'codicehttp' => 200,
                'client_ip' => '127.0.0.1',
                'dataoperazione' => $sameTimestamp,
            ]);
        }

        $response = $this->getJson('/api/logoperations?per_page=5');
        $data = $response->json();

        $ids = array_column($data['data'], 'id');
        // Gli ID devono essere rigorosamente decrescenti
        $sortedIds = $ids;
        rsort($sortedIds);
        $this->assertEquals($sortedIds, $ids);
    }
}
