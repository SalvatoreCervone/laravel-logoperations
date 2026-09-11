<?php

namespace SalvatoreCervone\LogOperations\Tests\Unit;

use SalvatoreCervone\LogOperations\Contracts\LogOperationsProxy;
use SalvatoreCervone\LogOperations\Facades\LogOperations;
use SalvatoreCervone\LogOperations\Services\ProxyClassGenerator;
use SalvatoreCervone\LogOperations\Tests\TestCase;

// Dummy classes for unit testing
class DummyCalculatorService
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    public function calculateDiscount(float $amount, ?string $coupon = null): float|int
    {
        return $coupon === 'VIP' ? $amount * 0.8 : $amount;
    }

    public function resetTotals(int &$accumulator): void
    {
        $accumulator = 0;
    }

    public function failWithException(): string
    {
        throw new \InvalidArgumentException('Operazione fallita');
    }
}

final class FinalService
{
    public function ping(): string
    {
        return 'pong';
    }
}

class ProxyClassGeneratorTest extends TestCase
{
    protected ProxyClassGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new ProxyClassGenerator();
    }

    public function test_it_generates_proxy_that_preserves_instanceof(): void
    {
        $target = new DummyCalculatorService();
        $proxy = $this->generator->createProxy($target, DummyCalculatorService::class, ['add']);

        $this->assertInstanceOf(DummyCalculatorService::class, $proxy);
        $this->assertInstanceOf(LogOperationsProxy::class, $proxy);
        $this->assertSame($target, $proxy->__getLogOperationsTarget());
    }

    public function test_monitored_method_executes_and_logs_step(): void
    {
        $target = new DummyCalculatorService();
        $proxy = $this->generator->createProxy($target, DummyCalculatorService::class, ['add']);

        $result = $proxy->add(10, 25);

        $this->assertEquals(35, $result);

        $steps = LogOperations::getCustomTraces()['steps'] ?? [];
        $this->assertNotEmpty($steps);

        $lastStep = end($steps);
        $this->assertStringContainsString('DummyCalculatorService::add', $lastStep['label']);
        $this->assertEquals('success', $lastStep['context']['status']);
        $this->assertEquals([10, 25], $lastStep['context']['arguments']);
        $this->assertEquals(35, $lastStep['context']['result']);
    }

    public function test_unmonitored_method_executes_without_logging_step(): void
    {
        $target = new DummyCalculatorService();
        $proxy = $this->generator->createProxy($target, DummyCalculatorService::class, ['add']); // calculateDiscount non è monitorato

        LogOperations::flush();

        $result = $proxy->calculateDiscount(100.0, 'VIP');

        $this->assertEquals(80.0, $result);

        $steps = LogOperations::getCustomTraces()['steps'] ?? [];
        $this->assertEmpty($steps);
    }

    public function test_method_with_void_return_type_and_reference_parameter(): void
    {
        $target = new DummyCalculatorService();
        $proxy = $this->generator->createProxy($target, DummyCalculatorService::class, ['resetTotals']);

        $total = 150;
        $proxy->resetTotals($total);

        $this->assertEquals(0, $total, 'Il parametro per riferimento deve essere mutato');

        $steps = LogOperations::getCustomTraces()['steps'] ?? [];
        $this->assertNotEmpty($steps);
    }

    public function test_method_throwing_exception_is_logged_and_rethrown(): void
    {
        $target = new DummyCalculatorService();
        $proxy = $this->generator->createProxy($target, DummyCalculatorService::class, ['failWithException']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Operazione fallita');

        try {
            $proxy->failWithException();
        } finally {
            $steps = LogOperations::getCustomTraces()['steps'] ?? [];
            $this->assertNotEmpty($steps);
            $lastStep = end($steps);
            $this->assertStringContainsString('DummyCalculatorService::failWithException [ERRORE]', $lastStep['label']);
            $this->assertEquals('error', $lastStep['context']['status']);
            $this->assertEquals(\InvalidArgumentException::class, $lastStep['context']['exception']);
        }
    }

    public function test_final_class_cannot_be_subclassed_and_returns_original_target(): void
    {
        $target = new FinalService();
        $proxy = $this->generator->createProxy($target, FinalService::class, ['ping']);

        $this->assertSame($target, $proxy);
        $this->assertEquals('pong', $proxy->ping());
    }

    public function test_service_with_default_constants_and_objects_generates_proxy_safely(): void
    {
        $target = new ServiceWithDefaults();
        $proxy = $this->generator->createProxy($target, ServiceWithDefaults::class, ['process']);

        $this->assertInstanceOf(ServiceWithDefaults::class, $proxy);
        $this->assertEquals(42, $proxy->process());
    }
}

class ServiceWithDefaults
{
    const DEFAULT_LIMIT = 42;

    public function process(int $limit = self::DEFAULT_LIMIT, ?\stdClass $obj = null): int
    {
        return $limit;
    }
}
