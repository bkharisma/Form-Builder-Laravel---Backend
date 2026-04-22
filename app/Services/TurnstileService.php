<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TurnstileService
{
    private ?string $errorMessage = null;

    public function verify(?string $token, ?string $ip = null): bool
    {
        $this->errorMessage = null;

        if (empty($token)) {
            $this->errorMessage = 'Verification is required. Please complete the human verification.';
            return false;
        }

        $secretKey = config('turnstile.secret_key');

        if (empty($secretKey)) {
            Log::error('Turnstile secret key is not configured');
            $this->errorMessage = 'Configuration error. Please contact support.';
            return false;
        }

        try {
            $response = Http::asForm()->post(config('turnstile.verify_url'), [
                'secret' => $secretKey,
                'response' => $token,
                'remoteip' => $ip,
            ]);

            $data = $response->json();

            if ($response->failed() || !isset($data['success'])) {
                Log::error('Turnstile verification failed', ['response' => $data]);
                $this->errorMessage = 'Unable to verify. Please check your connection and try again.';
                return false;
            }

            if ($data['success'] === true) {
                return true;
            }

            if (isset($data['error-codes']) && is_array($data['error-codes'])) {
                $errorCode = $data['error-codes'][0] ?? null;

                $this->errorMessage = match ($errorCode) {
                    'invalid-input-response' => 'Verification failed. Please try again.',
                    'timeout-or-duplicate' => 'Verification failed. Please try again.',
                    'rate-limited' => 'Temporary verification error. Please wait a moment and try again.',
                    default => 'Verification failed. Please try again.',
                };

                Log::warning('Turnstile verification error', [
                    'error_codes' => $data['error-codes'],
                ]);
            } else {
                $this->errorMessage = 'Verification failed. Please try again.';
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Turnstile verification exception', [
                'message' => $e->getMessage(),
            ]);

            $this->errorMessage = 'Unable to verify. Please check your connection and try again.';
            return false;
        }
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}