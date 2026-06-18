<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NrsSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'user_id',
        'correlation_id',
        'idempotency_key',
        'action',
        'status',
        'request_payload',
        'response_payload',
        'http_status_code',
        'error_code',
        'error_message',
        'attempt_number',
        'response_time_ms',
        'submitted_at',
        'responded_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'submitted_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
