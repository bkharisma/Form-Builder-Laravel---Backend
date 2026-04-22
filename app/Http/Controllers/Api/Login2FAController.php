<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoginRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class Login2FAController extends Controller
{
    public function verify(Request $request, LoginRecorder $recorder): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'code' => 'required|string',
        ]);

        $user = User::findOrFail($request->user_id);

        if (!$user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages([
                'user_id' => ['This user does not have 2FA enabled.'],
            ]);
        }

        $rateLimitKey = "2fa_attempts_{$user->id}";
        $attempts = Cache::get($rateLimitKey, 0);

        if ($attempts >= 10) {
            return response()->json([
                'message' => 'Too many failed 2FA attempts. Please try again later.',
            ], 429);
        }

        $recoveryCodes = $user->two_factor_recovery_codes;
        if (is_array($recoveryCodes) && in_array($request->code, $recoveryCodes, true)) {
            $index = array_search($request->code, $recoveryCodes, true);
            unset($recoveryCodes[$index]);
            $user->two_factor_recovery_codes = array_values($recoveryCodes);
            $user->save();

            $token = $user->createToken('auth-token')->plainTextToken;
            $recorder->record($user, true);
            Cache::forget($rateLimitKey);

            return response()->json([
                'user' => $user,
                'token' => $token,
            ]);
        }

        $google2fa = new Google2FA();
        $secret = Crypt::decryptString($user->two_factor_secret);

        if ($google2fa->verifyKey($secret, $request->code, 1)) {
            $token = $user->createToken('auth-token')->plainTextToken;
            $recorder->record($user, true);
            Cache::forget($rateLimitKey);

            return response()->json([
                'user' => $user,
                'token' => $token,
            ]);
        }

        Cache::put($rateLimitKey, $attempts + 1, now()->addMinutes(5));
        $recorder->record($user, false, 'invalid_2fa');

        throw ValidationException::withMessages([
            'code' => ['The provided 2FA code is invalid.'],
        ]);
    }
}
