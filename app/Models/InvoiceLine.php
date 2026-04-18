<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

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
