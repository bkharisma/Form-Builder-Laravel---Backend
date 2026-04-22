<?php

namespace Tests\Unit;

use App\Services\TurnstileService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TurnstileServiceTest extends TestCase
{
    private TurnstileService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['turnstile.secret_key' => 'test-secret-key']);
        config(['turnstile.verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify']);
        $this->service = new TurnstileService();
    }

    public function test_verify_returns_false_with_missing_token(): void
    {
        $result = $this->service->verify('');

        $this->assertFalse($result);
        $this->assertEquals(
            'Verification is required. Please complete the human verification.',
            $this->service->getErrorMessage()
        );
    }

    public function test_verify_returns_true_with_valid_token(): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
        ]);

        $result = $this->service->verify('valid-token');

        $this->assertTrue($result);
        $this->assertNull($this->service->getErrorMessage());
    }

    public function test_verify_returns_false_with_invalid_token(): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response([
                'success' => false,
                'error-codes' => ['invalid-input-response'],
            ], 200),
        ]);

        $result = $this->service->verify('invalid-token');

        $this->assertFalse($result);
        $this->assertEquals(
            'Verification failed. Please try again.',
            $this->service->getErrorMessage()
        );
    }

    public function test_verify_handles_timeout_error(): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response([
                'success' => false,
                'error-codes' => ['timeout-or-duplicate'],
            ], 200),
        ]);

        $result = $this->service->verify('expired-token');

        $this->assertFalse($result);
        $this->assertEquals(
            'Verification failed. Please try again.',
            $this->service->getErrorMessage()
        );
    }

    public function test_verify_handles_rate_limit(): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response([
                'success' => false,
                'error-codes' => ['rate-limited'],
            ], 200),
        ]);

        $result = $this->service->verify('rate-limited-token');

        $this->assertFalse($result);
        $this->assertEquals(
            'Temporary verification error. Please wait a moment and try again.',
            $this->service->getErrorMessage()
        );
    }

    public function test_verify_handles_network_error(): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response([], 500),
        ]);

        $result = $this->service->verify('network-error-token');

        $this->assertFalse($result);
        $this->assertEquals(
            'Unable to verify. Please check your connection and try again.',
            $this->service->getErrorMessage()
        );
    }

    public function test_verify_handles_exception(): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::failedConnection('Connection refused'),
        ]);

        $result = $this->service->verify('exception-token');

        $this->assertFalse($result);
        $this->assertEquals(
            'Unable to verify. Please check your connection and try again.',
            $this->service->getErrorMessage()
        );
    }

    public function test_verify_handles_unknown_error(): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response([
                'success' => false,
                'error-codes' => ['unknown-error'],
            ], 200),
        ]);

        $result = $this->service->verify('unknown-error-token');

        $this->assertFalse($result);
        $this->assertEquals(
            'Verification failed. Please try again.',
            $this->service->getErrorMessage()
        );
    }

    public function test_verify_passes_ip_address(): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => function ($request) {
                $body = $request->body();
                parse_str($body, $params);
                $this->assertEquals('192.168.1.1', $params['remoteip']);

                return Http::response(['success' => true], 200);
            },
        ]);

        $result = $this->service->verify('valid-token', '192.168.1.1');

        $this->assertTrue($result);
    }
}