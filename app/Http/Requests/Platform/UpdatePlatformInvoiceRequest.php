<?php

namespace App\Http\Requests\Platform;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlatformInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in(array_column(InvoiceStatus::cases(), 'value'))],
            'payment_status' => ['sometimes', Rule::in(array_column(PaymentStatus::cases(), 'value'))],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
