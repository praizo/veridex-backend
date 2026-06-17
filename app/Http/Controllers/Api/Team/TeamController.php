<?php

namespace App\Http\Controllers\Api\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\AddMemberRequest;
use App\Http\Requests\Team\UpdateMemberRoleRequest;
use App\Http\Resources\TeamMemberResource;
use App\Models\Organization;
use App\Models\User;
use App\Services\Team\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function __construct(
        protected TeamService $teamService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $org = Organization::findOrFail($request->user()->currentOrganizationId());

        return response()->json([
            'data' => TeamMemberResource::collection($this->teamService->listMembers($org))->resolve($request),
        ]);
    }

    public function store(AddMemberRequest $request): JsonResponse
    {
        $orgId = $request->user()->currentOrganizationId();
        $org = Organization::findOrFail($orgId);

        $result = $this->teamService->addMember(
            $org,
            $request->email,
            $request->role,
            $request->input('first_name'),
            $request->input('last_name'),
            $request->user(),
            config('app.frontend_url', env('FRONTEND_URL', config('app.url')))
        );

        return response()->json([
            'message' => $result['was_created']
                ? 'Invitation created. The user can complete signup with this email.'
                : 'Member added successfully',
            'data' => (new TeamMemberResource($result['user']->setRelation('pivot', (object) [
                'role' => $request->role,
                'created_at' => now(),
            ])))->resolve($request),
        ], $result['was_created'] ? 201 : 200);
    }

    public function update(UpdateMemberRoleRequest $request, User $member): JsonResponse
    {

        $org = Organization::findOrFail($request->user()->currentOrganizationId());
        $this->teamService->updateRole($org, $member, $request->role, $request->user());

        return response()->json([
            'message' => 'Member role updated successfully',
        ]);
    }

    public function destroy(Request $request, User $member): JsonResponse
    {
        $org = Organization::findOrFail($request->user()->currentOrganizationId());
        $this->teamService->removeMember($org, $member, $request->user());

        return response()->json([
            'message' => 'Member removed successfully',
        ]);
    }
}
