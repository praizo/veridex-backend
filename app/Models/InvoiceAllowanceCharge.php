<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceAllowanceCharge extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'charge_indicator',
        'reason_code',
        'reason_text',
        'multiplier_factor_numeric',
        'amount',
        'base_amount',
        'tax_category_id',
        'tax_percent',
        'tax_scheme_id',
    ];

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
