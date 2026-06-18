<?php

namespace App\Http\Requests\Organization;

use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = Organization::find($this->user()?->currentOrganizationId());

        return $organization && Gate::allows('update', $organization);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'tin' => ['required', 'string', 'max:50'],
            'nrs_business_id' => ['nullable', 'string', 'uuid'],
            'email' => ['nullable', 'email', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'street_name' => ['nullable', 'string', 'max:255'],
            'city_name' => ['nullable', 'string', 'max:255'],
            'postal_zone' => ['nullable', 'string', 'max:20'],
            'country_code' => ['required', 'string', 'size:2'],
            'business_description' => ['nullable', 'string', 'max:1000'],
            'service_id' => ['nullable', 'string', 'size:8'],
        ];
    }
}
