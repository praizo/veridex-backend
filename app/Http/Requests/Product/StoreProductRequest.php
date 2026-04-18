<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'hs_code' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'unit_code' => ['required', 'string', 'max:10'],
            'tax_category' => ['required', 'string', 'max:2'],
            'tax_rate' => ['required', 'numeric', 'min:0'],
        ];
    }
}
