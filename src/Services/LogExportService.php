<?php

namespace SalvatoreCervone\LogOperations\Services;

use Illuminate\Database\Eloquent\Builder;

class LogExportService
{
    /**
     * Esporta i record in formato CSV in modalità streaming O(1) memoria.
     *
     * @param Builder $query
     * @param resource $stream
     * @return void
     */
    public function exportCsv(Builder $query, $stream): void
    {
        // UTF-8 BOM per compatibilità con Microsoft Excel
        fwrite($stream, "\xEF\xBB\xBF");

        // Intestazioni delle colonne
        $headers = [
            'ID',
            'Data e Ora',
            'Metodo',
            'URI / Rotta',
            'Status Code',
            'Durata (ms)',
            'Memoria (MB)',
            'Indirizzo IP',
            'Utente ID',
            'Utente',
            'Subject Type',
            'Subject ID',
            'Stato Transazione',
            'Ha Errore',
            'Messaggio Errore',
            'Applicazione',
        ];

        fputcsv($stream, $headers);

        // Streaming con chunk lazily loaded per non saturare la memoria RAM
        $query->lazy(1000)->each(function ($log) use ($stream) {
            $userDesc = '';
            if ($log->user) {
                $userDesc = $log->user->name ?? $log->user->email ?? ('User #' . $log->user_id);
            }

            $date = $log->dataoperazione ?? $log->created_at;
            $dateStr = $date ? (is_string($date) ? $date : $date->toIso8601String()) : '';
            $verb = strtoupper($log->verbo ?? $log->verb ?? '');
            $route = $log->rotta ?? $log->route ?? '';
            $status = $log->codicehttp ?? $log->status_code ?? 0;
            $duration = $log->duration_ms ?? 0;
            $ip = $log->client_ip ?? $log->ip_address ?? '';
            $error = $log->error ?? $log->error_message ?? '';
            $hasError = ($status >= 400 || !empty($error)) ? 'SI' : 'NO';
            $appName = $log->nomeapplicazione ?? $log->app_name ?? '';

            $row = [
                $log->id,
                $dateStr,
                $verb,
                $route,
                $status,
                $duration,
                $log->memory_usage_mb ?? 0,
                $ip,
                $log->user_id,
                $userDesc,
                $log->subject_type,
                $log->subject_id,
                $log->transaction_status ?? 'none',
                $hasError,
                $error,
                $appName,
            ];

            fputcsv($stream, $row);
        });
    }

    /**
     * Esporta i record in formato JSON in modalità streaming O(1) memoria.
     *
     * @param Builder $query
     * @param resource $stream
     * @return void
     */
    public function exportJson(Builder $query, $stream): void
    {
        fwrite($stream, "[\n");

        $isFirst = true;

        $query->lazy(1000)->each(function ($log) use ($stream, &$isFirst) {
            if (!$isFirst) {
                fwrite($stream, ",\n");
            } else {
                $isFirst = false;
            }

            // Normalizza l'oggetto log con i campi principali e le relazioni caricate
            $data = $log->toArray();
            
            fwrite($stream, '  ' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        });

        fwrite($stream, "\n]\n");
    }
}
