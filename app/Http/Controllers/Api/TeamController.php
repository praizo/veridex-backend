<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\AddMemberRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TeamController extends Controller
{
    private function currentOrganizationRole(Request $request): ?string
    {
        $currentOrgId = $request->user()->currentOrganizationId();

        return $request->user()->organizations()
            ->where('organization_id', $currentOrgId)
            ->first()
            ?->pivot
            ?->role;
    }

    private function ensureTargetMember(User $user, int $organizationId): void
    {
        if (! $user->organizations()->where('organization_id', $organizationId)->exists()) {
            abort(404, 'Member not found in this organization');
        }
    }

    public function index(Request $request): JsonResponse
    {
        $orgId = $request->user()->currentOrganizationId();
        $org = Organization::findOrFail($orgId);

        $members = $org->users()->withPivot('role')->get()->map(function ($user) {
            return [
                'id' => $user->uuid,
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

        // Authorization check
        $currentUserRole = $this->currentOrganizationRole($request);

        if (! in_array($currentUserRole, ['owner', 'admin'], true)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Prevent non-owners from inviting owners
        if ($request->role === 'owner' && $currentUserRole !== 'owner') {
            return response()->json(['message' => 'Only the owner can assign the owner role'], 403);
        }

        $newUser = User::where('email', $request->email)->first();
        if (! $newUser) {
            return response()->json(['message' => 'This email is not registered on Veridex.'], 404);
        }

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

    public function update(Request $request, User $member): JsonResponse
    {
        $request->validate([
            'role' => ['required', 'in:admin,editor,viewer,owner'],
        ]);

        $currentOrgId = $request->user()->currentOrganizationId();
        $this->ensureTargetMember($member, $currentOrgId);

        // Authorization check
        $currentUserRole = $this->currentOrganizationRole($request);

        if (! in_array($currentUserRole, ['owner', 'admin'], true)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Protect the owner
        $targetRole = $member->organizations()
            ->where('organization_id', $currentOrgId)
            ->first()
            ?->pivot
            ?->role;

        if ($targetRole === 'owner' && $currentUserRole !== 'owner') {
            return response()->json(['message' => 'Admins cannot modify the organization owner'], 403);
        }

        // Prevent non-owners from promoting someone to owner
        if ($request->role === 'owner' && $currentUserRole !== 'owner') {
            throw ValidationException::withMessages([
                'role' => ['Only the owner can assign the owner role'],
            ]);
        }

        $member->organizations()->updateExistingPivot($currentOrgId, ['role' => $request->role]);

        return response()->json([
            'message' => 'Member role updated successfully',
        ]);
    }

    public function destroy(Request $request, User $member): JsonResponse
    {
        $currentOrgId = $request->user()->currentOrganizationId();
        $this->ensureTargetMember($member, $currentOrgId);

        // Cannot remove oneself
        if ($member->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot remove yourself from the organization'], 422);
        }

        // Authorization check
        $currentUserRole = $this->currentOrganizationRole($request);

        if (! in_array($currentUserRole, ['owner', 'admin'], true)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Protect the owner
        $targetRole = $member->organizations()
            ->where('organization_id', $currentOrgId)
            ->first()
            ->pivot
            ->role;

        if ($targetRole === 'owner' && $currentUserRole !== 'owner') {
            return response()->json(['message' => 'Admins cannot remove the organization owner'], 403);
        }

        $member->organizations()->detach($currentOrgId);

        return response()->json([
            'message' => 'Member removed successfully',
        ]);
    }
}
