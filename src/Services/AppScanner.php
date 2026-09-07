<?php

namespace SalvatoreCervone\LogOperations\Services;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SalvatoreCervone\LogOperations\Models\OperationRule;

/**
 * Scanner per l'introspezione automatica dell'applicazione Laravel.
 *
 * Fornisce al pannello grafico:
 * 1. Il catalogo completo delle rotte registrate in Laravel
 * 2. Il catalogo delle classi e dei metodi pubblici del codice applicativo (app/)
 */
class AppScanner
{
    /**
     * Recupera tutte le rotte dell'applicazione, formattate e arricchite
     * con lo stato di monitoraggio corrente.
     */
    public function getRoutes(): array
    {
        $allRoutes = Route::getRoutes()->getRoutes();
        $rules = OperationRule::routes()->get()->keyBy('target');

        $result = [];
        $apiPrefix = config('logoperations.api_prefix', 'api/logoperations');

        foreach ($allRoutes as $route) {
            $uri = $route->uri();

            // Salta le rotte interne del pacchetto per evitare confusione
            if (Str::is([$apiPrefix . '*', 'api/log-operations*'], $uri)) {
                continue;
            }

            $methods = array_diff($route->methods(), ['HEAD']);
            $action = $route->getActionName();
            $name = $route->getName();

            // Determina Area
            $area = 'Web';
            if (Str::startsWith($uri, 'api/') || in_array('api', $route->gatherMiddleware())) {
                $area = 'API';
            } elseif (Str::startsWith($uri, 'admin/')) {
                $area = 'Admin';
            }

            // Estrai Controller e Azione leggibili
            $controllerName = 'Closure';
            $methodName = '';
            if (str_contains($action, '@')) {
                [$fullController, $methodName] = explode('@', $action);
                $controllerName = class_basename($fullController);
            } elseif ($action !== 'Closure') {
                $controllerName = class_basename($action);
            }

            // Verifica se esiste già una regola attiva
            $cleanUri = trim($uri, '/');
            $rule = $rules->first(function ($r) use ($cleanUri) {
                return $r->matchesUri($cleanUri);
            });

            $result[] = [
                'uri'             => $uri,
                'clean_uri'       => $cleanUri,
                'methods'         => array_values($methods),
                'name'            => $name ?: null,
                'action'          => $action,
                'controller'      => $controllerName,
                'controller_method' => $methodName,
                'area'            => $area,
                'is_tracked'      => $rule ? $rule->is_active : false,
                'rule_id'         => $rule ? $rule->id : null,
                'stack_level'     => $rule ? $rule->stack_level : 'base',
                'http_methods'    => $rule ? $rule->http_methods : ['*'],
            ];
        }

        // Ordina per Area e poi per URI
        usort($result, function ($a, $b) {
            if ($a['area'] === $b['area']) {
                return strcmp($a['uri'], $b['uri']);
            }
            return strcmp($a['area'], $b['area']);
        });

        return $result;
    }

    /**
     * Scansiona la cartella app/ per trovare classi applicative (Services, Actions, Repositories, ecc.)
     * e i loro metodi pubblici tracciabili.
     */
    public function getClasses(string $basePath = null): array
    {
        $basePath = $basePath ?: app_path();
        if (!is_dir($basePath)) {
            return [];
        }

        $rules = OperationRule::methods()->get()->keyBy('target');
        $classes = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $filePath = $file->getRealPath();
            $className = $this->extractClassNameFromFile($filePath);

            if (!$className || !class_exists($className)) {
                continue;
            }

            try {
                $ref = new ReflectionClass($className);

                // Ignora classi astratte, interfacce, trait o classi interne di framework
                if ($ref->isAbstract() || $ref->isInterface() || $ref->isTrait()) {
                    continue;
                }

                // Considera solo metodi pubblici dichiarati direttamente in questa classe
                $methods = [];
                foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                    // Ignora metodi magici (tranne __invoke)
                    if (str_starts_with($method->getName(), '__') && $method->getName() !== '__invoke') {
                        continue;
                    }

                    // Ignora metodi ereditati da classi genitore esterne ad app/
                    $declaringClass = $method->getDeclaringClass()->getName();
                    if ($declaringClass !== $className) {
                        continue;
                    }

                    $targetKey = $className . '@' . $method->getName();
                    $rule = $rules->get($targetKey);

                    // Verifica se il metodo o la classe possiedono l'attributo #[Traceable]
                    $hasTraceableAttr = !empty($method->getAttributes(\SalvatoreCervone\LogOperations\Attributes\Traceable::class))
                        || !empty($ref->getAttributes(\SalvatoreCervone\LogOperations\Attributes\Traceable::class));

                    // Estrai firma dei parametri
                    $params = [];
                    foreach ($method->getParameters() as $param) {
                        $params[] = [
                            'name'     => $param->getName(),
                            'type'     => $param->hasType() ? (string) $param->getType() : 'mixed',
                            'optional' => $param->isOptional(),
                        ];
                    }

                    $methods[] = [
                        'name'                   => $method->getName(),
                        'target'                 => $targetKey,
                        'parameters'             => $params,
                        'return_type'            => $method->hasReturnType() ? (string) $method->getReturnType() : 'mixed',
                        'is_tracked'             => $rule ? $rule->is_active : $hasTraceableAttr,
                        'rule_id'                => $rule ? $rule->id : null,
                        'stack_level'            => $rule ? $rule->stack_level : 'core',
                        'is_traceable_attribute' => $hasTraceableAttr,
                    ];
                }

                if (!empty($methods)) {
                    $category = 'Altro';
                    if (str_contains($className, '\\Services\\')) $category = 'Services';
                    elseif (str_contains($className, '\\Actions\\')) $category = 'Actions';
                    elseif (str_contains($className, '\\Repositories\\')) $category = 'Repositories';
                    elseif (str_contains($className, '\\Controllers\\')) $category = 'Controllers';
                    elseif (str_contains($className, '\\Models\\')) $category = 'Models';

                    $classes[] = [
                        'class_name' => $className,
                        'short_name' => $ref->getShortName(),
                        'category'   => $category,
                        'methods'    => $methods,
                    ];
                }
            } catch (\Throwable $e) {
                // Se una classe ha dipendenze complesse non caricabili durante la scansione, la saltiamo
                continue;
            }
        }

        // Ordina per categoria e nome classe
        usort($classes, function ($a, $b) {
            if ($a['category'] === $b['category']) {
                return strcmp($a['short_name'], $b['short_name']);
            }
            return strcmp($a['category'], $b['category']);
        });

        return $classes;
    }

    /**
     * Risolve il namespace completo della classe a partire dal percorso del file.
     */
    protected function extractClassNameFromFile(string $filePath): ?string
    {
        $appPath = realpath(app_path());
        $realFile = realpath($filePath);

        if (!str_starts_with($realFile, $appPath)) {
            return null;
        }

        $relative = substr($realFile, strlen($appPath) + 1);
        $relativeWithoutExt = substr($relative, 0, -4); // rimuove .php

        return 'App\\' . str_replace('/', '\\', $relativeWithoutExt);
    }
}
