<?php

namespace App\Http\Controllers\Api\Profile;

use App\DTOs\Profile\UpdateProfileDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Services\Profile\ProfileService;
use App\Traits\HasUserPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    use HasUserPayload;

    public function __construct(
        private readonly ProfileService $profileService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->userPayload($request->user()));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $dto = UpdateProfileDTO::fromRequest($request);
        $user = $this->profileService->updateProfile($request->user(), $dto);

        return response()->json([
            'message' => $dto->password
                ? 'Profile and password updated successfully.'
                : 'Profile updated successfully.',
            'user' => $this->userPayload($user),
        ]);
    }
}
