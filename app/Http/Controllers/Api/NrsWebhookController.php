<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\InvoiceStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NrsWebhookController extends Controller
{
    protected InvoiceStateService $stateService;

    public function __construct(InvoiceStateService $stateService)
    {
        $this->stateService = $stateService;
    }

    public function handle(Request $request): JsonResponse
    {
        $event = $request->input('event');     // e.g. 'invoice.confirmed', 'invoice.rejected'
        $irn = $request->input('irn');
        $data = $request->input('data');

        Log::info("NRS Webhook Received: {$event} for IRN: {$irn}", ['payload' => $request->all()]);

        if (empty($irn)) {
            return response()->json(['message' => 'Missing IRN'], 422);
        }

        // Find the invoice globally (webhooks aren't organization-scoped in the URL)
        $invoice = Invoice::where('irn', $irn)->first();

        if (! $invoice) {
            Log::warning("NRS Webhook Error: Invoice not found for IRN: {$irn}");

            return response()->json(['message' => 'Invoice not found'], 404);
        }

        // Handle event
        try {
            switch ($event) {
                case 'invoice.confirmed':
                    $this->stateService->transition($invoice, InvoiceStatus::CONFIRMED, null, 'nrs_webhook', $data['message'] ?? 'Confirmed via NRS Webhook');
                    break;
                case 'invoice.rejected':
                    $this->stateService->transition($invoice, InvoiceStatus::VALIDATION_FAILED, null, 'nrs_webhook', $data['message'] ?? 'Rejected via NRS Webhook');
                    break;
                case 'invoice.payment_updated':
                    // Custom logic for payment updates if needed in future
                    Log::info("Payment update received for IRN: {$irn}");
                    break;
                default:
                    Log::warning("Unknown NRS webhook event: {$event}");
            }
        } catch (\Exception $e) {
            Log::error('Failed to process NRS webhook: '.$e->getMessage());

            return response()->json(['message' => 'Internal processing error'], 500);
        }

        return response()->json(['status' => 'received'], 200);
    }
}
