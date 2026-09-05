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
     * Esegue un callable tracciandone l'esecuzione e la durata.
     *
     * @template T
     * @param string $label Etichetta descrittiva dell'operazione
     * @param callable(): T $callback Funzione da eseguire
     * @return T Il valore di ritorno del callable
     */
    public function trace(string $label, callable $callback): mixed
    {
        $caller = $this->findCaller();
        $start = microtime(true);
        $error = null;

        try {
            $result = $callback();
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            throw $e;
        } finally {
            $this->traces[] = [
                'label' => $label,
                'file' => $caller['file'] ?? null,
                'line' => $caller['line'] ?? null,
                'class' => $caller['class'] ?? null,
                'function' => $caller['function'] ?? null,
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
