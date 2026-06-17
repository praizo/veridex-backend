<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyTinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tin' => ['required', 'string', 'regex:/^\d{8}-\d{4}$/'],
        ];
    }
}
