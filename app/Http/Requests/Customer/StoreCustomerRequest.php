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
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'in:business,individual,government'],
            'tin' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string'],
            'postal_zone' => ['nullable', 'string', 'max:20'],
            'country_code' => ['nullable', 'string', 'size:2'],
        ];
    }

    /**
     * Map frontend field names to canonical DB column names.
     */
    public function toServiceData(): array
    {
        $v = $this->validated();

        return [
            'first_name' => $v['first_name'],
            'last_name' => $v['last_name'],
            'type' => $v['type'] ?? 'business',
            'tin' => $v['tin'],
            'email' => $v['email'],
            'telephone' => $v['phone'] ?? null,
            'street_name' => $v['address'] ?? null,
            'city_name' => $v['city'] ?? null,
            'postal_zone' => $v['postal_zone'] ?? null,
            'country_code' => $v['country_code'] ?? 'NG',
        ];
    }
}
