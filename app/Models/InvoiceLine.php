<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'line_id',
        'item_type',
        'invoiced_quantity',
        'unit_code',
        'line_extension_amount',
        'item_name',
        'item_description',
        'hs_code',
        'item_category',
        'item_standard_id',
        'price_amount',
        'price_base_quantity',
        'tax_category_id',
        'tax_percent',
        'tax_scheme_id',
        'isic_code',
        'service_category',
    ];

    protected $casts = [
        'invoiced_quantity' => 'decimal:2',
        'line_extension_amount' => 'decimal:2',
        'price_amount' => 'decimal:2',
        'price_base_quantity' => 'decimal:2',
        'tax_percent' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
