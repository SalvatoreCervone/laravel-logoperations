<?php

namespace SalvatoreCervone\LogOperations\Services;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use ReflectionIntersectionType;
use SalvatoreCervone\LogOperations\Contracts\LogOperationsProxy;

/**
 * Generatore dinamico di classi proxy tipizzate.
 *
 * Risolve il problema del type-hinting nella Dependency Injection di Laravel:
 * genera una sottoclasse PHP a runtime che estende direttamente la classe target
 * originale, garantendo che "$proxy instanceof TargetClass" sia sempre true
 * e preservando al 100% tutte le firme dei metodi (tipi di ritorno, parametri,
 * union types, void, passaggi per riferimento e variadics).
 */
class ProxyClassGenerator
{
    /**
     * Cache in memoria delle classi proxy già generate e dichiarate.
     *
     * @var array<string, string>
     */
    protected static array $generatedClasses = [];

    /**
     * Crea un'istanza proxy tipizzata per il target specificato.
     * Se la classe è final o non estendibile, restituisce il target originale.
     */
    public function createProxy(object $target, string $className, array $monitoredMethods, ?RuleEngine $engine = null): object
    {
        $proxyClassName = $this->generate($className);

        if ($proxyClassName === null || !class_exists($proxyClassName)) {
            return $target;
        }

        $ref = new ReflectionClass($proxyClassName);
        $proxy = $ref->newInstanceWithoutConstructor();

        if ($proxy instanceof LogOperationsProxy) {
            $proxy->__initLogOperationsProxy($target, $className, $monitoredMethods, $engine);
        }

        return $proxy;
    }

    /**
     * Genera e dichiara la classe proxy per la classe specificata.
     * Restituisce il nome completo della classe proxy generata, oppure null se non estendibile.
     */
    public function generate(string $className): ?string
    {
        $cleanClassName = ltrim($className, '\\');

        if (isset(self::$generatedClasses[$cleanClassName])) {
            return self::$generatedClasses[$cleanClassName];
        }

        if (!class_exists($cleanClassName) && !interface_exists($cleanClassName)) {
            return null;
        }

        $ref = new ReflectionClass($cleanClassName);

        // Se la classe è final, PHP impedisce l'ereditarietà: non è possibile generare una sottoclasse
        if ($ref->isFinal()) {
            return null;
        }

        $proxyShortName = str_replace('\\', '_', $cleanClassName) . '_Proxy';
        $proxyNamespace = 'SalvatoreCervone\\LogOperations\\GeneratedProxies';
        $fullProxyName = $proxyNamespace . '\\' . $proxyShortName;

        if (class_exists($fullProxyName, false)) {
            self::$generatedClasses[$cleanClassName] = $fullProxyName;
            return $fullProxyName;
        }

        $code = $this->compileProxyClass($ref, $proxyNamespace, $proxyShortName, $cleanClassName);

        eval($code);

        self::$generatedClasses[$cleanClassName] = $fullProxyName;

        return $fullProxyName;
    }

    /**
     * Compila il codice sorgente PHP della classe proxy.
     */
    protected function compileProxyClass(ReflectionClass $ref, string $namespace, string $shortName, string $targetClass): string
    {
        $isInterface = $ref->isInterface();
        $extendsClause = $isInterface ? '' : 'extends \\' . $targetClass;
        $implementsClause = $isInterface
            ? 'implements \\' . $targetClass . ', \\' . LogOperationsProxy::class
            : 'implements \\' . LogOperationsProxy::class;

        $methodsCode = $this->compileMethods($ref);

        return <<<PHP
namespace {$namespace};

class {$shortName} {$extendsClause} {$implementsClause}
{
    protected object \$__logOperationsTarget;
    protected string \$__logOperationsClassName;
    protected array \$__logOperationsMonitoredMethods = [];
    protected ?\\SalvatoreCervone\\LogOperations\\Services\\RuleEngine \$__logOperationsEngine = null;

    public function __initLogOperationsProxy(object \$target, string \$className, array \$monitoredMethods, ?\\SalvatoreCervone\\LogOperations\\Services\\RuleEngine \$engine = null): void
    {
        \$this->__logOperationsTarget = \$target;
        \$this->__logOperationsClassName = \$className;
        \$this->__logOperationsMonitoredMethods = \$monitoredMethods;
        \$this->__logOperationsEngine = \$engine;
    }

    public function __getLogOperationsTarget(): object
    {
        return \$this->__logOperationsTarget;
    }

    public function __getLogOperationsMonitoredMethods(): array
    {
        return \$this->__logOperationsMonitoredMethods;
    }

    protected function __isLogOperationsMethodMonitored(string \$method): bool
    {
        return in_array('*', \$this->__logOperationsMonitoredMethods) || in_array(\$method, \$this->__logOperationsMonitoredMethods);
    }

{$methodsCode}

    public function __get(string \$name)
    {
        return \$this->__logOperationsTarget->\$name;
    }

    public function __set(string \$name, \$value): void
    {
        \$this->__logOperationsTarget->\$name = \$value;
    }

    public function __isset(string \$name): bool
    {
        return isset(\$this->__logOperationsTarget->\$name);
    }

    public function __unset(string \$name): void
    {
        unset(\$this->__logOperationsTarget->\$name);
    }

    public function __call(string \$name, array \$arguments)
    {
        return \$this->__logOperationsTarget->\$name(...\$arguments);
    }
}
PHP;
    }

    /**
     * Compila tutti i metodi pubblici della classe per inoltrare e intercettare le chiamate.
     */
    protected function compileMethods(ReflectionClass $ref): string
    {
        $code = [];
        $magicMethodsToSkip = [
            '__construct', '__destruct', '__clone',
            '__get', '__set', '__isset', '__unset', '__call',
        ];

        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();

            if (in_array($name, $magicMethodsToSkip) || $method->isFinal() || $method->isStatic()) {
                continue;
            }

            $code[] = $this->compileSingleMethod($method);
        }

        return implode("\n\n", $code);
    }

    /**
     * Compila un singolo metodo pubblico con rispetto esatto della signature.
     */
    protected function compileSingleMethod(ReflectionMethod $method): string
    {
        $methodName = $method->getName();
        $paramsDecl = [];
        $argsPassed = [];
        $argsArray = [];

        foreach ($method->getParameters() as $param) {
            $paramName = $param->getName();
            $paramStr = '';

            $type = $this->formatType($param->getType());
            if ($type !== '') {
                $paramStr .= $type . ' ';
            }

            if ($param->isPassedByReference()) {
                $paramStr .= '&';
            }

            if ($param->isVariadic()) {
                $paramStr .= '...';
                $argsPassed[] = '...$' . $paramName;
            } else {
                $argsPassed[] = '$' . $paramName;
            }

            $paramStr .= '$' . $paramName;

            if ($param->isDefaultValueAvailable()) {
                $paramStr .= ' = ' . var_export($param->getDefaultValue(), true);
            }

            $paramsDecl[] = $paramStr;
            $argsArray[] = '$' . $paramName;
        }

        $paramsString = implode(', ', $paramsDecl);
        $argsCallString = implode(', ', $argsPassed);
        $argsArrayString = '[' . implode(', ', $argsArray) . ']';

        $returnTypeRef = $method->getReturnType();
        $returnTypeString = '';
        $isVoid = false;
        $isNever = false;

        if ($returnTypeRef !== null) {
            $returnTypeString = ': ' . $this->formatType($returnTypeRef);
            if ($returnTypeRef instanceof ReflectionNamedType) {
                if ($returnTypeRef->getName() === 'void') {
                    $isVoid = true;
                } elseif ($returnTypeRef->getName() === 'never') {
                    $isNever = true;
                }
            }
        }

        $callTarget = "\$this->__logOperationsTarget->{$methodName}({$argsCallString})";

        if ($isNever) {
            $forwardCall = "{$callTarget};";
            $successReturn = "{$callTarget};";
        } elseif ($isVoid) {
            $forwardCall = "{$callTarget}; return;";
            $successReturn = "{$callTarget}; return;";
        } else {
            $forwardCall = "return {$callTarget};";
            $successReturn = "\$result = {$callTarget};\n        return \$result;";
        }

        $body = <<<PHP
    public function {$methodName}({$paramsString}){$returnTypeString}
    {
        if (!\$this->__isLogOperationsMethodMonitored('{$methodName}')) {
            {$forwardCall}
        }

        \$startTime = microtime(true);
        \$label = class_basename(\$this->__logOperationsClassName) . '::{$methodName}';

        try {
            {$callTarget};
            \$durationMs = round((microtime(true) - \$startTime) * 1000, 2);

            \\SalvatoreCervone\\LogOperations\\Facades\\LogOperations::step(\$label, [
                'type'        => 'method_execution',
                'class'       => \$this->__logOperationsClassName,
                'method'      => '{$methodName}',
                'duration_ms' => \$durationMs,
                'arguments'   => \\SalvatoreCervone\\LogOperations\\Services\\MethodInterceptor::sanitizeForLog({$argsArrayString}),
                'status'      => 'success',
            ]);

            return;
        } catch (\\Throwable \$e) {
            \$durationMs = round((microtime(true) - \$startTime) * 1000, 2);

            \\SalvatoreCervone\\LogOperations\\Facades\\LogOperations::step(\$label . ' [ERRORE]', [
                'type'        => 'method_execution',
                'class'       => \$this->__logOperationsClassName,
                'method'      => '{$methodName}',
                'duration_ms' => \$durationMs,
                'arguments'   => \\SalvatoreCervone\\LogOperations\\Services\\MethodInterceptor::sanitizeForLog({$argsArrayString}),
                'exception'   => get_class(\$e),
                'message'     => \$e->getMessage(),
                'file'        => \$e->getFile() . ':' . \$e->getLine(),
                'status'      => 'error',
            ]);

            throw \$e;
        }
    }
PHP;

        // Se non è void né never, salva $result e registralo nello step
        if (!$isVoid && !$isNever) {
            $body = <<<PHP
    public function {$methodName}({$paramsString}){$returnTypeString}
    {
        if (!\$this->__isLogOperationsMethodMonitored('{$methodName}')) {
            {$forwardCall}
        }

        \$startTime = microtime(true);
        \$label = class_basename(\$this->__logOperationsClassName) . '::{$methodName}';

        try {
            \$result = {$callTarget};
            \$durationMs = round((microtime(true) - \$startTime) * 1000, 2);

            \\SalvatoreCervone\\LogOperations\\Facades\\LogOperations::step(\$label, [
                'type'        => 'method_execution',
                'class'       => \$this->__logOperationsClassName,
                'method'      => '{$methodName}',
                'duration_ms' => \$durationMs,
                'arguments'   => \\SalvatoreCervone\\LogOperations\\Services\\MethodInterceptor::sanitizeForLog({$argsArrayString}),
                'result'      => \\SalvatoreCervone\\LogOperations\\Services\\MethodInterceptor::sanitizeForLog(\$result),
                'status'      => 'success',
            ]);

            return \$result;
        } catch (\\Throwable \$e) {
            \$durationMs = round((microtime(true) - \$startTime) * 1000, 2);

            \\SalvatoreCervone\\LogOperations\\Facades\\LogOperations::step(\$label . ' [ERRORE]', [
                'type'        => 'method_execution',
                'class'       => \$this->__logOperationsClassName,
                'method'      => '{$methodName}',
                'duration_ms' => \$durationMs,
                'arguments'   => \\SalvatoreCervone\\LogOperations\\Services\\MethodInterceptor::sanitizeForLog({$argsArrayString}),
                'exception'   => get_class(\$e),
                'message'     => \$e->getMessage(),
                'file'        => \$e->getFile() . ':' . \$e->getLine(),
                'status'      => 'error',
            ]);

            throw \$e;
        }
    }
PHP;
        }

        return $body;
    }

    /**
     * Formatta un ReflectionType per la dichiarazione PHP.
     */
    protected function formatType(?ReflectionType $type): string
    {
        if (!$type) {
            return '';
        }

        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();
            if ($type->isBuiltin() || in_array($name, ['self', 'parent', 'static', 'mixed', 'void', 'never'])) {
                return ($type->allowsNull() && !in_array($name, ['null', 'mixed', 'void', 'never'])) ? '?' . $name : $name;
            }
            $formatted = '\\' . ltrim($name, '\\');
            return ($type->allowsNull() && $name !== 'null') ? '?' . $formatted : $formatted;
        }

        if ($type instanceof ReflectionUnionType) {
            $subtypes = array_map(fn($t) => $this->formatType($t), $type->getTypes());
            return implode('|', $subtypes);
        }

        if ($type instanceof ReflectionIntersectionType) {
            $subtypes = array_map(fn($t) => $this->formatType($t), $type->getTypes());
            return implode('&', $subtypes);
        }

        return (string) $type;
    }
}
