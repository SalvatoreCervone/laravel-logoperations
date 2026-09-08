<?php

namespace SalvatoreCervone\LogOperations\Services;

use SalvatoreCervone\LogOperations\Models\OperationLog;

/**
 * Gestore Privacy, Conformità GDPR e Anonimizzazione Dati.
 */
class PrivacyManager
{
    /**
     * Anonimizza un indirizzo IP mascherando l'ultimo ottetto per IPv4
     * o la seconda metà (interfaccia utente) per IPv6.
     *
     * @param string|null $ip Indirizzo IP grezzo
     * @param string|null $mask Carattere o valore di mascheramento (es. 'xxx' o '0')
     * @return string|null
     */
    public function anonymizeIp(?string $ip, ?string $mask = null): ?string
    {
        if (empty($ip)) {
            return null;
        }

        $mask = $mask ?? config('logoperations.privacy.anonymize_ip_mask', 'xxx');

        // Verifica IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return (string) preg_replace('/\.\d+$/', '.' . $mask, $ip);
        }

        // Verifica IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            if (count($parts) >= 4) {
                return implode(':', array_slice($parts, 0, 4)) . ':xxxx:xxxx:xxxx:xxxx';
            }
            return '::xxxx';
        }

        return $ip;
    }

    /**
     * Maschera ricorsivamente i campi sensibili presenti in un array o oggetto.
     */
    public function maskSensitiveData(array $data, ?array $fields = null): array
    {
        $fields = $fields ?? config('logoperations.mask_fields', []);

        if (empty($fields)) {
            return $data;
        }

        $fieldsLower = array_map('strtolower', $fields);

        return $this->recursiveMask($data, $fieldsLower);
    }

    /**
     * Sanitizza un array di header HTTP mascherando o escludendo token e credenziali.
     */
    public function sanitizeHeaders(array $headers, ?array $headersToMask = null): array
    {
        $headersToMask = $headersToMask ?? config('logoperations.privacy.mask_headers', [
            'authorization',
            'cookie',
            'set-cookie',
            'x-xsrf-token',
            'x-csrf-token',
            'php-auth-pw',
            'php-auth-user',
        ]);

        $headersToMaskLower = array_map('strtolower', $headersToMask);
        $sanitized = [];

        foreach ($headers as $name => $values) {
            $nameLower = strtolower($name);
            if (in_array($nameLower, $headersToMaskLower, true)) {
                $sanitized[$name] = is_array($values) ? ['***MASKED***'] : '***MASKED***';
            } else {
                $sanitized[$name] = $values;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitizza una URI mascherando i parametri sensibili presenti nella query string.
     *
     * @param string $uri L'URI o URL da sanitizzare (es. '/api/users?token=secret123&page=1')
     * @param array|null $fields Elenco campi da mascherare (default config 'logoperations.mask_fields')
     * @return string L'URI con i valori dei parametri sensibili mascherati da '***MASKED***'
     */
    public function sanitizeUri(string $uri, ?array $fields = null): string
    {
        $parts = parse_url($uri);
        if ($parts === false || empty($parts['query'])) {
            return $uri;
        }

        parse_str($parts['query'], $queryParams);
        if (empty($queryParams)) {
            return $uri;
        }

        $maskedParams = $this->maskSensitiveData($queryParams, $fields);
        $newQuery = http_build_query($maskedParams);
        $newQuery = str_replace(
            ['%2A%2A%2AMASKED%2A%2A%2A', '%2a%2a%2amaskied%2a%2a%2a', '%2a%2a%2amasked%2a%2a%2a'],
            '***MASKED***',
            $newQuery
        );

        $scheme   = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host     = $parts['host'] ?? '';
        $port     = isset($parts['port']) ? ':' . $parts['port'] : '';
        $user     = $parts['user'] ?? '';
        $pass     = isset($parts['pass']) ? ':' . $parts['pass'] : '';
        $userPass = ($user !== '' || $pass !== '') ? "$user$pass@" : '';
        $path     = $parts['path'] ?? '';
        $query    = $newQuery !== '' ? '?' . $newQuery : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        if ($scheme !== '' || $host !== '') {
            return "$scheme$userPass$host$port$path$query$fragment";
        }

        return "$path$query$fragment";
    }

    /**
     * Esegue il Diritto all'Oblio (GDPR Art. 17).
     *
     * Se $anonymize è false (default), elimina fisicamente tutti i log associati all'utente.
     * Se $anonymize è true, conserva la riga tecnica (rotta, durata, codice HTTP) ma azzera
     * i riferimenti personali (user_id, user_type, client_ip e parametri personali).
     *
     * @param int|string $userId Identificativo utente
     * @param string|null $userType Classe modello utente (es. 'App\Models\User')
     * @param bool $anonymize True per anonimizzare, False per cancellare fisicamente
     * @return int Numero di record coinvolti
     */
    public function forgetUser(int|string $userId, ?string $userType = null, bool $anonymize = false): int
    {
        $query = OperationLog::query()->where('user_id', (string) $userId);

        if ($userType !== null) {
            $query->where('user_type', $userType);
        }

        if (!$anonymize) {
            return $query->delete();
        }

        // Modalità anonimizzazione: conservazione metadati tecnici ma rimozione dati personali (in blocchi da 500 per O(1) RAM)
        $affected = 0;
        $query->chunkById(500, function ($logs) use (&$affected) {
            foreach ($logs as $log) {
                $cleanIp = $this->anonymizeIp($log->client_ip, '0');
                $cleanParams = $this->scrubPersonalDataFromParams($log->parametri);

                $log->update([
                    'user_id'   => null,
                    'user_type' => null,
                    'client_ip' => $cleanIp,
                    'parametri' => $cleanParams,
                ]);

                $affected++;
            }
        });

        return $affected;
    }

    /**
     * Ripulisce ricorsivamente i dati dell'utente dai parametri registrati.
     */
    protected function scrubPersonalDataFromParams($parametri): ?array
    {
        if (empty($parametri) || !is_array($parametri)) {
            return null;
        }

        $piiFields = [
            'email', 'name', 'nome', 'cognome', 'first_name', 'last_name',
            'phone', 'telefono', 'cellulare', 'indirizzo', 'address',
            'codice_fiscale', 'tax_id', 'vat_number', 'partita_iva',
            'iban', 'birth_date', 'data_nascita'
        ];

        return $this->recursiveScrub($parametri, $piiFields);
    }

    protected function recursiveMask(array $data, array $maskedFields): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->recursiveMask($value, $maskedFields);
            } elseif (in_array(strtolower((string) $key), $maskedFields, true)) {
                $data[$key] = '***MASKED***';
            }
        }
        return $data;
    }

    protected function recursiveScrub(array $data, array $piiFields): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->recursiveScrub($value, $piiFields);
            } elseif (in_array(strtolower((string) $key), $piiFields, true)) {
                $data[$key] = '[ANONYMIZED_GDPR]';
            }
        }
        return $data;
    }
}
