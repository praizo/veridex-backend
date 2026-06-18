<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceDocReference extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'reference_type',
        'document_id',
        'issue_date',
        'document_type_code',
        'document_description',
    ];

    protected $casts = [
        'issue_date' => 'date',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
