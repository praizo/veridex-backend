<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\AddMemberRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orgId = $request->user()->currentOrganizationId();
        $org = Organization::findOrFail($orgId);

        $members = $org->users()->withPivot('role')->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->pivot->role,
                'joined_at' => $user->pivot->created_at,
            ];
        });

        return response()->json([
            'data' => $members,
        ]);
    }

    public function store(AddMemberRequest $request): JsonResponse
    {
        $orgId = $request->user()->currentOrganizationId();
        $org = Organization::findOrFail($orgId);

        $newUser = User::where('email', $request->email)->firstOrFail();

        // Check if already a member
        if ($org->users()->where('user_id', $newUser->id)->exists()) {
            return response()->json(['message' => 'User is already a member of this organization'], 422);
        }

        $org->users()->attach($newUser->id, ['role' => $request->role]);

        return response()->json([
            'message' => 'Member added successfully',
            'data' => [
                'id' => $newUser->id,
                'name' => $newUser->name,
                'email' => $newUser->email,
                'role' => $request->role,
            ],
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'role' => ['required', 'in:admin,editor,viewer,owner'],
        ]);

        $currentOrgId = $request->user()->currentOrganizationId();

        // Authorization check (simplified)
        $currentUserRole = $request->user()->organizations()
            ->where('organization_id', $currentOrgId)
            ->first()
            ->pivot
            ->role;

        if (! in_array($currentUserRole, ['owner', 'admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user->organizations()->updateExistingPivot($currentOrgId, ['role' => $request->role]);

        return response()->json([
            'message' => 'Member role updated successfully',
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $currentOrgId = $request->user()->currentOrganizationId();

        // Cannot remove oneself
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot remove yourself from the organization'], 422);
        }

        $user->organizations()->detach($currentOrgId);

        return response()->json([
            'message' => 'Member removed successfully',
        ]);
    }
}
