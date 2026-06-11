<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Traits\HasUserPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    use HasUserPayload;

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->userPayload($request->user()));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $validated = $request->validated();

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
