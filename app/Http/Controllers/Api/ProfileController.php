<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    private function userPayload(User $user): array
    {
        $user->load('currentOrganization');
        $organizationId = $user->currentOrganizationId();

        $role = $organizationId
            ? $user->organizations()
                ->where('organization_id', $organizationId)
                ->first()
                ?->pivot
                ?->role
            : null;

        return array_merge($user->toArray(), [
            'current_organization_role' => $role,
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->userPayload($request->user()));
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'current_password' => ['nullable', 'required_with:password', 'string'],
            'password' => ['nullable', 'string', PasswordRule::min(8)->letters()->mixedCase()->numbers()->symbols(), 'confirmed'],
        ]);

        $user = $request->user();

        if (! empty($validated['password'])) {
            if (! Hash::check((string) $validated['current_password'], $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['The current password is incorrect.'],
                ]);
            }

            $user->password = Hash::make($validated['password']);
            $user->tokens()->delete();
        }

        $user->name = $validated['name'];
        $user->save();

        return response()->json([
            'message' => ! empty($validated['password'])
                ? 'Profile and password updated successfully.'
                : 'Profile updated successfully.',
            'user' => $this->userPayload($user->fresh()),
        ]);
    }
}
