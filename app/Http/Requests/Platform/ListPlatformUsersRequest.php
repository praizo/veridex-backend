<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPlatformUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'suspended', 'unverified'])],
            'organization_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'sort' => ['sometimes', 'nullable', 'string', 'max:100'],
            'direction' => ['sometimes', 'nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
