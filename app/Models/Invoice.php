<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;

class Invoice extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'tax_point_date' => 'date',
        'actual_delivery_date' => 'date',
        'delivery_period_start' => 'date',
        'delivery_period_end' => 'date',
        'status' => InvoiceStatus::class,
        'payment_status' => PaymentStatus::class,
    ];

    protected static function booted()
    {
        static::creating(function ($invoice) {
            $organization = $invoice->organization ?? Organization::find($invoice->organization_id);
            if ($organization && $invoice->invoice_number && $invoice->issue_date) {
                $serviceId = $organization->service_id ?? '00000000';
                $dateStamp = $invoice->issue_date->format('Ymd');
                $invoice->irn = "{$invoice->invoice_number}-{$serviceId}-{$dateStamp}";
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function taxTotals(): HasMany
    {
        return $this->hasMany(InvoiceTaxTotal::class);
    }

    public function allowanceCharges(): HasMany
    {
        return $this->hasMany(InvoiceAllowanceCharge::class);
    }

    public function paymentMeans(): HasMany
    {
        return $this->hasMany(InvoicePaymentMeans::class);
    }

    public function docReferences(): HasMany
    {
        return $this->hasMany(InvoiceDocReference::class);
    }

    public function stateTransitions(): HasMany
    {
        return $this->hasMany(InvoiceStateTransition::class);
    }

    public function nrsSubmissions(): HasMany
    {
        return $this->hasMany(NrsSubmission::class);
    }

    /**
     * Scope route model binding to the user's active organization.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where($field ?? 'id', $value)
            ->where('organization_id', auth()->user()->current_organization_id)
            ->firstOrFail();
    }
}
