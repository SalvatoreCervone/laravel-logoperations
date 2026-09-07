<?php

namespace SalvatoreCervone\LogOperations\Contracts;

use SalvatoreCervone\LogOperations\Services\RuleEngine;

/**
 * Interfaccia implementata da tutti i proxy generati dinamicamente.
 */
interface LogOperationsProxy
{
    /**
     * Inizializza il proxy associando l'istanza reale originaria e le impostazioni di tracciamento.
     */
    public function __initLogOperationsProxy(object $target, string $className, array $monitoredMethods, ?RuleEngine $engine = null): void;

    /**
     * Restituisce l'istanza originale target incapsulata nel proxy.
     */
    public function __getLogOperationsTarget(): object;

    /**
     * Restituisce l'elenco dei metodi monitorati dal proxy.
     */
    public function __getLogOperationsMonitoredMethods(): array;
}
