<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceTaxTotal extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'tax_amount',
        'taxable_amount',
        'tax_category_id',
        'tax_percent',
        'tax_scheme_id',
    ];

    protected $casts = [
        'tax_amount' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'tax_percent' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
