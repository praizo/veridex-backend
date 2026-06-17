<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoicePaymentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_status' => ['required', 'string', Rule::in(['PENDING', 'PAID', 'PARTIAL', 'REJECTED'])],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
