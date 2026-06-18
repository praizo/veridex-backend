<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use BelongsToOrganization, HasFactory, \Illuminate\Database\Eloquent\Concerns\HasUuids;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

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
        'seller_snapshot' => 'array',
        'buyer_snapshot' => 'array',
        'line_snapshot' => 'array',
        'tax_snapshot' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($invoice) {
            $organization = $invoice->organization ?? Organization::find($invoice->organization_id);
            if ($organization && $invoice->invoice_number && $invoice->issue_date) {
                // NRS rules: all uppercase, no spaces, only '-' special character allowed
                $serviceId = $organization->service_id ?? config('nrs.default_service_id', '0D2153BF');
                $tinBranch = $organization->tin
                    ? "{$organization->tin}-0001"
                    : '99999999-0001';
                $dateStamp = $invoice->issue_date->format('Ymd');
                $irn = "{$tinBranch}-{$invoice->invoice_number}-{$serviceId}-{$dateStamp}";
                $invoice->irn = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', $irn));
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

    public function captureImmutableSnapshot(): void
    {
        if ($this->seller_snapshot || $this->buyer_snapshot || $this->line_snapshot || $this->tax_snapshot) {
            return;
        }

        $this->loadMissing(['organization', 'customer', 'lines', 'taxTotals']);

        $this->forceFill([
            'seller_snapshot' => $this->organization ? [
                'id' => $this->organization->id,
                'name' => $this->organization->name,
                'tin' => $this->organization->tin,
                'email' => $this->organization->email,
                'service_id' => $this->organization->service_id,
                'nrs_business_id' => $this->organization->nrs_business_id,
                'street_name' => $this->organization->street_name,
                'city_name' => $this->organization->city_name,
                'country_subentity' => $this->organization->country_subentity,
                'country_code' => $this->organization->country_code,
            ] : null,
            'buyer_snapshot' => $this->customer ? [
                'id' => $this->customer->id,
                'uuid' => $this->customer->uuid,
                'name' => $this->customer->name,
                'type' => $this->customer->type,
                'tin' => $this->customer->tin,
                'email' => $this->customer->email,
                'telephone' => $this->customer->telephone,
                'street_name' => $this->customer->street_name,
                'building_number' => $this->customer->building_number,
                'city_name' => $this->customer->city_name,
                'country_subentity' => $this->customer->country_subentity,
                'postal_zone' => $this->customer->postal_zone,
                'country_code' => $this->customer->country_code,
            ] : null,
            'line_snapshot' => $this->lines->map(fn ($line) => $line->only([
                'line_id',
                'invoiced_quantity',
                'line_extension_amount',
                'item_name',
                'item_description',
                'hsn_code',
                'product_category',
                'item_standard_id',
                'price_amount',
                'price_base_quantity',
                'unit_code',
                'tax_category_id',
                'tax_percent',
                'tax_scheme_id',
            ]))->values()->all(),
            'tax_snapshot' => $this->taxTotals->map(fn ($taxTotal) => $taxTotal->only([
                'tax_amount',
                'taxable_amount',
                'tax_category_id',
                'tax_percent',
                'tax_scheme_id',
            ]))->values()->all(),
        ])->save();
    }

    /**
     * Scope route model binding to the user's active organization.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where($field ?? 'uuid', $value)
            ->where('organization_id', auth()->user()->current_organization_id)
            ->firstOrFail();
    }
}
