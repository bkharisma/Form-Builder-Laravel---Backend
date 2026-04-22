<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'ip_address', 'user_agent', 'login_at', 'success', 'failure_reason'])]
class LoginHistory extends Model
{
    use HasFactory;
    protected $table = 'login_history';
    protected function casts(): array
    {
        return [
            'login_at' => 'datetime',
            'success' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, int $days = 90)
    {
        return $query->where('login_at', '>=', now()->subDays($days));
    }
}
