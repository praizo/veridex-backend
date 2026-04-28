<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\UpdateOrganizationRequest;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $orgId = $request->attributes->get('organization_id');
        $org = Organization::findOrFail($orgId);

        return response()->json($org);
    }

    public function update(UpdateOrganizationRequest $request): JsonResponse
    {
        $orgId = $request->user()->currentOrganizationId();
        $org = Organization::findOrFail($orgId);

        $org->update($request->validated());

        return response()->json([
            'message' => 'Organization updated successfully',
            'data' => $org,
        ]);
    }

    public function switch(Request $request): JsonResponse
    {
        $request->validate([
            'organization_id' => ['required', 'exists:organizations,id'],
        ]);

        $user = $request->user();
        $orgId = $request->organization_id;

        // Verify user belongs to this organization
        if (! $user->organizations()->where('organization_id', $orgId)->exists()) {
            return response()->json(['message' => 'Unauthorized access to organization'], 403);
        }

        $user->update(['current_organization_id' => $orgId]);

        return response()->json([
            'message' => 'Organization switched successfully',
            'user' => $user->load('currentOrganization'),
        ]);
    }
}
