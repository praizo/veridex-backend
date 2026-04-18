<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\Nrs\NrsInvoiceService;
use App\DTOs\Invoice\CreateInvoiceDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\InvoicePdfService;
use App\Services\ActivityLogService;

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
        $dto = CreateInvoiceDTO::fromRequest($request);
        $invoice = $this->invoiceService->createInvoice($dto);

        return response()->json([
            'message' => 'Invoice created successfully.',
            'data'    => new InvoiceResource($invoice->load([
                'customer', 
                'lines', 
                'organization', 
                'taxTotals', 
                'stateTransitions'
            ]))
        ], 201);
    }

    /**
     * Display the specified invoice.
     */
    public function show(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($invoice->load([
            'customer', 
            'organization',
            'lines', 
            'taxTotals', 
            'paymentMeans', 
            'nrsSubmissions', 
            'stateTransitions'
        ]));
    }

    /**
     * Step 1: Validate on NRS.
     */
    public function validateOnNrs(Invoice $invoice): InvoiceResource
    {
        $this->nrsService->validate($invoice);
        return new InvoiceResource($invoice->load(['customer', 'lines', 'organization', 'stateTransitions']));
    }

    /**
     * Step 2: Sign on NRS.
     */
    public function signOnNrs(Invoice $invoice): InvoiceResource
    {
        $this->nrsService->sign($invoice);
        return new InvoiceResource($invoice->load(['customer', 'lines', 'organization', 'stateTransitions']));
    }

    /**
     * Step 3: Transmit on NRS.
     */
    public function transmitOnNrs(Invoice $invoice): InvoiceResource
    {
        $this->nrsService->transmit($invoice);
        return new InvoiceResource($invoice->load(['customer', 'lines', 'organization', 'stateTransitions']));
    }

    /**
     * Step 4: Confirm on NRS.
     */
    public function confirmOnNrs(Invoice $invoice): InvoiceResource
    {
        $this->nrsService->confirm($invoice);
        return new InvoiceResource($invoice->load(['customer', 'lines', 'organization', 'stateTransitions']));
    }

    /**
     * Phase 5: Download Official A4 PDF.
     */
    public function downloadPdf(Invoice $invoice)
    {
        return $this->pdfService->generate($invoice);
    }

    /**
     * Phase 5: Update localized payment status.
     */
    public function updatePaymentStatus(Request $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validate([
            'payment_status' => 'required|string|in:PENDING,PAID,PARTIAL,REJECTED'
        ]);

        $invoice->update(['payment_status' => $validated['payment_status']]);

        $this->activityLog->log(
            $request->user(), 
            'PAYMENT_STATUS_UPDATE', 
            $invoice, 
            "Marked invoice as {$validated['payment_status']}"
        );

        return response()->json([
            'message' => 'Payment status updated.',
            'status'  => $invoice->payment_status,
        ]);
    }
}
