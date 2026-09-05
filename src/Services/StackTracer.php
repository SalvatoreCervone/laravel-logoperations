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
     */
    public function captureCurrentStack(): array
    {
        if (!$this->enabled) {
            return [];
        }

        $rawTrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $this->maxFrames + 20);
        $rawTrace = $this->stripInternalFrames($rawTrace);

        return $this->processFrames($rawTrace);
    }

    /**
     * Registra un caller di query DB rilevato durante la richiesta.
     * Viene chiamato dal listener DB::listen() configurato nel middleware.
     */
    public function recordDbCaller(array $callerInfo): void
    {
        if (!$this->traceDbCallers) {
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

        foreach ($this->projectPaths as $projectPath) {
            // Supporto sia percorsi relativi che assoluti
            if (str_contains($filePath, '/' . ltrim($projectPath, '/'))
                || str_contains($filePath, '\\' . ltrim($projectPath, '/'))
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
