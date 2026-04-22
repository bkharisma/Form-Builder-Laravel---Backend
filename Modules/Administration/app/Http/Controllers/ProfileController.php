<?php

namespace Modules\Administration\Http\Controllers;

use App\Models\AppSetting;
use App\Models\LoginHistory;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'two_factor_enabled' => $user->hasTwoFactorEnabled(),
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ]);

        $user->update($validated);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'two_factor_enabled' => $user->hasTwoFactorEnabled(),
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'min:8', 'confirmed'],
        ]);

        if (!Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update(['password' => $validated['password']]);

        return response()->json(['message' => 'Password updated successfully']);
    }

    public function enableTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return response()->json(['message' => 'Two-factor authentication is already enabled'], 409);
        }

        $pendingData = $user->storePendingTwoFactor();

        $settings = AppSetting::getSettings();
        $issuer = $settings->app_name ?? 'Form Builder';

        $qrCodeUrl = $user->generateQrCodeUrl($pendingData['secret'], $issuer);

        return response()->json([
            'secret' => $pendingData['secret'],
            'qr_code_url' => $qrCodeUrl,
            'backup_codes' => $pendingData['backup_codes'],
        ]);
    }

    public function verifyTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $pendingData = $user->getPendingTwoFactor();

        if (!$pendingData) {
            throw ValidationException::withMessages([
                'code' => ['2FA setup session expired. Please start over.'],
            ]);
        }

        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        $valid = $google2fa->verifyKey($pendingData['secret'], $validated['code']);

        if (!$valid) {
            throw ValidationException::withMessages([
                'code' => ['Invalid code. Please try again.'],
            ]);
        }

        $user->two_factor_secret = Crypt::encryptString($pendingData['secret']);
        $user->two_factor_recovery_codes = $pendingData['backup_codes'];
        $user->two_factor_enabled_at = now();
        $user->save();

        $user->clearPendingTwoFactor();

        LoginHistory::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'login_at' => now(),
            'success' => true,
            'failure_reason' => null,
        ]);

        return response()->json(['message' => 'Two-factor authentication enabled successfully']);
    }

    public function disableTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'password' => ['required'],
        ]);

        if (!Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The password is incorrect.'],
            ]);
        }

        $user->disableTwoFactor();

        return response()->json(['message' => 'Two-factor authentication disabled successfully']);
    }

    public function regenerateCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasTwoFactorEnabled()) {
            return response()->json(['message' => 'Two-factor authentication is not enabled'], 403);
        }

        $newCodes = $user->generateBackupCodes();
        $user->storeRecoveryCodes($newCodes);

        return response()->json(['backup_codes' => $newCodes]);
    }

    public function loginHistory(Request $request): JsonResponse
    {
        $user = $request->user();

        $perPage = min($request->integer('per_page', 10), 50);
        $page = $request->integer('page', 1);

        $history = LoginHistory::forUser($user->id)
            ->orderBy('login_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $history->items(),
            'current_page' => $history->currentPage(),
            'last_page' => $history->lastPage(),
            'per_page' => $history->perPage(),
            'total' => $history->total(),
        ]);
    }
}
