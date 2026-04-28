<?php

namespace App\Http\Controllers\Api;

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
        $logs = ActivityLog::where('organization_id', $request->user()->current_organization_id)
            ->with('user:id,name,email')
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
