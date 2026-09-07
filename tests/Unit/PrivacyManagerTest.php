<?php

namespace SalvatoreCervone\LogOperations\Tests\Unit;

use SalvatoreCervone\LogOperations\Services\PrivacyManager;
use SalvatoreCervone\LogOperations\Tests\TestCase;

class PrivacyManagerTest extends TestCase
{
    protected PrivacyManager $privacy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->privacy = new PrivacyManager();
    }

    public function test_anonymize_ipv4_default_mask(): void
    {
        config(['logoperations.privacy.anonymize_ip_mask' => 'xxx']);

        $this->assertEquals('192.168.1.xxx', $this->privacy->anonymizeIp('192.168.1.55'));
        $this->assertEquals('10.0.0.xxx', $this->privacy->anonymizeIp('10.0.0.1'));
        $this->assertEquals('127.0.0.xxx', $this->privacy->anonymizeIp('127.0.0.1'));
    }

    public function test_anonymize_ipv4_custom_mask(): void
    {
        $this->assertEquals('192.168.1.0', $this->privacy->anonymizeIp('192.168.1.55', '0'));
        $this->assertEquals('172.16.4.0', $this->privacy->anonymizeIp('172.16.4.218', '0'));
    }

    public function test_anonymize_ipv6(): void
    {
        $ipv6 = '2001:0db8:85a3:0000:0000:8a2e:0370:7334';
        $anonymized = $this->privacy->anonymizeIp($ipv6);

        $this->assertEquals('2001:0db8:85a3:0000:xxxx:xxxx:xxxx:xxxx', $anonymized);
    }

    public function test_anonymize_null_or_invalid_ip(): void
    {
        $this->assertNull($this->privacy->anonymizeIp(null));
        $this->assertEquals('not-an-ip', $this->privacy->anonymizeIp('not-an-ip'));
    }

    public function test_mask_sensitive_data(): void
    {
        $data = [
            'username' => 'mario.rossi',
            'password' => 'SuperSecret123!',
            'password_confirmation' => 'SuperSecret123!',
            'credit_card' => '1234-5678-9012-3456',
            'nested' => [
                'token' => 'jwt.secret.payload',
                'api_key' => 'sk_live_987654321',
                'public_data' => 'Hello World',
            ],
        ];

        $masked = $this->privacy->maskSensitiveData($data);

        $this->assertEquals('mario.rossi', $masked['username']);
        $this->assertEquals('***MASKED***', $masked['password']);
        $this->assertEquals('***MASKED***', $masked['password_confirmation']);
        $this->assertEquals('***MASKED***', $masked['credit_card']);
        $this->assertEquals('***MASKED***', $masked['nested']['token']);
        $this->assertEquals('***MASKED***', $masked['nested']['api_key']);
        $this->assertEquals('Hello World', $masked['nested']['public_data']);
    }

    public function test_sanitize_headers(): void
    {
        $headers = [
            'host' => ['localhost:8000'],
            'authorization' => ['Bearer secret-jwt-token-here'],
            'cookie' => ['laravel_session=xyz; XSRF-TOKEN=abc'],
            'x-xsrf-token' => ['abc123token'],
            'x-custom-tracking' => ['track-12345'],
            'accept' => ['application/json'],
        ];

        $sanitized = $this->privacy->sanitizeHeaders($headers);

        $this->assertEquals(['localhost:8000'], $sanitized['host']);
        $this->assertEquals(['***MASKED***'], $sanitized['authorization']);
        $this->assertEquals(['***MASKED***'], $sanitized['cookie']);
        $this->assertEquals(['***MASKED***'], $sanitized['x-xsrf-token']);
        $this->assertEquals(['track-12345'], $sanitized['x-custom-tracking']);
        $this->assertEquals(['application/json'], $sanitized['accept']);
    }

    public function test_sanitize_uri_masks_sensitive_query_parameters(): void
    {
        $uri = '/api/v1/checkout?token=secret123&page=1&card_number=1111222233334444';
        $sanitized = $this->privacy->sanitizeUri($uri);

        $this->assertStringContainsString('token=***MASKED***', $sanitized);
        $this->assertStringContainsString('card_number=***MASKED***', $sanitized);
        $this->assertStringContainsString('page=1', $sanitized);
        $this->assertStringNotContainsString('secret123', $sanitized);
        $this->assertStringNotContainsString('1111222233334444', $sanitized);
    }

    public function test_sanitize_uri_leaves_non_sensitive_query_intact(): void
    {
        $uri = '/api/v1/products?category=electronics&sort=asc&page=2';
        $sanitized = $this->privacy->sanitizeUri($uri);

        $this->assertEquals($uri, $sanitized);
    }

    public function test_sanitize_uri_handles_urls_without_query(): void
    {
        $uri = '/api/v1/users';
        $this->assertEquals($uri, $this->privacy->sanitizeUri($uri));

        $empty = '';
        $this->assertEquals('', $this->privacy->sanitizeUri($empty));
    }

    public function test_sanitize_uri_handles_nested_query_params(): void
    {
        $uri = '/api/v1/search?filter[password]=my_secret_pw&filter[query]=books';
        $sanitized = $this->privacy->sanitizeUri($uri);

        $this->assertStringNotContainsString('my_secret_pw', $sanitized);
        $this->assertStringContainsString('***MASKED***', $sanitized);
        $this->assertStringContainsString('books', $sanitized);
    }

    public function test_sanitize_uri_handles_full_urls_with_port_and_fragment(): void
    {
        $url = 'https://example.com:8080/path/test?api_key=sk_live_12345&foo=bar#section1';
        $sanitized = $this->privacy->sanitizeUri($url);

        $this->assertStringStartsWith('https://example.com:8080/path/test?', $sanitized);
        $this->assertStringContainsString('api_key=***MASKED***', $sanitized);
        $this->assertStringContainsString('foo=bar', $sanitized);
        $this->assertStringEndsWith('#section1', $sanitized);
        $this->assertStringNotContainsString('sk_live_12345', $sanitized);
    }
}
