<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use PragmaRX\Google2FA\Google2FA;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_enabled_at' => 'datetime',
            'two_factor_recovery_codes' => 'encrypted:array',
        ];
    }

    /**
     * Get the login history records for this user.
     */
    public function loginHistory(): HasMany
    {
        return $this->hasMany(LoginHistory::class);
    }

    /**
     * Check if the user has two-factor authentication enabled.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_enabled_at !== null;
    }

    /**
     * Generate a new TOTP secret and return it.
     */
    public function generateTwoFactorSecret(): string
    {
        $google2fa = new Google2FA();
        return $google2fa->generateSecretKey();
    }

    /**
     * Generate a QR code URL for the given secret.
     */
    public function generateQrCodeUrl(string $secret, ?string $issuer = null): string
    {
        $google2fa = new Google2FA();
        $issuer = $issuer ?? config('app.name', 'Form Builder');
        return $google2fa->getQRCodeUrl(
            $issuer,
            $this->email,
            $secret
        );
    }

    /**
     * Verify a TOTP code against the stored secret.
     */
    public function verifyTwoFactorCode(string $code): bool
    {
        if (!$this->two_factor_secret) {
            return false;
        }

        $google2fa = new Google2FA();
        $secret = Crypt::decryptString($this->two_factor_secret);

        return $google2fa->verifyKey($secret, $code);
    }

    /**
     * Generate backup recovery codes.
     *
     * @param int $count
     * @return array<string>
     */
    public function generateBackupCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = Str::lower(Str::random(8));
        }
        return $codes;
    }

    /**
     * Store encrypted recovery codes on the user.
     *
     * @param array<string> $codes
     */
    public function storeRecoveryCodes(array $codes): void
    {
        $this->two_factor_recovery_codes = $codes;
        $this->save();
    }

    /**
     * Check if a backup code is valid and consume it.
     */
    public function consumeBackupCode(string $code): bool
    {
        $recoveryCodes = $this->two_factor_recovery_codes;

        if (!is_array($recoveryCodes) || empty($recoveryCodes)) {
            return false;
        }

        $index = array_search($code, $recoveryCodes, true);

        if ($index === false) {
            return false;
        }

        unset($recoveryCodes[$index]);
        $this->two_factor_recovery_codes = array_values($recoveryCodes);
        $this->save();

        return true;
    }

    /**
     * Disable two-factor authentication.
     */
    public function disableTwoFactor(): void
    {
        $this->two_factor_secret = null;
        $this->two_factor_enabled_at = null;
        $this->two_factor_recovery_codes = null;
        $this->save();
    }

    /**
     * Store a pending 2FA secret temporarily in cache.
     *
     * @return array{secret: string, backup_codes: array<string>}
     */
    public function storePendingTwoFactor(): array
    {
        $secret = $this->generateTwoFactorSecret();
        $backupCodes = $this->generateBackupCodes();

        Cache::put(
            $this->pendingTwoFactorCacheKey(),
            [
                'secret' => $secret,
                'backup_codes' => $backupCodes,
            ],
            now()->addMinutes(10)
        );

        return [
            'secret' => $secret,
            'backup_codes' => $backupCodes,
        ];
    }

    /**
     * Retrieve the pending 2FA secret from cache.
     *
     * @return array{secret: string, backup_codes: array<string>}|null
     */
    public function getPendingTwoFactor(): ?array
    {
        return Cache::get($this->pendingTwoFactorCacheKey());
    }

    /**
     * Clear the pending 2FA data from cache.
     */
    public function clearPendingTwoFactor(): void
    {
        Cache::forget($this->pendingTwoFactorCacheKey());
    }

    /**
     * Get the cache key for pending 2FA data.
     */
    private function pendingTwoFactorCacheKey(): string
    {
        return "two_factor_pending_{$this->id}";
    }
}
