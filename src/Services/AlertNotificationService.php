<?php

namespace SalvatoreCervone\LogOperations\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use SalvatoreCervone\LogOperations\Models\OperationLog;

class AlertNotificationService
{
    /**
     * Invia una notifica di allarme per transazione pendente sottoposta a rollback automatico.
     *
     * @param array $logData
     * @return array
     */
    public function sendRollbackAlert(array $logData): array
    {
        if (!Config::get('logoperations.alerts.enabled', false) ||
            !Config::get('logoperations.alerts.notify_on_rollback', true)) {
            return ['status' => 'skipped', 'reason' => 'alerts_disabled'];
        }

        $title = '🚨 Transazione Pendente Rolled Back';
        $route = $logData['rotta'] ?? $logData['route'] ?? 'N/A';
        $verb = $logData['verbo'] ?? $logData['verb'] ?? 'GET';
        $status = $logData['codicehttp'] ?? $logData['status_code'] ?? 500;
        $appName = $logData['nomeapplicazione'] ?? $logData['app_name'] ?? Config::get('logoperations.app_name', 'laravel');
        $ip = $logData['client_ip'] ?? $logData['ip_address'] ?? 'N/A';

        $message = "È stata rilevata una transazione database lasciata aperta e non chiusa dal codice applicativo durante la richiesta [{$verb} {$route}]. Il middleware di LogOperations ha eseguito un rollback di emergenza per prevenire lock persistenti.";

        $context = [
            'App' => $appName,
            'Rotta' => "{$verb} {$route}",
            'Status HTTP' => (string) $status,
            'IP' => $ip,
            'Utente ID' => (string) ($logData['user_id'] ?? 'Guest'),
            'Durata' => isset($logData['duration_ms']) ? "{$logData['duration_ms']} ms" : 'N/A',
            'Stato Transazione' => $logData['transaction_status'] ?? 'rolled_back',
        ];

        return $this->sendAlert($title, $message, 'critical', $context, 'rollback_' . md5($route));
    }

    /**
     * Controlla se la percentuale di errori nella finestra temporale supera la soglia.
     *
     * @param int|null $windowMinutes
     * @param float|null $thresholdPercentage
     * @param int|null $minRequests
     * @return array
     */
    public function checkErrorRate(?int $windowMinutes = null, ?float $thresholdPercentage = null, ?int $minRequests = null): array
    {
        $windowMinutes = $windowMinutes ?? Config::get('logoperations.alerts.error_rate.window_minutes', 5);
        $thresholdPercentage = $thresholdPercentage ?? Config::get('logoperations.alerts.error_rate.threshold_percentage', 10.0);
        $minRequests = $minRequests ?? Config::get('logoperations.alerts.error_rate.min_requests', 20);

        $since = now()->subMinutes($windowMinutes);

        $totalRequests = OperationLog::where(function ($q) use ($since) {
            $q->where('dataoperazione', '>=', $since)
              ->orWhere('created_at', '>=', $since);
        })->count();

        if ($totalRequests < $minRequests) {
            return [
                'status' => 'ok',
                'total_requests' => $totalRequests,
                'min_requests' => $minRequests,
                'error_count' => 0,
                'error_rate' => 0.0,
                'threshold' => $thresholdPercentage,
                'alert_sent' => false,
                'reason' => 'insufficient_volume',
            ];
        }

        $errorCount = OperationLog::where(function ($q) use ($since) {
            $q->where('dataoperazione', '>=', $since)
              ->orWhere('created_at', '>=', $since);
        })
        ->where(function ($q) {
            $q->where('codicehttp', '>=', 400)
              ->orWhere(function ($sub) {
                  $sub->whereNotNull('error')->where('error', '!=', '');
              });
        })
        ->count();

        $errorRate = round(($errorCount / $totalRequests) * 100, 2);

        $alertSent = false;
        $alertResult = [];

        if ($errorRate >= $thresholdPercentage) {
            $title = "⚠️ Picco Percentuale Errori Rilevato ({$errorRate}%)";
            $message = "Negli ultimi {$windowMinutes} minuti, la percentuale di richieste fallite ha raggiunto il {$errorRate}% ({$errorCount}/{$totalRequests} richieste), superando la soglia configurata del {$thresholdPercentage}%.";

            $context = [
                'Finestra' => "Ultimi {$windowMinutes} minuti",
                'Richieste Totali' => (string) $totalRequests,
                'Errori Rilevati' => (string) $errorCount,
                'Tasso di Errore' => "{$errorRate}%",
                'Soglia Trigger' => "{$thresholdPercentage}%",
            ];

            $alertResult = $this->sendAlert($title, $message, 'warning', $context, 'error_rate_spike');
            $alertSent = true;
        }

        return [
            'status' => $alertSent ? 'alert_triggered' : 'ok',
            'total_requests' => $totalRequests,
            'error_count' => $errorCount,
            'error_rate' => $errorRate,
            'threshold' => $thresholdPercentage,
            'alert_sent' => $alertSent,
            'alert_result' => $alertResult,
        ];
    }

    /**
     * Invia un alert multi-canale (mail, slack, discord, webhook) con anti-flood throttle.
     *
     * @param string $title
     * @param string $message
     * @param string $level (info|warning|danger|critical)
     * @param array $context
     * @param string|null $eventKey
     * @return array
     */
    public function sendAlert(string $title, string $message, string $level = 'danger', array $context = [], ?string $eventKey = null): array
    {
        if (!Config::get('logoperations.alerts.enabled', false)) {
            return ['status' => 'skipped', 'reason' => 'alerts_disabled'];
        }

        $activeChannels = Config::get('logoperations.alerts.channels', ['mail', 'slack', 'discord', 'webhook']);
        $throttleMinutes = Config::get('logoperations.alerts.throttle_minutes', 15);
        $eventKey = $eventKey ?? md5($title);

        $results = [];

        foreach ($activeChannels as $channel) {
            $cacheKey = "logoperations_alert_{$channel}_{$eventKey}";

            if (Cache::has($cacheKey)) {
                $results[$channel] = [
                    'status' => 'throttled',
                    'message' => "Allarme silenziato per anti-flood (limite {$throttleMinutes}m)",
                ];
                continue;
            }

            try {
                $sent = match ($channel) {
                    'mail' => $this->sendMail($title, $message, $level, $context),
                    'slack' => $this->sendSlack($title, $message, $level, $context),
                    'discord' => $this->sendDiscord($title, $message, $level, $context),
                    'webhook' => $this->sendWebhook($title, $message, $level, $context),
                    default => false,
                };

                if ($sent) {
                    Cache::put($cacheKey, true, now()->addMinutes($throttleMinutes));
                    $results[$channel] = ['status' => 'sent'];
                } else {
                    $results[$channel] = ['status' => 'not_configured_or_skipped'];
                }
            } catch (\Throwable $e) {
                Log::error("[LogOperations] Errore invio alert canale {$channel}: " . $e->getMessage());
                $results[$channel] = ['status' => 'error', 'error' => $e->getMessage()];
            }
        }

        return [
            'status' => 'processed',
            'event_key' => $eventKey,
            'channels' => $results,
        ];
    }

    /**
     * Invio email di notifica.
     */
    protected function sendMail(string $title, string $message, string $level, array $context): bool
    {
        $to = Config::get('logoperations.alerts.mail.to');
        if (empty($to)) {
            return false;
        }

        $body = "{$title}\n\n{$message}\n\n";
        if (!empty($context)) {
            $body .= "Dettagli:\n";
            foreach ($context as $k => $v) {
                $body .= "- {$k}: {$v}\n";
            }
        }

        $body .= "\n--\nLogOperations Notification System";

        Mail::raw($body, function ($mail) use ($to, $title) {
            $mail->to($to)->subject("[LogOperations] {$title}");
        });

        return true;
    }

    /**
     * Invio notifica a webhook Slack.
     */
    protected function sendSlack(string $title, string $message, string $level, array $context): bool
    {
        $webhookUrl = Config::get('logoperations.alerts.slack.webhook_url');
        if (empty($webhookUrl)) {
            return false;
        }

        $color = match ($level) {
            'critical', 'danger' => '#e53e3e',
            'warning' => '#dd6b20',
            default => '#3182ce',
        };

        $fields = [];
        foreach ($context as $k => $v) {
            $fields[] = [
                'title' => $k,
                'value' => (string) $v,
                'short' => true,
            ];
        }

        $payload = [
            'text' => "*[LogOperations]* {$title}\n{$message}",
            'attachments' => [
                [
                    'color' => $color,
                    'fields' => $fields,
                    'ts' => time(),
                ],
            ],
        ];

        $response = Http::timeout(5)->post($webhookUrl, $payload);
        return $response->successful();
    }

    /**
     * Invio notifica a webhook Discord.
     */
    protected function sendDiscord(string $title, string $message, string $level, array $context): bool
    {
        $webhookUrl = Config::get('logoperations.alerts.discord.webhook_url');
        if (empty($webhookUrl)) {
            return false;
        }

        $color = match ($level) {
            'critical', 'danger' => 15158332, // Red
            'warning' => 15105570,            // Orange
            default => 3447003,               // Blue
        };

        $fields = [];
        foreach ($context as $k => $v) {
            $fields[] = [
                'name' => $k,
                'value' => (string) $v,
                'inline' => true,
            ];
        }

        $payload = [
            'content' => "**[LogOperations Alert]** {$title}",
            'embeds' => [
                [
                    'title' => $title,
                    'description' => $message,
                    'color' => $color,
                    'fields' => $fields,
                    'timestamp' => now()->toIso8601String(),
                ],
            ],
        ];

        $response = Http::timeout(5)->post($webhookUrl, $payload);
        return $response->successful();
    }

    /**
     * Invio payload a Webhook generico con firma HMAC facoltativa.
     */
    protected function sendWebhook(string $title, string $message, string $level, array $context): bool
    {
        $webhookUrl = Config::get('logoperations.alerts.webhook.url');
        if (empty($webhookUrl)) {
            return false;
        }

        $payload = [
            'event' => 'logoperations.alert',
            'title' => $title,
            'message' => $message,
            'level' => $level,
            'context' => $context,
            'timestamp' => now()->toIso8601String(),
        ];

        $client = Http::timeout(5);

        $secret = Config::get('logoperations.alerts.webhook.secret');
        if (!empty($secret)) {
            $signature = hash_hmac('sha256', json_encode($payload), $secret);
            $client = $client->withHeaders([
                'X-LogOperations-Signature' => $signature,
            ]);
        }

        $response = $client->post($webhookUrl, $payload);
        return $response->successful();
    }
}
