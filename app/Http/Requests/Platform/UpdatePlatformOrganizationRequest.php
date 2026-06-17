<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
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
            'platform_status' => ['sometimes', Rule::in(['active', 'suspended'])],
            'onboarding_status' => ['sometimes', Rule::in(['pending', 'onboarded', 'review'])],
            'verified' => ['sometimes', 'boolean'],
            'admin_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
