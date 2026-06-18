<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPlatformActivityLogsRequest extends FormRequest
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
            'organization_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'user_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'sort' => ['sometimes', 'nullable', 'string', 'max:100'],
            'direction' => ['sometimes', 'nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
