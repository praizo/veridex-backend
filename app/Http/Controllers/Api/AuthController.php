<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $org = Organization::create([
            'name' => $validated['organization_name'],
            'slug' => str()->slug($validated['organization_name']).'-'.str()->random(5),
            'email' => $validated['email'],
            'tin' => $validated['tin'],
            'nrs_business_id' => $validated['nrs_business_id'],
        ]);

        $user->organizations()->attach($org->id, ['role' => 'owner']);
        $user->update(['current_organization_id' => $org->id]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user->load('currentOrganization'),
            'token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user->load('currentOrganization'),
            'token' => $token,
        ]);
    }

    public function logout(): JsonResponse
    {
        request()->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me(): JsonResponse
    {
        return response()->json(request()->user()->load('currentOrganization'));
    }
}
