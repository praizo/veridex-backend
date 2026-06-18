<?php

namespace App\Http\Requests\Report;

use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class InvoiceSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = Organization::find($this->user()?->currentOrganizationId());

        return $organization && Gate::allows('viewReports', $organization);
    }

    public function rules(): array
    {
        return [
            'customer_type' => ['nullable', 'string', 'in:all,business,individual,government'],
            'status' => ['nullable', 'string'],
            'payment_status' => ['nullable', 'string'],
            'tax_category_id' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }

    /**
     * Get validated filters with defaults applied.
     */
    public function filters(): array
    {
        $filters = $this->validated();
        $filters['customer_type'] ??= 'business';

        return $filters;
    }
}
