<?php

namespace App\Services;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\Request;

class LoginRecorder
{
    public function __construct(
        private readonly Request $request
    ) {}

    public function record(User $user, bool $success, ?string $reason = null): void
    {
        LoginHistory::create([
            'user_id' => $user->id,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'login_at' => now(),
            'success' => $success,
            'failure_reason' => $reason,
        ]);
    }
}
