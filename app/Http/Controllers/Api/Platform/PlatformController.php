<?php

namespace App\Http\Controllers\Api\Platform;

use App\DTOs\Platform\PlatformAnalyticsFiltersDTO;
use App\DTOs\Platform\PlatformListFiltersDTO;
use App\DTOs\Platform\UpdatePlatformInvoiceDTO;
use App\DTOs\Platform\UpdatePlatformOrganizationDTO;
use App\DTOs\Platform\UpdatePlatformUserDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ListPlatformActivityLogsRequest;
use App\Http\Requests\Platform\ListPlatformInvoicesRequest;
use App\Http\Requests\Platform\ListPlatformOrganizationsRequest;
use App\Http\Requests\Platform\ListPlatformUsersRequest;
use App\Http\Requests\Platform\PlatformAnalyticsRequest;
use App\Http\Requests\Platform\UpdatePlatformInvoiceRequest;
use App\Http\Requests\Platform\UpdatePlatformOrganizationRequest;
use App\Http\Requests\Platform\UpdatePlatformUserRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\User;
use App\Services\Platform\PlatformActivityLogService;
use App\Services\Platform\PlatformAnalyticsService;
use App\Services\Platform\PlatformInvoiceService;
use App\Services\Platform\PlatformOrganizationService;
use App\Services\Platform\PlatformSystemService;
use App\Services\Platform\PlatformUserService;
use Illuminate\Http\JsonResponse;

class PlatformController extends Controller
{
    public function __construct(
        private readonly PlatformAnalyticsService $analyticsService,
        private readonly PlatformOrganizationService $organizationService,
        private readonly PlatformUserService $userService,
        private readonly PlatformInvoiceService $invoiceService,
        private readonly PlatformActivityLogService $activityLogService,
        private readonly PlatformSystemService $systemService,
    ) {}

    public function summary(): JsonResponse
    {
        return response()->json(['data' => $this->analyticsService->summary()]);
    }

    public function analytics(PlatformAnalyticsRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->analyticsService->analytics(PlatformAnalyticsFiltersDTO::fromRequest($request)),
        ]);
    }

    public function organizations(ListPlatformOrganizationsRequest $request): JsonResponse
    {
        return response()->json($this->organizationService->list(PlatformListFiltersDTO::fromRequest($request)));
    }

    public function organization(string $organization): JsonResponse
    {
        return response()->json(['data' => $this->organizationService->show($organization)]);
    }

    public function updateOrganization(UpdatePlatformOrganizationRequest $request, string $organization): JsonResponse
    {
        $record = $this->organizationService->update(
            $request->user(),
            $this->organizationService->find($organization),
            UpdatePlatformOrganizationDTO::fromRequest($request),
        );

        return response()->json(['message' => 'Organization updated successfully', 'data' => $record]);
    }

    public function users(ListPlatformUsersRequest $request): JsonResponse
    {
        return response()->json($this->userService->list(PlatformListFiltersDTO::fromRequest($request)));
    }

    public function user(User $user): JsonResponse
    {
        return response()->json(['data' => $this->userService->show($user)]);
    }

    public function updateUser(UpdatePlatformUserRequest $request, User $user): JsonResponse
    {
        $record = $this->userService->update($request->user(), $user, UpdatePlatformUserDTO::fromRequest($request));

        return response()->json(['message' => 'User updated successfully', 'data' => $record]);
    }

    public function invoices(ListPlatformInvoicesRequest $request): JsonResponse
    {
        return response()->json(
            InvoiceResource::collection($this->invoiceService->list(PlatformListFiltersDTO::fromRequest($request)))
                ->response()
                ->getData(true)
        );
    }

    public function invoice(string $invoice): InvoiceResource
    {
        return new InvoiceResource($this->invoiceService->show($invoice));
    }

    public function updateInvoice(UpdatePlatformInvoiceRequest $request, string $invoice): JsonResponse
    {
        $record = $this->invoiceService->update(
            $request->user(),
            $this->invoiceService->find($invoice),
            UpdatePlatformInvoiceDTO::fromRequest($request),
        );

        return response()->json([
            'message' => 'Invoice updated successfully',
            'data' => (new InvoiceResource($record))->resolve($request),
        ]);
    }

    public function activityLogs(ListPlatformActivityLogsRequest $request): JsonResponse
    {
        return response()->json($this->activityLogService->list(PlatformListFiltersDTO::fromRequest($request)));
    }

    public function system(): JsonResponse
    {
        return response()->json(['data' => $this->systemService->overview()]);
    }
}
