<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NrsApiLog extends Model
{
    protected $fillable = [
        'organization_id',
        'irn',
        'endpoint',
        'method',
        'request_payload',
        'response_body',
        'status_code',
        'latency_ms',
        'ip_address',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_body' => 'array',
        'latency_ms' => 'float',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
