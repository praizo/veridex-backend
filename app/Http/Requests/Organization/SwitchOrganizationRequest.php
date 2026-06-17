<?php

namespace App\Http\Requests\Organization;

use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;

class SwitchOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('organization_id') && is_string($this->organization_id) && ! ctype_digit($this->organization_id)) {
            $org = Organization::where('uuid', $this->organization_id)->first();
            if ($org) {
                $this->merge(['organization_id' => $org->id]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
        ];
    }

    public function organizationId(): int
    {
        return (int) $this->validated('organization_id');
    }
}
