<?php

namespace SalvatoreCervone\LogOperations\Tests\Feature;

use SalvatoreCervone\LogOperations\Attributes\Traceable;
use SalvatoreCervone\LogOperations\Facades\LogOperations;
use SalvatoreCervone\LogOperations\Services\MethodInterceptor;
use SalvatoreCervone\LogOperations\Tests\TestCase;

// Dummy service using #[Traceable] on method
class DummyNotificationService
{
    #[Traceable(label: 'Invio SMS Notifica')]
    public function sendSms(string $phone, string $text): bool
    {
        return true;
    }

    public function untrackedHelper(): string
    {
        return 'no-trace';
    }
}

// Dummy service using #[Traceable] on class
#[Traceable]
class DummyPaymentGateway
{
    public function authorizeCard(string $pan): bool
    {
        return true;
    }
}

class TraceableAttributeTest extends TestCase
{
    public function test_it_discovers_methods_with_traceable_attribute(): void
    {
        $interceptor = $this->app->make(MethodInterceptor::class);

        $methods = $interceptor->discoverTraceableMethods(DummyNotificationService::class);
        $this->assertEquals(['sendSms'], $methods);

        $classMethods = $interceptor->discoverTraceableMethods(DummyPaymentGateway::class);
        $this->assertEquals(['*'], $classMethods);
    }

    public function test_automatic_interception_of_method_with_traceable_attribute(): void
    {
        $interceptor = $this->app->make(MethodInterceptor::class);
        $interceptor->registerTraceableClass(DummyNotificationService::class);

        $service = $this->app->make(DummyNotificationService::class);

        $this->assertInstanceOf(DummyNotificationService::class, $service);

        // Chiamata al metodo decorato con #[Traceable]
        $sent = $service->sendSms('+393331234567', 'Codice OTP: 1234');
        $this->assertTrue($sent);

        $steps = LogOperations::getCustomTraces()['steps'] ?? [];
        $this->assertNotEmpty($steps);

        $lastStep = end($steps);
        $this->assertStringContainsString('DummyNotificationService::sendSms', $lastStep['label']);
        $this->assertEquals('success', $lastStep['context']['status']);

        // Chiamata al metodo non decorato
        LogOperations::flush();
        $res = $service->untrackedHelper();
        $this->assertEquals('no-trace', $res);

        $stepsAfter = LogOperations::getCustomTraces()['steps'] ?? [];
        $this->assertEmpty($stepsAfter);
    }

    public function test_automatic_interception_of_class_with_traceable_attribute(): void
    {
        $interceptor = $this->app->make(MethodInterceptor::class);
        $interceptor->registerTraceableClass(DummyPaymentGateway::class);

        $gateway = $this->app->make(DummyPaymentGateway::class);
        $this->assertInstanceOf(DummyPaymentGateway::class, $gateway);

        $res = $gateway->authorizeCard('4000123456789010');
        $this->assertTrue($res);

        $steps = LogOperations::getCustomTraces()['steps'] ?? [];
        $this->assertNotEmpty($steps);
        $this->assertStringContainsString('DummyPaymentGateway::authorizeCard', end($steps)['label']);
    }
}
