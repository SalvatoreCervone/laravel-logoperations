<?php

namespace SalvatoreCervone\LogOperations\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use SalvatoreCervone\LogOperations\Facades\LogOperations;
use SalvatoreCervone\LogOperations\Models\OperationLog;
use SalvatoreCervone\LogOperations\Tests\TestCase;

class ContextAndTaggingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/test-context-tagging', function () {
            LogOperations::withContext([
                'order_id' => 10099,
                'customer_segment' => 'vip',
                'password' => 'secret_internal_pass',
            ]);
            LogOperations::addContext('payment_gateway', 'stripe');
            LogOperations::tag('checkout', 'ecommerce');
            LogOperations::tag(['priority', 'checkout']); // test deduplication

            return response()->json(['status' => 'ok']);
        })->middleware('log.operations');
    }

    public function test_manager_context_and_tagging_methods_and_flush(): void
    {
        $this->assertFalse(LogOperations::hasContext());
        $this->assertFalse(LogOperations::hasTags());
        $this->assertEmpty(LogOperations::getContext());
        $this->assertEmpty(LogOperations::getTags());

        LogOperations::withContext(['env' => 'production', 'feature' => 'dark_mode']);
        LogOperations::addContext('user_level', 5);
        $this->assertTrue(LogOperations::hasContext());
        $this->assertEquals([
            'env' => 'production',
            'feature' => 'dark_mode',
            'user_level' => 5,
        ], LogOperations::getContext());

        LogOperations::tag('alpha', 'beta');
        LogOperations::tag(['beta', 'gamma']);
        $this->assertTrue(LogOperations::hasTags());
        $this->assertEquals(['alpha', 'beta', 'gamma'], LogOperations::getTags());

        LogOperations::flush();
        $this->assertFalse(LogOperations::hasContext());
        $this->assertFalse(LogOperations::hasTags());
        $this->assertEmpty(LogOperations::getContext());
        $this->assertEmpty(LogOperations::getTags());
    }

    public function test_middleware_captures_context_and_tags_with_masking(): void
    {
        $this->post('/test-context-tagging', ['item' => 'laptop']);

        $log = OperationLog::first();
        $this->assertNotNull($log);

        // Verifica array parametri
        $this->assertArrayHasKey('context', $log->parametri);
        $this->assertArrayHasKey('tags', $log->parametri);

        $context = $log->parametri['context'];
        $this->assertEquals(10099, $context['order_id']);
        $this->assertEquals('vip', $context['customer_segment']);
        $this->assertEquals('stripe', $context['payment_gateway']);
        // Il campo sensibile password deve essere mascherato
        $this->assertEquals('***MASKED***', $context['password']);

        // Verifica tags
        $tags = $log->parametri['tags'];
        $this->assertEquals(['checkout', 'ecommerce', 'priority'], $tags);

        // Verifica custom_traces
        $this->assertNotNull($log->custom_traces);
        $this->assertEquals(['checkout', 'ecommerce', 'priority'], $log->custom_traces['tags']);
        $this->assertEquals(10099, $log->custom_traces['context']['order_id']);
        $this->assertEquals('***MASKED***', $log->custom_traces['context']['password']);
    }

    public function test_eloquent_tag_and_context_query_scopes(): void
    {
        $this->post('/test-context-tagging', ['item' => 'phone']);

        // Test scopeTag
        $checkoutLogs = OperationLog::tag('checkout')->get();
        $this->assertCount(1, $checkoutLogs);

        $nonExistentTagLogs = OperationLog::tag('non_existent_tag')->get();
        $this->assertCount(0, $nonExistentTagLogs);

        // Test scopeContext
        $orderLogs = OperationLog::context('order_id', 10099)->get();
        $this->assertCount(1, $orderLogs);

        $hasOrderContextLogs = OperationLog::context('order_id')->get();
        $this->assertCount(1, $hasOrderContextLogs);

        $wrongOrderLogs = OperationLog::context('order_id', 99999)->get();
        $this->assertCount(0, $wrongOrderLogs);
    }
}
