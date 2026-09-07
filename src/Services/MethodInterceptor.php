<?php

namespace SalvatoreCervone\LogOperations\Services;

use Illuminate\Contracts\Foundation\Application;
use ReflectionClass;
use SalvatoreCervone\LogOperations\Attributes\Traceable;
use SalvatoreCervone\LogOperations\Facades\LogOperations;

/**
 * Proxy Interceptor Dinamico per l'intercettazione a runtime
 * delle funzioni interne e metodi di servizio dell'applicazione.
 *
 * Utilizza ProxyClassGenerator per generare sottoclassi proxy tipizzate
 * che estendono direttamente la classe originale target, prevenendo qualsiasi
 * TypeError nella Dependency Injection di Laravel.
 */
class MethodInterceptor
{
    protected Application $app;
    protected RuleEngine $ruleEngine;
    protected ProxyClassGenerator $proxyGenerator;

    /**
     * Elenco delle classi registrate per il tracciamento.
     *
     * @var array<string, array<string>>
     */
    protected array $registeredClasses = [];

    public function __construct(
        Application $app,
        RuleEngine $ruleEngine,
        ?ProxyClassGenerator $proxyGenerator = null
    ) {
        $this->app = $app;
        $this->ruleEngine = $ruleEngine;
        $this->proxyGenerator = $proxyGenerator ?: new ProxyClassGenerator();
    }

    /**
     * Registra i proxy nel Service Container per tutte le classi
     * che hanno almeno un metodo monitorato attivo (via DB o via attributo #[Traceable]).
     */
    public function registerActiveInterceptors(): void
    {
        // 1. Regole attive da Database / Cache
        $activeRules = $this->ruleEngine->getActiveRules()['methods'] ?? [];
        $classesToHook = [];

        foreach (array_keys($activeRules) as $target) {
            if (str_contains($target, '@')) {
                [$className, $method] = explode('@', $target, 2);
                $classesToHook[$className][] = $method;
            }
        }

        // 2. Registra le classi identificate
        foreach ($classesToHook as $className => $methods) {
            $this->registerTraceableClass($className, array_unique($methods));
        }
    }

    /**
     * Registra esplicitamente una classe per l'intercettazione dei suoi metodi.
     *
     * @param string $className Nome completo della classe
     * @param array<string>|null $methods Metodi specifici da monitorare (null = tutti i metodi o quelli con #[Traceable])
     */
    public function registerTraceableClass(string $className, ?array $methods = null): void
    {
        $className = ltrim($className, '\\');

        if (!class_exists($className) && !interface_exists($className)) {
            return;
        }

        // Se i metodi non sono specificati, ispeziona la classe e i suoi attributi #[Traceable]
        if ($methods === null) {
            $methods = $this->discoverTraceableMethods($className);
        }

        if (empty($methods)) {
            $methods = ['*'];
        }

        $this->registeredClasses[$className] = $methods;

        try {
            // Registra il wrapper tipizzato quando la classe viene risolta dal container
            $this->app->extend($className, function ($instance) use ($className, $methods) {
                return $this->createProxy($instance, $className, $methods);
            });
        } catch (\Throwable $e) {
            // Se la classe non è bindata o estendibile nel container, prosegui silenziosamente
        }
    }

    /**
     * Crea un proxy dinamico tipizzato per l'istanza.
     */
    public function createProxy(object $target, string $className, array $monitoredMethods): object
    {
        return $this->proxyGenerator->createProxy($target, $className, $monitoredMethods, $this->ruleEngine);
    }

    /**
     * Ispeziona la classe alla ricerca di metodi con l'attributo #[Traceable].
     */
    public function discoverTraceableMethods(string $className): array
    {
        if (!class_exists($className)) {
            return [];
        }

        $ref = new ReflectionClass($className);
        $methods = [];

        // Se l'intera classe ha #[Traceable], tutti i metodi pubblici sono monitorati
        if (!empty($ref->getAttributes(Traceable::class))) {
            return ['*'];
        }

        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (!empty($method->getAttributes(Traceable::class))) {
                $methods[] = $method->getName();
            }
        }

        return $methods;
    }

    /**
     * Restituisce l'istanza del generatore proxy.
     */
    public function getProxyGenerator(): ProxyClassGenerator
    {
        return $this->proxyGenerator;
    }

    /**
     * Restituisce le classi attualmente registrate per l'intercettazione.
     */
    public function getRegisteredClasses(): array
    {
        return $this->registeredClasses;
    }

    /**
     * Sanitizza dati complessi per evitare crash o log troppo pesanti.
     */
    public static function sanitizeForLog($data, int $depth = 0)
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
                $cleaned[$k] = self::sanitizeForLog($v, $depth + 1);
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
}
