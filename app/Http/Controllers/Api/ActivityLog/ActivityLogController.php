<?php

namespace App\Http\Controllers\Api\ActivityLog;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of activity logs for the current organization.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewActivityLogs', Organization::findOrFail($request->user()->current_organization_id));

        $logs = ActivityLog::where('organization_id', $request->user()->current_organization_id)
            ->with('user:id,first_name,last_name,email')
            ->latest()
            ->paginate($request->query('per_page', 15));

        return response()->json([
            'data' => ActivityLogResource::collection($logs->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
