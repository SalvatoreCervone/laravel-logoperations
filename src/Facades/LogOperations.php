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
 * @method static \SalvatoreCervone\LogOperations\LogOperationsManager withContext(array $context)
 * @method static \SalvatoreCervone\LogOperations\LogOperationsManager addContext(string $key, mixed $value)
 * @method static array getContext()
 * @method static bool hasContext()
 * @method static \SalvatoreCervone\LogOperations\LogOperationsManager tag(string|array ...$tags)
 * @method static array getTags()
 * @method static bool hasTags()
 * @method static \SalvatoreCervone\LogOperations\LogOperationsManager setSubject(?\Illuminate\Database\Eloquent\Model $subject)
 * @method static ?\Illuminate\Database\Eloquent\Model getSubject()
 * @method static void flush()
 * @method static \SalvatoreCervone\LogOperations\Services\StackTracer getStackTracer()
 * @method static \SalvatoreCervone\LogOperations\Services\PrivacyManager getPrivacyManager()
 * @method static int forgetUser(int|string $userId, ?string $userType = null, bool $anonymize = false)
 * @method static ?string anonymizeIp(?string $ip, ?string $mask = null)
 * @method static string sanitizeUri(string $uri, ?array $fields = null)
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
