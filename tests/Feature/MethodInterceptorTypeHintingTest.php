<?php

namespace SalvatoreCervone\LogOperations\Tests\Feature;

use Illuminate\Http\Request;
use SalvatoreCervone\LogOperations\Facades\LogOperations;
use SalvatoreCervone\LogOperations\Models\OperationRule;
use SalvatoreCervone\LogOperations\Services\MethodInterceptor;
use SalvatoreCervone\LogOperations\Services\RuleEngine;
use SalvatoreCervone\LogOperations\Tests\TestCase;

// Dummy service and consumer
class DummyBillingService
{
    public function generateInvoice(int $orderId, float $total): array
    {
        return [
            'invoice_number' => 'INV-' . $orderId,
            'total'          => $total,
            'status'         => 'issued',
        ];
    }
}

class DummyCheckoutController
{
    protected DummyBillingService $billingService;

    // Strict Type-Hinting in constructor
    public function __construct(DummyBillingService $billingService)
    {
        $this->billingService = $billingService;
    }

    public function checkout(int $orderId, float $amount): array
    {
        return $this->billingService->generateInvoice($orderId, $amount);
    }

    public function getBillingService(): DummyBillingService
    {
        return $this->billingService;
    }
}

class MethodInterceptorTypeHintingTest extends TestCase
{
    public function test_proxied_service_can_be_injected_via_type_hinting_without_type_error(): void
    {
        $interceptor = $this->app->make(MethodInterceptor::class);

        // Registra il proxy per DummyBillingService
        $interceptor->registerTraceableClass(DummyBillingService::class, ['generateInvoice']);

        // Risolvi il controller che type-hinta DummyBillingService nel costruttore
        $controller = $this->app->make(DummyCheckoutController::class);

        $this->assertInstanceOf(DummyCheckoutController::class, $controller);
        $this->assertInstanceOf(DummyBillingService::class, $controller->getBillingService());

        // Esegui il metodo del controller
        $result = $controller->checkout(42, 299.90);

        $this->assertEquals('INV-42', $result['invoice_number']);
        $this->assertEquals(299.90, $result['total']);

        // Verifica che lo step sia stato intercettato e registrato
        $steps = LogOperations::getCustomTraces()['steps'] ?? [];
        $this->assertNotEmpty($steps);

        $step = end($steps);
        $this->assertStringContainsString('DummyBillingService::generateInvoice', $step['label']);
        $this->assertEquals('success', $step['context']['status']);
        $this->assertEquals([42, 299.90], $step['context']['arguments']);
    }

    public function test_interception_via_database_rule(): void
    {
        // Crea una regola attiva nel database
        OperationRule::create([
            'name'         => 'Monitoraggio Billing',
            'type'         => 'method',
            'target'       => DummyBillingService::class . '@generateInvoice',
            'stack_level'  => 'core',
            'is_active'    => true,
        ]);

        // Invalida la cache delle regole
        $this->app->make(RuleEngine::class)->flushCache();

        $interceptor = $this->app->make(MethodInterceptor::class);
        $interceptor->registerActiveInterceptors();

        $controller = $this->app->make(DummyCheckoutController::class);

        $this->assertInstanceOf(DummyBillingService::class, $controller->getBillingService());

        $result = $controller->checkout(99, 149.50);
        $this->assertEquals('INV-99', $result['invoice_number']);

        $steps = LogOperations::getCustomTraces()['steps'] ?? [];
        $this->assertNotEmpty($steps);
    }
}
