<?php

namespace SalvatoreCervone\LogOperations;

use SalvatoreCervone\LogOperations\Services\StackTracer;

/**
 * Manager centrale per il tracciamento manuale delle operazioni.
 *
 * Permette di registrare step e checkpoint all'interno del codice
 * applicativo, che verranno poi salvati insieme al log della richiesta.
 *
 * Utilizzo tramite Facade:
 *   LogOperations::step('Verifica permessi fiscali');
 *   $result = LogOperations::trace('Calcolo giacenze', fn() => $this->calcolaGiacenze());
 */
class LogOperationsManager
{
    /**
     * Step personalizzati registrati durante la richiesta corrente.
     */
    protected array $steps = [];

    /**
     * Risultati delle funzioni tracciate durante la richiesta.
     */
    protected array $traces = [];

    public function __construct(
        protected StackTracer $stackTracer
    ) {}

    /**
     * Registra un checkpoint/step con un'etichetta descrittiva.
     * Lo stack di chiamata viene catturato automaticamente al momento
     * dell'invocazione per identificare esattamente da dove è stato chiamato.
     */
    public function step(string $label, array $context = []): void
    {
        $caller = $this->findCaller();

        $this->steps[] = [
            'label' => $label,
            'context' => $context,
            'file' => $caller['file'] ?? null,
            'line' => $caller['line'] ?? null,
            'class' => $caller['class'] ?? null,
            'function' => $caller['function'] ?? null,
            'timestamp' => microtime(true),
        ];
    }

    /**
     * Esegue e traccia un metodo applicativo o callable, misurandone la durata
     * e registrando classe, metodo reale, file e riga esatti per lo stack trace.
     *
     * Firme supportate:
     * 1. trace([$object, 'methodName'], callable $callback, ?string $label = null)
     * 2. trace('Class@methodName', callable $callback, ?string $label = null)
     * 3. trace('Class::methodName', callable $callback, ?string $label = null)
     * 4. trace('label', callable $callback)
     *
     * @param mixed $target Callable, array [object, 'method'], string 'Class@method' o string label
     * @param callable $callback Funzione da eseguire
     * @param string|null $label Etichetta descrittiva opzionale
     * @return mixed Risultato del callback
     */
    public function trace(mixed $target, callable $callback, ?string $label = null): mixed
    {
        $caller = $this->findCaller();
        $targetClass = $caller['class'] ?? null;
        $targetFunction = $caller['function'] ?? null;
        $targetFile = $caller['file'] ?? null;
        $targetLine = $caller['line'] ?? null;
        $targetLabel = $label;

        // Caso 1: Array [$object, 'methodName'] o [Class::class, 'methodName']
        if (is_array($target) && count($target) === 2 && is_string($target[1])) {
            $cls = is_object($target[0]) ? get_class($target[0]) : (string) $target[0];
            $mth = $target[1];
            $targetClass = $cls;
            $targetFunction = $mth;
            $targetLabel = $label ?: $mth;

            if (class_exists($cls) && method_exists($cls, $mth)) {
                try {
                    $ref = new \ReflectionMethod($cls, $mth);
                    $targetFile = $ref->getFileName();
                    $targetLine = $ref->getStartLine();
                } catch (\Throwable $e) {}
            }
        }
        // Caso 2: Stringa "Class@method" o "Class::method"
        elseif (is_string($target) && (str_contains($target, '@') || str_contains($target, '::'))) {
            $delimiter = str_contains($target, '@') ? '@' : '::';
            [$cls, $mth] = explode($delimiter, $target, 2);
            $targetClass = $cls;
            $targetFunction = $mth;
            $targetLabel = $label ?: $mth;

            if (class_exists($cls) && method_exists($cls, $mth)) {
                try {
                    $ref = new \ReflectionMethod($cls, $mth);
                    $targetFile = $ref->getFileName();
                    $targetLine = $ref->getStartLine();
                } catch (\Throwable $e) {}
            }
        }
        // Caso 3: Stringa semplice (label o nome metodo)
        elseif (is_string($target)) {
            $targetLabel = $target;
            // Se la stringa è un identificatore PHP valido senza spazi e il chiamante ha questo metodo
            if (preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $target)) {
                if ($targetClass && method_exists($targetClass, $target)) {
                    $targetFunction = $target;
                    try {
                        $ref = new \ReflectionMethod($targetClass, $target);
                        $targetFile = $ref->getFileName();
                        $targetLine = $ref->getStartLine();
                    } catch (\Throwable $e) {}
                }
            }
        }

        $start = microtime(true);
        $error = null;

        try {
            $result = $callback();
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            throw $e;
        } finally {
            $this->traces[] = [
                'label' => $targetLabel ?: $targetFunction,
                'file' => $targetFile,
                'line' => $targetLine,
                'class' => $targetClass,
                'function' => $targetFunction,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                'error' => $error,
                'timestamp' => $start,
            ];
        }

        return $result;
    }

    /**
     * Identifica il primo frame dello stack che appartiene al codice chiamante,
     * saltando la Facade di Laravel e le classi interne del package.
     */
    protected function findCaller(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $callSite = null;

        for ($i = 0; $i < count($trace); $i++) {
            $class = $trace[$i]['class'] ?? '';
            if (empty($class) 
                || str_starts_with($class, 'Illuminate\\Support\\Facades')
                || str_starts_with($class, 'SalvatoreCervone\\LogOperations')
            ) {
                if (!empty($trace[$i]['file'])) {
                    $callSite = [
                        'file' => $trace[$i]['file'],
                        'line' => $trace[$i]['line'] ?? null,
                    ];
                }
                continue;
            }

            return [
                'file' => $callSite['file'] ?? ($trace[$i]['file'] ?? null),
                'line' => $callSite['line'] ?? ($trace[$i]['line'] ?? null),
                'class' => $trace[$i]['class'] ?? null,
                'function' => $trace[$i]['function'] ?? null,
            ];
        }

        return $trace[1] ?? [];
    }

    /**
     * Restituisce tutti gli step registrati durante questa richiesta.
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * Restituisce tutti i trace registrati durante questa richiesta.
     */
    public function getTraces(): array
    {
        return $this->traces;
    }

    /**
     * Verifica se ci sono step o trace registrati.
     */
    public function hasCustomTraces(): bool
    {
        return !empty($this->steps) || !empty($this->traces);
    }

    /**
     * Resetta gli step e i trace per la prossima richiesta.
     */
    public function flush(): void
    {
        $this->steps = [];
        $this->traces = [];
    }

    /**
     * Accesso diretto al StackTracer per operazioni avanzate.
     */
    public function getStackTracer(): StackTracer
    {
        return $this->stackTracer;
    }
}
