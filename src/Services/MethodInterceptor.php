<?php

namespace SalvatoreCervone\LogOperations\Services;

use Illuminate\Contracts\Foundation\Application;
use SalvatoreCervone\LogOperations\Facades\LogOperations;

/**
 * Proxy Interceptor Dinamico per l'intercettazione a runtime
 * delle funzioni interne e metodi di servizio dell'applicazione.
 *
 * Si aggancia al Service Container di Laravel tramite app()->extend()
 * o decoratori per tracciare chiamate, argomenti, tempo di esecuzione ed eccezioni
 * senza modificare una singola riga di codice nei file sorgente dell'app.
 */
class MethodInterceptor
{
    protected Application $app;
    protected RuleEngine $ruleEngine;

    public function __construct(Application $app, RuleEngine $ruleEngine)
    {
        $this->app = $app;
        $this->ruleEngine = $ruleEngine;
    }

    /**
     * Registra i proxy nel Service Container per tutte le classi
     * che hanno almeno un metodo monitorato attivo.
     */
    public function registerActiveInterceptors(): void
    {
        $activeRules = $this->ruleEngine->getActiveRules()['methods'] ?? [];
        if (empty($activeRules)) {
            return;
        }

        // Raggruppa per nome classe
        $classesToHook = [];
        foreach (array_keys($activeRules) as $target) {
            if (str_contains($target, '@')) {
                [$className, $method] = explode('@', $target, 2);
                $classesToHook[$className][] = $method;
            }
        }

        foreach ($classesToHook as $className => $methods) {
            if (!class_exists($className)) {
                continue;
            }

            try {
                // Registra il wrapper quando la classe viene risolta dal container
                $this->app->extend($className, function ($instance) use ($className, $methods) {
                    return $this->createProxy($instance, $className, $methods);
                });
            } catch (\Throwable $e) {
                // Se la classe non è bindabile come estensione, prosegui
            }
        }
    }

    /**
     * Crea un proxy dinamico trasparente per l'istanza.
     */
    public function createProxy(object $target, string $className, array $monitoredMethods): object
    {
        $engine = $this->ruleEngine;

        return new class($target, $className, $monitoredMethods, $engine) {
            protected object $target;
            protected string $className;
            protected array $monitoredMethods;
            protected RuleEngine $engine;

            public function __construct(object $target, string $className, array $monitoredMethods, RuleEngine $engine)
            {
                $this->target = $target;
                $this->className = $className;
                $this->monitoredMethods = $monitoredMethods;
                $this->engine = $engine;
            }

            /**
             * Intercetta tutte le chiamate ai metodi.
             */
            public function __call(string $method, array $arguments)
            {
                $isMonitored = in_array('*', $this->monitoredMethods) || in_array($method, $this->monitoredMethods);

                if (!$isMonitored) {
                    return $this->target->$method(...$arguments);
                }

                $startTime = microtime(true);
                $label = class_basename($this->className) . '::' . $method;

                try {
                    $result = $this->target->$method(...$arguments);
                    $durationMs = round((microtime(true) - $startTime) * 1000, 2);

                    // Sanitizza argomenti e risultato per i log
                    $safeArgs = $this->sanitizeForLog($arguments);
                    $safeResult = $this->sanitizeForLog($result);

                    LogOperations::step($label, [
                        'type'        => 'method_execution',
                        'class'       => $this->className,
                        'method'      => $method,
                        'duration_ms' => $durationMs,
                        'arguments'   => $safeArgs,
                        'result'      => $safeResult,
                        'status'      => 'success',
                    ]);

                    return $result;
                } catch (\Throwable $e) {
                    $durationMs = round((microtime(true) - $startTime) * 1000, 2);

                    LogOperations::step($label . ' [ERRORE]', [
                        'type'        => 'method_execution',
                        'class'       => $this->className,
                        'method'      => $method,
                        'duration_ms' => $durationMs,
                        'arguments'   => $this->sanitizeForLog($arguments),
                        'exception'   => get_class($e),
                        'message'     => $e->getMessage(),
                        'file'        => $e->getFile() . ':' . $e->getLine(),
                        'status'      => 'error',
                    ]);

                    throw $e;
                }
            }

            /**
             * Inoltra le proprietà pubbliche al target.
             */
            public function __get(string $name)
            {
                return $this->target->$name;
            }

            public function __set(string $name, $value): void
            {
                $this->target->$name = $value;
            }

            public function __isset(string $name): bool
            {
                return isset($this->target->$name);
            }

            /**
             * Sanitizza dati complessi per evitare crash o log troppo pesanti.
             */
            protected function sanitizeForLog($data, int $depth = 0)
            {
                if ($depth > 3) {
                    return '[Max Depth Reached]';
                }

                if (is_null($data) || is_scalar($data)) {
                    return $data;
                }

                if (is_array($data)) {
                    $cleaned = [];
                    foreach (array_slice($data, 0, 20) as $k => $v) {
                        $cleaned[$k] = $this->sanitizeForLog($v, $depth + 1);
                    }
                    return $cleaned;
                }

                if (is_object($data)) {
                    if (method_exists($data, 'toArray')) {
                        return array_slice($data->toArray(), 0, 10);
                    }
                    return '[Object: ' . get_class($data) . ']';
                }

                return '[Resource/Unknown]';
            }
        };
    }
}
