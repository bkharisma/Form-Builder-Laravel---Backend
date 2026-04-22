<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoginRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request, LoginRecorder $recorder): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'cf_turnstile_response' => 'required|string',
        ]);

        // Verify Turnstile token
        $turnstileService = app(\App\Services\TurnstileService::class);
        if (!$turnstileService->verify($request->input('cf_turnstile_response'), $request->ip())) {
            return response()->json([
                'message' => $turnstileService->getErrorMessage() ?? 'Verification failed. Please try again.',
                'errors' => ['cf_turnstile_response' => [$turnstileService->getErrorMessage() ?? 'Verification failed.']],
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->hasTwoFactorEnabled()) {
            $recorder->record($user, false, 'pending_2fa');

            return response()->json([
                'requires_2fa' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;
        $recorder->record($user, true);

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()]);
    }
}
