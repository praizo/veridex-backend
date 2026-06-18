<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePaymentMeans extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'payment_means_code',
        'payee_financial_account_id',
        'payee_financial_account_name',
        'financial_institution_branch_id',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
