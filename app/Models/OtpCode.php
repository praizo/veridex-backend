<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    protected $fillable = [
        'email',
        'code',
        'type',
        'payload',
        'attempts',
        'max_attempts',
        'expires_at',
        'verified_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function hasAttemptsRemaining(): bool
    {
        return $this->attempts < $this->max_attempts;
    }

    public function recordFailedAttempt(): void
    {
        $this->increment('attempts');
    }

    public function markConsumed(): void
    {
        $now = now();

        $this->update([
            'verified_at' => $now,
            'consumed_at' => $now,
        ]);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('verified_at')
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now());
    }
}
