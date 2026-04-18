<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:business,individual,government'],
            'tin' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'telephone' => ['nullable', 'string'],
            'street_name' => ['nullable', 'string'],
            'city_name' => ['nullable', 'string'],
            'postal_zone' => ['nullable', 'string'],
            'country_code' => ['nullable', 'string', 'size:2'],
        ];
    }
}
