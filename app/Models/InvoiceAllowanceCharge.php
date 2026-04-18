<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceAllowanceCharge extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'charge_indicator' => 'boolean',
        'multiplier_factor_numeric' => 'decimal:4',
        'amount' => 'decimal:2',
        'base_amount' => 'decimal:2',
        'tax_percent' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
