<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdempotencyRecord extends Model
{
    protected $fillable = [
        'organization_id',
        'key',
        'scope',
        'status_code',
        'response_payload',
        'completed_at',
    ];

    protected $casts = [
        'response_payload' => 'array',
        'completed_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
