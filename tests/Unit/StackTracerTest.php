<?php

namespace SalvatoreCervone\LogOperations\Tests\Unit;

use SalvatoreCervone\LogOperations\Tests\TestCase;
use SalvatoreCervone\LogOperations\Services\StackTracer;

class StackTracerTest extends TestCase
{
    public function test_it_correctly_identifies_enabled_state(): void
    {
        $tracer = new StackTracer(['enabled' => true]);
        $this->assertTrue($tracer->isEnabled());

        $disabledTracer = new StackTracer(['enabled' => false]);
        $this->assertFalse($disabledTracer->isEnabled());
    }

    public function test_it_captures_and_classifies_exception_frames(): void
    {
        $tracer = new StackTracer([
            'enabled' => true,
            'project_paths' => ['app/', 'tests/'],
            'max_frames' => 50,
        ]);

        try {
            throw new \RuntimeException('Test exception for stack tracer');
        } catch (\Throwable $e) {
            $frames = $tracer->capture($e);
        }

        $this->assertIsArray($frames);
        $this->assertNotEmpty($frames);

        // Ogni frame deve avere la proprietà is_core (boolean)
        foreach ($frames as $frame) {
            $this->assertArrayHasKey('is_core', $frame);
            $this->assertIsBool($frame['is_core']);
            $this->assertArrayHasKey('file', $frame);
        }
    }

    public function test_it_manages_db_callers(): void
    {
        $tracer = new StackTracer(['enabled' => true, 'trace_db_callers' => true]);

        $this->assertEmpty($tracer->getDbCallers());

        $tracer->recordDbCaller(['sql' => 'SELECT * FROM users', 'time_ms' => 1.5]);
        $callers = $tracer->getDbCallers();

        $this->assertCount(1, $callers);
        $this->assertEquals('SELECT * FROM users', $callers[0]['sql']);
        $this->assertEquals(1.5, $callers[0]['time_ms']);

        $tracer->flushDbCallers();
        $this->assertEmpty($tracer->getDbCallers());
    }
}
