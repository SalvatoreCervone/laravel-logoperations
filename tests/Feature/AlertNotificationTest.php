<?php

namespace SalvatoreCervone\LogOperations\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use SalvatoreCervone\LogOperations\Models\OperationLog;
use SalvatoreCervone\LogOperations\Services\AlertNotificationService;
use SalvatoreCervone\LogOperations\Tests\TestCase;

class AlertNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_alerts_are_skipped_when_disabled(): void
    {
        Config::set('logoperations.alerts.enabled', false);

        $service = app(AlertNotificationService::class);
        $result = $service->sendAlert('Test Title', 'Test Message');

        $this->assertEquals('skipped', $result['status']);
        $this->assertEquals('alerts_disabled', $result['reason']);
    }

    public function test_mail_alert_dispatched_when_configured(): void
    {
        Mail::fake();

        Config::set('logoperations.alerts.enabled', true);
        Config::set('logoperations.alerts.channels', ['mail']);
        Config::set('logoperations.alerts.mail.to', 'admin@example.com');

        $service = app(AlertNotificationService::class);
        $result = $service->sendAlert('Critical DB Issue', 'Database connection lost', 'critical', ['Host' => 'db.example.com']);

        $this->assertEquals('processed', $result['status']);
        $this->assertEquals('sent', $result['channels']['mail']['status']);

        Mail::assertSent(\Illuminate\Mail\Mailable::class, 0); // Sent via Mail::raw
    }

    public function test_slack_alert_dispatched_via_http_post(): void
    {
        Http::fake([
            'https://hooks.slack.com/*' => Http::response(['ok' => true], 200),
        ]);

        Config::set('logoperations.alerts.enabled', true);
        Config::set('logoperations.alerts.channels', ['slack']);
        Config::set('logoperations.alerts.slack.webhook_url', 'https://hooks.slack.com/services/test/webhook');

        $service = app(AlertNotificationService::class);
        $result = $service->sendAlert('Slow Query Spike', 'Average duration exceeded 2000ms', 'warning');

        $this->assertEquals('sent', $result['channels']['slack']['status']);

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://hooks.slack.com/')
                && str_contains($request['text'], 'Slow Query Spike');
        });
    }

    public function test_discord_alert_dispatched_via_http_post(): void
    {
        Http::fake([
            'https://discord.com/api/webhooks/*' => Http::response(['id' => '123'], 204),
        ]);

        Config::set('logoperations.alerts.enabled', true);
        Config::set('logoperations.alerts.channels', ['discord']);
        Config::set('logoperations.alerts.discord.webhook_url', 'https://discord.com/api/webhooks/test/123');

        $service = app(AlertNotificationService::class);
        $result = $service->sendAlert('Transaction Rolled Back', 'Dangling transaction detected', 'danger');

        $this->assertEquals('sent', $result['channels']['discord']['status']);

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://discord.com/api/webhooks/')
                && str_contains($request['content'], 'Transaction Rolled Back');
        });
    }

    public function test_generic_webhook_alert_dispatched_with_hmac_signature(): void
    {
        Http::fake([
            'https://alerts.mycompany.com/webhook' => Http::response(['success' => true], 200),
        ]);

        Config::set('logoperations.alerts.enabled', true);
        Config::set('logoperations.alerts.channels', ['webhook']);
        Config::set('logoperations.alerts.webhook.url', 'https://alerts.mycompany.com/webhook');
        Config::set('logoperations.alerts.webhook.secret', 'my_secret_token');

        $service = app(AlertNotificationService::class);
        $result = $service->sendAlert('Queue Failure', 'Worker failed', 'danger');

        $this->assertEquals('sent', $result['channels']['webhook']['status']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://alerts.mycompany.com/webhook'
                && $request->hasHeader('X-LogOperations-Signature')
                && $request['event'] === 'logoperations.alert';
        });
    }

    public function test_anti_flood_throttle_prevents_duplicate_notifications(): void
    {
        Http::fake([
            'https://hooks.slack.com/*' => Http::response(['ok' => true], 200),
        ]);

        Config::set('logoperations.alerts.enabled', true);
        Config::set('logoperations.alerts.channels', ['slack']);
        Config::set('logoperations.alerts.slack.webhook_url', 'https://hooks.slack.com/services/test/webhook');
        Config::set('logoperations.alerts.throttle_minutes', 15);

        $service = app(AlertNotificationService::class);

        // Prima chiamata: inviata con successo
        $first = $service->sendAlert('Frequent Error', 'Repeated message', 'danger', [], 'same_event_key');
        $this->assertEquals('sent', $first['channels']['slack']['status']);

        // Seconda chiamata immediata con la stessa chiave: silenziata per throttle
        $second = $service->sendAlert('Frequent Error', 'Repeated message', 'danger', [], 'same_event_key');
        $this->assertEquals('throttled', $second['channels']['slack']['status']);

        // Slack HTTP request inviata una sola volta
        Http::assertSentCount(1);
    }

    public function test_check_error_rate_spike_detection(): void
    {
        Http::fake([
            'https://hooks.slack.com/*' => Http::response(['ok' => true], 200),
        ]);

        Config::set('logoperations.alerts.enabled', true);
        Config::set('logoperations.alerts.channels', ['slack']);
        Config::set('logoperations.alerts.slack.webhook_url', 'https://hooks.slack.com/services/test/webhook');

        $now = now();
        // 20 richieste totali: 15 ok, 5 in errore (25% di errore > soglia 10%)
        for ($i = 1; $i <= 15; $i++) {
            OperationLog::create([
                'rotta' => '/api/test/' . $i,
                'verbo' => 'GET',
                'codicehttp' => 200,
                'client_ip' => '127.0.0.1',
                'dataoperazione' => $now,
            ]);
        }
        for ($i = 1; $i <= 5; $i++) {
            OperationLog::create([
                'rotta' => '/api/error/' . $i,
                'verbo' => 'GET',
                'codicehttp' => 500,
                'error' => 'Error occurred',
                'client_ip' => '127.0.0.1',
                'dataoperazione' => $now,
            ]);
        }

        $service = app(AlertNotificationService::class);
        $result = $service->checkErrorRate(windowMinutes: 5, thresholdPercentage: 10.0, minRequests: 20);

        $this->assertEquals('alert_triggered', $result['status']);
        $this->assertTrue($result['alert_sent']);
        $this->assertEquals(25.0, $result['error_rate']);
        $this->assertEquals(20, $result['total_requests']);
        $this->assertEquals(5, $result['error_count']);
    }

    public function test_check_alerts_artisan_command(): void
    {
        $now = now();
        for ($i = 1; $i <= 25; $i++) {
            OperationLog::create([
                'rotta' => '/api/test/' . $i,
                'verbo' => 'GET',
                'codicehttp' => 200,
                'client_ip' => '127.0.0.1',
                'dataoperazione' => $now,
            ]);
        }

        // Tasso di errore 0% -> successo
        $this->artisan('logoperations:check-alerts --window=5 --threshold=10 --min-requests=20')
            ->expectsOutputToContain('Tasso di errore nei parametri di norma')
            ->assertExitCode(0);
    }

    public function test_middleware_triggers_alert_on_dangling_transaction_rollback(): void
    {
        Http::fake([
            'https://hooks.slack.com/*' => Http::response(['ok' => true], 200),
        ]);

        Config::set('logoperations.alerts.enabled', true);
        Config::set('logoperations.alerts.notify_on_rollback', true);
        Config::set('logoperations.alerts.channels', ['slack']);
        Config::set('logoperations.alerts.slack.webhook_url', 'https://hooks.slack.com/services/test/webhook');
        Config::set('logoperations.write_after_response', false);

        Route::middleware(['web', 'log.operations'])->get('/test-dangling-tx', function () {
            DB::beginTransaction(); // Transazione aperta e non chiusa!
            return response('OK with dangling tx', 200);
        });

        $this->get('/test-dangling-tx');

        // Verifica che lo Slack webhook sia stato chiamato per l'allarme transazione
        Http::assertSent(function ($request) {
            return str_contains($request['text'], 'Transazione Pendente Rolled Back');
        });
    }
}
