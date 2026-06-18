<?php

namespace App\Http\Controllers\Api\Invoice;

use App\DTOs\Invoice\CreateInvoiceDTO;
use App\DTOs\Invoice\UpdateInvoicePaymentStatusDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoicePaymentStatusRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\ActivityLog\ActivityLogService;
use App\Services\Invoice\IdempotencyService;
use App\Services\Invoice\InvoicePaymentService;
use App\Services\Invoice\InvoicePdfService;
use App\Services\Invoice\InvoiceService;
use App\Services\Nrs\NrsArtifactService;
use App\Services\Nrs\NrsInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceService $invoiceService,
        protected NrsInvoiceService $nrsService,
        protected NrsArtifactService $artifactService,
        protected InvoicePdfService $pdfService,
        protected IdempotencyService $idempotency,
        protected InvoicePaymentService $paymentService,
        protected ActivityLogService $activityLog,
    ) {}

    /**
     * Display a listing of invoices.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $invoices = Invoice::where('organization_id', $request->user()->current_organization_id)
            ->with(['customer', 'lines'])
            ->latest()
            ->paginate($request->query('per_page', 15));

        return response()->json(InvoiceResource::collection($invoices)->response()->getData(true));
    }

    /**
     * Store a newly created invoice in storage.
     */
    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        if ($response = $this->idempotentReplay($request, 'invoice:create')) {
            return $response;
        }

        $result = DB::transaction(function () use ($request) {
            $dto = CreateInvoiceDTO::fromRequest($request);
            $invoice = $this->invoiceService->createInvoice($dto, $request->user());

            return [
                'status' => 201,
                'payload' => [
                    'message' => 'Invoice created successfully.',
                    'data' => (new InvoiceResource($invoice->load([
                        'customer',
                        'lines',
                        'organization',
                        'taxTotals',
                        'stateTransitions',
                    ])))->resolve($request),
                ],
            ];
        });

        $this->storeIdempotentResponse($request, 'invoice:create', $result['status'], $result['payload']);

        return response()->json($result['payload'], $result['status']);
    }

    /**
     * Display the specified invoice.
     */
    public function show(Invoice $invoice): InvoiceResource
    {
        $this->authorize('view', $invoice);

        return new InvoiceResource($invoice->load([
            'customer',
            'organization',
            'lines',
            'taxTotals',
            'paymentMeans',
            'nrsSubmissions',
            'stateTransitions',
        ]));
    }

    /**
     * Update an editable draft invoice.
     */
    public function update(StoreInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $dto = CreateInvoiceDTO::fromRequest($request);
        $invoice = $this->invoiceService->updateDraftInvoice($invoice, $dto, $request->user());

        return response()->json([
            'message' => 'Draft invoice updated successfully.',
            'data' => (new InvoiceResource($invoice))->resolve($request),
        ]);
    }

    /**
     * Step 1: Validate on NRS.
     */
    public function validateOnNrs(Request $request, Invoice $invoice): InvoiceResource
    {
        $this->authorize('manageLifecycle', $invoice);

        $this->nrsService->validate($invoice, $request->user());

        return new InvoiceResource($invoice->fresh()->load(['customer', 'lines', 'organization', 'nrsSubmissions', 'stateTransitions']));
    }

    /**
     * Step 2: Sign on NRS.
     */
    public function signOnNrs(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('manageLifecycle', $invoice);

        $result = $this->nrsService->sign($invoice, $request->user());

        $resource = new InvoiceResource($invoice->fresh()->load(['customer', 'lines', 'organization', 'nrsSubmissions', 'stateTransitions']));

        // Sign succeeded but transmit failed — return 207 Multi-Status
        if (! empty($result['transmit_failed'])) {
            return response()->json([
                'data' => $resource->resolve(request()),
                'transmit_failed' => true,
                'transmit_error' => $result['transmit_error'] ?? 'Transmission failed after signing.',
            ], 207);
        }

        return response()->json(['data' => $resource->resolve(request())]);
    }

    /**
     * Step 3: Transmit on NRS.
     */
    public function transmitOnNrs(Request $request, Invoice $invoice): InvoiceResource
    {
        $this->authorize('manageLifecycle', $invoice);

        $this->nrsService->transmit($invoice, $request->user());

        return new InvoiceResource($invoice->fresh()->load(['customer', 'lines', 'organization', 'nrsSubmissions', 'stateTransitions']));
    }

    /**
     * Step 4: Confirm on NRS.
     */
    public function confirmOnNrs(Invoice $invoice): InvoiceResource
    {
        $this->authorize('manageLifecycle', $invoice);

        $this->nrsService->confirm($invoice);

        return new InvoiceResource($invoice->load(['customer', 'lines', 'organization', 'stateTransitions']));
    }

    /**
     * Delete an editable invoice.
     */
    public function destroy(Invoice $invoice): JsonResponse
    {
        $this->authorize('delete', $invoice);
        $invoiceNumber = $invoice->invoice_number;
        $invoiceUuid = $invoice->uuid;

        $invoice->delete();

        $this->activityLog->log(
            request()->user(),
            'invoice.deleted',
            $invoice,
            "Invoice #{$invoiceNumber} deleted.",
            ['invoice_id' => $invoiceUuid],
        );

        return response()->json([
            'message' => 'Invoice deleted successfully.',
        ]);
    }

    /**
     * Exchange: Self Health Check — Verify APP readiness for transmission.
     */
    public function selfHealthCheck(): JsonResponse
    {
        $result = $this->nrsService->selfHealthCheck();

        return response()->json([
            'message' => 'Self health check completed.',
            'data' => $result,
        ]);
    }

    /**
     * Exchange: Lookup IRN — Retrieve invoice and party details from NRS.
     */
    public function lookupIrn(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        if (! $invoice->irn) {
            return response()->json(['message' => 'This invoice has no IRN yet.'], 422);
        }

        $result = $this->nrsService->lookupIrn($invoice->irn);

        return response()->json([
            'message' => 'Lookup completed.',
            'data' => $result,
        ]);
    }

    /**
     * Download locally generated A4 PDF.
     */
    public function downloadPdf(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        return $this->pdfService->generate($invoice);
    }

    /**
     * Download the official NRS invoice artifact.
     */
    public function downloadXml(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        $artifact = $this->artifactService->downloadAndStoreXmlIfAvailable($invoice, true);

        if (! $artifact) {
            return response()->json([
                'message' => 'Official NRS invoice artifact is not available for this invoice yet.',
                'retryable' => true,
            ], 422);
        }

        $fileName = "invoice_{$invoice->invoice_number}_ubl.xml";

        return response($artifact['content'], 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            'X-NRS-Official-Artifact' => 'true',
            'X-NRS-Artifact-Hash' => $artifact['hash'],
        ]);
    }

    /**
     * Phase 5: Update localized payment status.
     */
    public function updatePaymentStatus(UpdateInvoicePaymentStatusRequest $request, Invoice $invoice): JsonResponse
    {
        if ($response = $this->idempotentReplay($request, "invoice:{$invoice->id}:payment")) {
            return $response;
        }

        $dto = UpdateInvoicePaymentStatusDTO::fromRequest($request);
        try {
            $invoice = $this->paymentService->updatePaymentStatus($invoice, $dto, $request->user());
        } catch (\Throwable $e) {
            return response()->json([
                'code' => 'NRS_PAYMENT_UPDATE_FAILED',
                'message' => 'Failed to update payment status on NRS.',
                'details' => ['reason' => $e->getMessage()],
                'retryable' => true,
            ], 400);
        }

        $payload = [
            'message' => 'Payment status updated.',
            'status' => $invoice->payment_status,
        ];

        $this->storeIdempotentResponse($request, "invoice:{$invoice->id}:payment", 200, $payload);

        return response()->json($payload);
    }

    private function idempotencyKey(Request $request): ?string
    {
        return $this->idempotency->keyFromHeaders(
            $request->header('Idempotency-Key'),
            $request->header('X-Idempotency-Key'),
        );
    }

    private function idempotentReplay(Request $request, string $scope): ?JsonResponse
    {
        $key = $this->idempotencyKey($request);
        if (! $key) {
            return null;
        }

        $record = $this->idempotency->replay($request->user()->current_organization_id, $scope, $key);

        if (! $record) {
            return null;
        }

        return response()->json($record->response_payload, $record->status_code ?? 200)
            ->header('X-Idempotent-Replay', 'true');
    }

    private function storeIdempotentResponse(Request $request, string $scope, int $statusCode, array $payload): void
    {
        $key = $this->idempotencyKey($request);
        if (! $key) {
            return;
        }

        $this->idempotency->store($request->user()->current_organization_id, $scope, $key, $statusCode, $payload);
    }
}
