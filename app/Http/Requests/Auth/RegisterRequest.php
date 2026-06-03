<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'organization_name' => ['required', 'string', 'max:255'],
            'tin' => ['required', 'string', 'max:20'],
            'nrs_business_id' => ['required', 'string'], // Usually a UUID from FIRS
            'service_id' => ['required', 'string', 'size:8'],
            'telephone' => ['required', 'string', 'max:50'],
            'street_name' => ['required', 'string', 'max:255'],
            'city_name' => ['required', 'string', 'max:255'],
            'postal_zone' => ['required', 'string', 'max:20'],
            'country_code' => ['required', 'string', 'size:2'],
        ];
    }
}
