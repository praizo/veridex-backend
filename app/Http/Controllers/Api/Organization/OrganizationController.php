<?php

namespace App\Http\Controllers\Api\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\SwitchOrganizationRequest;
use App\Http\Requests\Organization\UpdateOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Services\Organization\OrganizationService;
use App\Traits\HasUserPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    use HasUserPayload;

    public function __construct(
        private readonly OrganizationService $organizationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $organizations = $request->user()
            ->organizations()
            ->get()
            ->map(function ($organization) use ($request) {
                return array_merge(
                    (new OrganizationResource($organization))->resolve($request),
                    ['role' => $organization->pivot?->role],
                );
            })
            ->values();

        return response()->json(['data' => $organizations]);
    }

    public function show(Request $request): JsonResponse
    {
        $organization = $this->organizationService->current($request->user());
        $this->authorize('view', $organization);

        return response()->json(['data' => (new OrganizationResource($organization))->resolve($request)]);
    }

    public function update(UpdateOrganizationRequest $request): JsonResponse
    {
        $org = $this->organizationService->updateCurrent($request->user(), $request->validated());

        return response()->json([
            'message' => 'Organization updated successfully',
            'data' => (new OrganizationResource($org))->resolve($request),
        ]);
    }

    public function switch(SwitchOrganizationRequest $request): JsonResponse
    {
        $user = $this->organizationService->switch($request->user(), $request->organizationId());

        return response()->json([
            'message' => 'Organization switched successfully',
            'user' => $this->userPayload($user),
        ]);
    }
}
