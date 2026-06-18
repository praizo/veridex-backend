<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->user()?->isSuperAdmin()) {
            return [];
        }

        return [
            'organization_name' => ['required', 'string', 'max:255'],
            'tin' => ['required', 'string', 'max:20'],
            'nrs_business_id' => ['required', 'string'],
            'service_id' => ['required', 'string', 'size:8'],
            'telephone' => ['required', 'string', 'max:50'],
            'street_name' => ['required', 'string', 'max:255'],
            'city_name' => ['required', 'string', 'max:255'],
            'postal_zone' => ['required', 'string', 'max:20'],
            'country_code' => ['required', 'string', 'size:2'],
        ];
    }
}
