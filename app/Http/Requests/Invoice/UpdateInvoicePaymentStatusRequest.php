<?php

namespace App\Http\Requests\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateInvoicePaymentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $invoice instanceof Invoice && Gate::allows('updatePayment', $invoice);
    }

    public function rules(): array
    {
        return [
            'payment_status' => ['required', 'string', Rule::in(['PENDING', 'PAID', 'PARTIAL', 'REJECTED'])],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
