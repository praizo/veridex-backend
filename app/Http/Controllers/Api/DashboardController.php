<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardResource;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    /**
     * Get organization dashboard data.
     */
    public function index(Request $request): DashboardResource
    {
        $orgId = $request->user()->current_organization_id;

        return new DashboardResource([
            'stats' => $this->dashboardService->getStats($orgId),
            'recent_activity' => $this->dashboardService->getRecentActivity($orgId),
        ]);
    }

    /**
     * Check NRS (FIRS) Portal health.
     */
    public function health(): JsonResponse
    {
        return response()->json($this->dashboardService->checkNrsHealth());
    }
}
