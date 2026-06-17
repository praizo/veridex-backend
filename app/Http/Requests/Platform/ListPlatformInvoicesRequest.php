<?php

namespace App\Http\Requests\Platform;

use App\Enums\InvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPlatformInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', Rule::in(array_column(InvoiceStatus::cases(), 'value'))],
            'organization_id' => ['sometimes', 'integer', 'exists:organizations,id'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'sort' => ['sometimes', 'nullable', 'string', 'max:100'],
            'direction' => ['sometimes', 'nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
