<?php

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only owners or admins can update organization settings
        $user = $this->user();
        $orgId = $user->currentOrganizationId();
        
        $role = $user->organizations()
            ->where('organization_id', $orgId)
            ->first()
            ->pivot
            ->role;

        return in_array($role, ['owner', 'admin']);
    }

    public function rules(): array
    {
        return [
            'name'                 => ['required', 'string', 'max:255'],
            'tin'                  => ['required', 'string', 'max:50'],
            'email'                => ['nullable', 'email', 'max:255'],
            'telephone'            => ['nullable', 'string', 'max:50'],
            'street_name'          => ['nullable', 'string', 'max:255'],
            'city_name'            => ['nullable', 'string', 'max:255'],
            'postal_zone'          => ['nullable', 'string', 'max:20'],
            'country_code'         => ['required', 'string', 'size:2'],
            'business_description' => ['nullable', 'string', 'max:1000'],
            'service_id'           => ['nullable', 'string', 'size:8'],
        ];
    }
}
