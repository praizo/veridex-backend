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
            'email' => ['required', 'email', 'exists:users,email'],
            'role' => ['required', 'in:admin,editor,viewer'],
        ];
    }
}
