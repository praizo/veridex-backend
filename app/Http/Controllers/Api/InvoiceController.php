<?php

namespace App\Http\Controllers\Api;

use App\DTOs\Invoice\CreateInvoiceDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\IdempotencyRecord;
use App\Models\Invoice;
use App\Services\ActivityLogService;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use App\Services\Nrs\NrsInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceService $invoiceService,
        protected NrsInvoiceService $nrsService,
        protected InvoicePdfService $pdfService,
        protected ActivityLogService $activityLog
    ) {}

    /**
     * Display a listing of invoices.
     */
    public function index(Request $request): JsonResponse
    {
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
            $invoice = $this->invoiceService->createInvoice($dto);

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
    protected function checkInvoiceTenancy(Invoice $invoice): void
    {
        if ($invoice->organization_id !== request()->user()->current_organization_id) {
            abort(403, 'Unauthorized access to invoice');
        }
    }

    /**
     * Display the specified invoice.
     */
    public function show(Invoice $invoice): InvoiceResource
    {
        $this->checkInvoiceTenancy($invoice);

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
        $this->checkInvoiceTenancy($invoice);

        $dto = CreateInvoiceDTO::fromRequest($request);
        $invoice = $this->invoiceService->updateDraftInvoice($invoice, $dto);

        return response()->json([
            'message' => 'Draft invoice updated successfully.',
            'data' => (new InvoiceResource($invoice))->resolve($request),
        ]);
    }

    /**
     * Step 1: Validate on NRS.
     */
    public function validateOnNrs(Invoice $invoice): InvoiceResource
    {
        $this->checkInvoiceTenancy($invoice);

        $this->nrsService->validate($invoice);

        return new InvoiceResource($invoice->fresh()->load(['customer', 'lines', 'organization', 'nrsSubmissions', 'stateTransitions']));
    }

    /**
     * Step 2: Sign on NRS.
     */
    public function signOnNrs(Invoice $invoice): InvoiceResource
    {
        $this->checkInvoiceTenancy($invoice);

        $this->nrsService->sign($invoice);

        return new InvoiceResource($invoice->fresh()->load(['customer', 'lines', 'organization', 'nrsSubmissions', 'stateTransitions']));
    }

    /**
     * Step 3: Transmit on NRS.
     */
    public function transmitOnNrs(Invoice $invoice): InvoiceResource
    {
        $this->checkInvoiceTenancy($invoice);

        $this->nrsService->transmit($invoice);

        return new InvoiceResource($invoice->fresh()->load(['customer', 'lines', 'organization', 'nrsSubmissions', 'stateTransitions']));
    }

    /**
     * Step 4: Confirm on NRS.
     */
    public function confirmOnNrs(Invoice $invoice): InvoiceResource
    {
        $this->checkInvoiceTenancy($invoice);

        $this->nrsService->confirm($invoice);

        return new InvoiceResource($invoice->load(['customer', 'lines', 'organization', 'stateTransitions']));
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
        $this->checkInvoiceTenancy($invoice);

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
        $this->checkInvoiceTenancy($invoice);

        return $this->pdfService->generate($invoice);
    }

    /**
     * Phase 5: Update localized payment status.
     */
    public function updatePaymentStatus(Request $request, Invoice $invoice): JsonResponse
    {
        $this->checkInvoiceTenancy($invoice);

        if ($response = $this->idempotentReplay($request, "invoice:{$invoice->id}:payment")) {
            return $response;
        }

        $validated = $request->validate([
            'payment_status' => 'required|string|in:PENDING,PAID,PARTIAL,REJECTED',
            'reference' => 'nullable|string|max:255',
        ]);

        // If the invoice has been fiscalized (signed or later status), push the update to NRS
        $fiscalizedStatuses = ['signed', 'pending_transmit', 'transmit_failed', 'transmitted', 'confirmed'];
        $currentStatus = $invoice->status->value ?? $invoice->status;

        if (in_array($currentStatus, $fiscalizedStatuses) && $validated['payment_status'] !== 'PENDING') {
            try {
                $this->nrsService->updatePayment($invoice, $validated['payment_status'], $validated['reference'] ?? null);
            } catch (\Throwable $e) {
                return response()->json([
                    'code' => 'NRS_PAYMENT_UPDATE_FAILED',
                    'message' => 'Failed to update payment status on NRS.',
                    'details' => ['reason' => $e->getMessage()],
                    'retryable' => true,
                ], 400);
            }
        }

        // Only update local DB if the remote update succeeded (or wasn't needed)
        $invoice->update(['payment_status' => $validated['payment_status']]);

        $this->activityLog->log(
            $request->user(),
            'PAYMENT_STATUS_UPDATE',
            $invoice,
            "Marked invoice as {$validated['payment_status']}"
        );

        $payload = [
            'message' => 'Payment status updated.',
            'status' => $invoice->payment_status,
        ];

        $this->storeIdempotentResponse($request, "invoice:{$invoice->id}:payment", 200, $payload);

        return response()->json($payload);
    }

    private function idempotencyKey(Request $request): ?string
    {
        return $request->header('Idempotency-Key') ?: $request->header('X-Idempotency-Key');
    }

    private function idempotentReplay(Request $request, string $scope): ?JsonResponse
    {
        $key = $this->idempotencyKey($request);
        if (! $key) {
            return null;
        }

        $record = IdempotencyRecord::where('organization_id', $request->user()->current_organization_id)
            ->where('scope', $scope)
            ->where('key', $key)
            ->whereNotNull('completed_at')
            ->first();

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

        IdempotencyRecord::updateOrCreate([
            'organization_id' => $request->user()->current_organization_id,
            'scope' => $scope,
            'key' => $key,
        ], [
            'status_code' => $statusCode,
            'response_payload' => $payload,
            'completed_at' => now(),
        ]);
    }
}
