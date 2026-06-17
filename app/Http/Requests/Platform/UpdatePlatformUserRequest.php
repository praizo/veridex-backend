<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlatformUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platform_role' => ['sometimes', 'nullable', Rule::in(['super_admin'])],
            'suspended' => ['sometimes', 'boolean'],
            'email_verified' => ['sometimes', 'boolean'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
