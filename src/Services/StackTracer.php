<?php

namespace SalvatoreCervone\LogOperations\Services;

/**
 * Motore di tracciamento dello stack a 2 livelli.
 *
 * Livello 1 (Core): solo frame appartenenti al codice applicativo proprietario
 *   (es. classi in app/), escludendo tutto il framework e i vendor.
 *
 * Livello 2 (Full): l'intero stack di esecuzione, incluse le chiamate
 *   interne di Laravel (Illuminate\...), Symfony e package di terze parti.
 *
 * Ogni frame viene arricchito con il flag 'is_core' per permettere al
 * frontend Vue di filtrare e visualizzare i due livelli con un toggle.
 */
class StackTracer
{
    protected bool $enabled;
    protected bool $onlyOnError;
    protected array $projectPaths;
    protected array $excludePaths;
    protected int $maxFrames;
    protected bool $traceDbCallers;
    protected int $maxDbCallers;

    /**
     * Callers delle query DB rilevati durante la richiesta.
     */
    protected array $dbCallers = [];

    public function __construct(array $config = [])
    {
        $this->enabled = $config['enabled'] ?? true;
        $this->onlyOnError = $config['only_on_error'] ?? false;
        $this->projectPaths = $config['project_paths'] ?? ['app/'];
        $this->excludePaths = $config['exclude_paths'] ?? [];
        $this->maxFrames = $config['max_frames'] ?? 100;
        $this->traceDbCallers = $config['trace_db_callers'] ?? true;
        $this->maxDbCallers = $config['max_db_callers'] ?? 50;
    }

    /**
     * Verifica se il tracciamento è abilitato.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Verifica se il tracciamento deve avvenire solo su errori.
     */
    public function isOnlyOnError(): bool
    {
        return $this->onlyOnError;
    }

    /**
     * Cattura lo stack trace corrente e lo classifica a 2 livelli.
     *
     * @param \Throwable|null $exception Se presente, usa lo stack dell'eccezione
     * @return array Array di frame, ognuno con flag 'is_core'
     */
    public function capture(?\Throwable $exception = null): array
    {
        if (!$this->enabled) {
            return [];
        }

        if ($exception !== null) {
            $rawTrace = $exception->getTrace();
        } else {
            $rawTrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $this->maxFrames + 20);
            // Rimuoviamo i frame del StackTracer stesso e del middleware
            $rawTrace = $this->stripInternalFrames($rawTrace);
        }

        return $this->processFrames($rawTrace);
    }

    /**
     * Cattura lo stack di esecuzione dal backtrace corrente
     * (usato quando non c'è un'eccezione, per tracciare il flusso di successo).
     *
     * Arricchisce la catena con i metodi applicativi eseguiti, gli step e il controller
     * della richiesta, così che anche le risposte 200 OK abbiano la visibilità del codice Core.
     */
    public function captureCurrentStack($route = null, ?array $customTraces = null): array
    {
        if (!$this->enabled) {
            return [];
        }

        $coreFrames = [];
        $order = 0;

        // 1. Metodi applicativi tracciati durante la richiesta (es. OrderService, PaymentService)
        if (!empty($customTraces['traces'])) {
            foreach (array_reverse($customTraces['traces']) as $trace) {
                $file = $trace['file'] ?? '';
                $coreFrames[] = [
                    'order' => $order++,
                    'file' => $this->relativePath($file),
                    'line' => $trace['line'] ?? null,
                    'class' => $trace['class'] ?? null,
                    'function' => $trace['function'] ?? 'trace',
                    'label' => $trace['label'] ?? null,
                    'type' => '->',
                    'is_core' => true,
                ];
            }
        }

        // Estraiamo il controller o closure della rotta per referenza
        $controllerClass = null;
        $controllerMethod = null;
        if ($route) {
            $action = $route->getAction();
            $controllerAction = $action['controller'] ?? (is_string($action['uses'] ?? null) ? $action['uses'] : null);
            if (!empty($controllerAction) && is_string($controllerAction)) {
                $parts = explode('@', $controllerAction);
                $controllerClass = $parts[0] ?? null;
                $controllerMethod = $parts[1] ?? '__invoke';
            }
        }

        // 2. Step eseguiti in classi applicative esterne al controller
        if (!empty($customTraces['steps'])) {
            foreach (array_reverse($customTraces['steps']) as $step) {
                if (!empty($step['file']) && !empty($step['class']) && !empty($step['function'])) {
                    $stepFunc = $step['function'];
                    // Se lo step è stato invocato dentro il controller stesso, è già rappresentato dal frame del controller
                    if ($step['class'] === $controllerClass && $stepFunc === $controllerMethod) {
                        continue;
                    }
                    $alreadyPresent = false;
                    foreach ($coreFrames as $cf) {
                        if ($cf['class'] === $step['class'] && $cf['function'] === $stepFunc) {
                            $alreadyPresent = true;
                            break;
                        }
                    }
                    if (!$alreadyPresent) {
                        $coreFrames[] = [
                            'order' => $order++,
                            'file' => $this->relativePath($step['file']),
                            'line' => $step['line'] ?? null,
                            'class' => $step['class'],
                            'function' => $stepFunc,
                            'label' => $step['label'] ?? null,
                            'type' => '->',
                            'is_core' => true,
                        ];
                    }
                }
            }
        }

        // 3. Controller o Closure che ha gestito la richiesta
        if ($route) {
            $action = $route->getAction();
            $controllerAction = $action['controller'] ?? (is_string($action['uses'] ?? null) ? $action['uses'] : null);
            if (!empty($controllerAction) && is_string($controllerAction)) {
                $parts = explode('@', $controllerAction);
                $class = $parts[0] ?? null;
                $method = $parts[1] ?? '__invoke';
                $file = null;
                $line = null;
                if ($class && class_exists($class)) {
                    try {
                        if (method_exists($class, $method)) {
                            $ref = new \ReflectionMethod($class, $method);
                            $file = $ref->getFileName();
                            $line = $ref->getStartLine();
                        } else {
                            $refClass = new \ReflectionClass($class);
                            $file = $refClass->getFileName();
                            $line = $refClass->getStartLine();
                        }
                    } catch (\Throwable $e) {}
                }
                $coreFrames[] = [
                    'order' => $order++,
                    'file' => $file ? $this->relativePath($file) : null,
                    'line' => $line,
                    'class' => $class,
                    'function' => $method,
                    'type' => '->',
                    'is_core' => true,
                ];
            } elseif (!empty($action['uses']) && $action['uses'] instanceof \Closure) {
                try {
                    $ref = new \ReflectionFunction($action['uses']);
                    $coreFrames[] = [
                        'order' => $order++,
                        'file' => $this->relativePath($ref->getFileName()),
                        'line' => $ref->getStartLine(),
                        'class' => 'Closure',
                        'function' => '{closure}',
                        'type' => '::',
                        'is_core' => true,
                    ];
                } catch (\Throwable $e) {}
            }
        }

        // 4. Framework stack dal backtrace del middleware
        $rawTrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $this->maxFrames + 20);
        $rawTrace = $this->stripInternalFrames($rawTrace);
        $frameworkFrames = $this->processFrames($rawTrace);

        // Unisci: prima i frame applicativi Core eseguiti, poi la catena del framework
        $allFrames = [];
        $idx = 0;
        foreach ($coreFrames as $f) {
            $f['order'] = $idx++;
            $allFrames[] = $f;
        }
        foreach ($frameworkFrames as $f) {
            $f['order'] = $idx++;
            $allFrames[] = $f;
        }

        return array_slice($allFrames, 0, $this->maxFrames);
    }

    /**
     * Registra un caller di query DB rilevato durante la richiesta.
     * Viene chiamato dal listener DB::listen() configurato nel middleware.
     */
    public function recordDbCaller(array $callerInfo): void
    {
        if (!$this->traceDbCallers || count($this->dbCallers) >= $this->maxDbCallers) {
            return;
        }

        $this->dbCallers[] = $callerInfo;
    }

    /**
     * Restituisce i callers DB registrati.
     */
    public function getDbCallers(): array
    {
        return $this->dbCallers;
    }

    /**
     * Resetta i callers DB per la prossima richiesta.
     */
    public function flushDbCallers(): void
    {
        $this->dbCallers = [];
    }

    /**
     * Individua il primo frame appartenente al codice applicativo
     * nello stack corrente. Utile per identificare l'origine di una query DB.
     */
    public function findProjectCaller(): ?array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30);

        foreach ($trace as $frame) {
            $file = $frame['file'] ?? '';
            if ($this->isCoreFrame($file)) {
                return [
                    'file' => $this->relativePath($file),
                    'line' => $frame['line'] ?? null,
                    'class' => $frame['class'] ?? null,
                    'function' => $frame['function'] ?? null,
                ];
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Metodi Interni
    |--------------------------------------------------------------------------
    */

    /**
     * Processa i frame grezzi, classificandoli come Core o Framework.
     */
    protected function processFrames(array $rawTrace): array
    {
        $frames = [];
        $order = 0;

        foreach ($rawTrace as $raw) {
            if ($order >= $this->maxFrames) {
                break;
            }

            $file = $raw['file'] ?? '';

            // Escludi percorsi configurati
            if ($this->isExcludedPath($file)) {
                continue;
            }

            $relativePath = $this->relativePath($file);
            $isCore = $this->isCoreFrame($file);

            $frames[] = [
                'order' => $order,
                'file' => $relativePath,
                'line' => $raw['line'] ?? null,
                'class' => $raw['class'] ?? null,
                'function' => $raw['function'] ?? null,
                'type' => $raw['type'] ?? null, // '->' o '::'
                'is_core' => $isCore,
            ];

            $order++;
        }

        return $frames;
    }

    /**
     * Verifica se un file appartiene al codice proprietario (Livello 1 - Core).
     */
    protected function isCoreFrame(string $filePath): bool
    {
        if (empty($filePath)) {
            return false;
        }

        // Se il file contiene /vendor/, non è codice core
        if (str_contains($filePath, '/vendor/') || str_contains($filePath, '\\vendor\\')) {
            return false;
        }

        $normalized = str_replace('\\', '/', $filePath);

        foreach ($this->projectPaths as $projectPath) {
            $cleanProject = trim(str_replace('\\', '/', $projectPath), '/');
            if (
                str_starts_with($normalized, $cleanProject . '/')
                || str_contains($normalized, '/' . $cleanProject . '/')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica se un percorso deve essere escluso dallo stack.
     */
    protected function isExcludedPath(string $filePath): bool
    {
        foreach ($this->excludePaths as $excludePath) {
            if (str_contains($filePath, $excludePath)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Converte un percorso assoluto in relativo rispetto alla base del progetto.
     */
    protected function relativePath(string $absolutePath): string
    {
        $basePath = base_path() . DIRECTORY_SEPARATOR;

        if (str_starts_with($absolutePath, $basePath)) {
            return substr($absolutePath, strlen($basePath));
        }

        return $absolutePath;
    }

    /**
     * Rimuove i frame interni del pacchetto log-operations dallo stack
     * per non inquinare la visualizzazione.
     */
    protected function stripInternalFrames(array $trace): array
    {
        $ownNamespace = 'SalvatoreCervone\\LogOperations\\';

        $filtered = [];
        $passedInternal = false;

        foreach ($trace as $frame) {
            $class = $frame['class'] ?? '';

            // Salta i frame del nostro stesso pacchetto
            if (!$passedInternal && str_starts_with($class, $ownNamespace)) {
                continue;
            }

            $passedInternal = true;
            $filtered[] = $frame;
        }

        return $filtered;
    }
}
