<?php

namespace SalvatoreCervone\LogOperations\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade per il sistema di Log Operazioni.
 *
 * @method static void step(string $label, array $context = [])
 * @method static mixed trace(string $label, callable $callback)
 * @method static array getSteps()
 * @method static array getTraces()
 * @method static array getCustomTraces()
 * @method static bool hasCustomTraces()
 * @method static \SalvatoreCervone\LogOperations\LogOperationsManager setSubject(?\Illuminate\Database\Eloquent\Model $subject)
 * @method static ?\Illuminate\Database\Eloquent\Model getSubject()
 * @method static void flush()
 * @method static \SalvatoreCervone\LogOperations\Services\StackTracer getStackTracer()
 *
 * @see \SalvatoreCervone\LogOperations\LogOperationsManager
 */
class LogOperations extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'logoperations';
    }
}
