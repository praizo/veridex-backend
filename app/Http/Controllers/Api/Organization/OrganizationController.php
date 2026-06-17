<?php

namespace App\Http\Controllers\Api\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\SwitchOrganizationRequest;
use App\Http\Requests\Organization\UpdateOrganizationRequest;
use App\Services\Organization\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function __construct(
        private readonly OrganizationService $organizationService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->organizationService->current($request->user()));
    }

    public function update(UpdateOrganizationRequest $request): JsonResponse
    {
        $org = $this->organizationService->updateCurrent($request->user(), $request->validated());

        return response()->json([
            'message' => 'Organization updated successfully',
            'data' => $org,
        ]);
    }

    public function switch(SwitchOrganizationRequest $request): JsonResponse
    {
        $user = $this->organizationService->switch($request->user(), $request->organizationId());

        return response()->json([
            'message' => 'Organization switched successfully',
            'user' => $user,
        ]);
    }
}
