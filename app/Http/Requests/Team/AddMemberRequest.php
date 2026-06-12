<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;

class AddMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
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
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'in:admin,editor,viewer'],
        ];
    }
}
