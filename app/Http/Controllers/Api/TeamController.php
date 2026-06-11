<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\AddMemberRequest;
use App\Http\Requests\Team\UpdateMemberRoleRequest;
use App\Models\Organization;
use App\Models\User;
use App\Services\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TeamController extends Controller
{
    public function __construct(
        protected TeamService $teamService
    ) {}

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

    private function frontendUrl(string $path): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', config('app.url'))), '/');

        return $frontendUrl.'/'.ltrim($path, '/');
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
                'status' => $user->email_verified_at ? 'active' : 'invited',
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

        // Check if already a member
        $existingUser = User::where('email', $request->email)->first();
        if ($existingUser && $org->users()->where('user_id', $existingUser->id)->exists()) {
            return response()->json(['message' => 'User is already a member of this organization'], 422);
        }

        $result = $this->teamService->addMember(
            $org,
            $request->email,
            $request->role,
            $request->input('name'),
            $request->user(),
            config('app.frontend_url', env('FRONTEND_URL', config('app.url')))
        );

        return response()->json([
            'message' => $result['was_created']
                ? 'Invitation created. The user can complete signup with this email.'
                : 'Member added successfully',
            'data' => [
                'id' => $result['user']->uuid,
                'name' => $result['user']->name,
                'email' => $result['user']->email,
                'role' => $request->role,
                'joined_at' => now(),
                'status' => $result['user']->email_verified_at ? 'active' : 'invited',
            ],
        ], $result['was_created'] ? 201 : 200);
    }

    public function update(UpdateMemberRoleRequest $request, User $member): JsonResponse
    {

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
