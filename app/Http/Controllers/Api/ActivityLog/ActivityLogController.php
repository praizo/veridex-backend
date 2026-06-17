<?php

namespace App\Http\Controllers\Api\ActivityLog;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of activity logs for the current organization.
     */
    public function index(Request $request): JsonResponse
    {
        $role = $request->user()
            ->organizations()
            ->where('organizations.id', $request->user()->current_organization_id)
            ->first()
            ?->pivot
            ?->role;

        if (! in_array($role, ['owner', 'admin', 'accountant'], true)) {
            return response()->json(['message' => 'You are not authorized to view activity logs.'], 403);
        }

        $logs = ActivityLog::where('organization_id', $request->user()->current_organization_id)
            ->with('user:id,first_name,last_name,email')
            ->latest()
            ->paginate($request->query('per_page', 15));

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
