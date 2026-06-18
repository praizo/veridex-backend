<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rule;

class UpdatePlatformOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'tin' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'telephone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'street_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'postal_zone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'country_code' => ['sometimes', 'nullable', 'string', 'size:2'],
            'business_description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'service_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'nrs_business_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'platform_status' => ['sometimes', Rule::in(['active', 'suspended'])],
            'onboarding_status' => ['sometimes', Rule::in(['pending', 'onboarded', 'review'])],
            'verified' => ['sometimes', 'boolean'],
            'admin_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $allowed = array_keys($this->rules());

            foreach (array_keys($this->all()) as $field) {
                if (! in_array($field, $allowed, true)) {
                    $validator->errors()->add($field, 'This field cannot be updated from the platform console.');
                }
            }
        });
    }
}
