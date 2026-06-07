<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessNrsWebhookJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NrsWebhookController extends Controller
{
    /**
     * Handle incoming NRS/APP webhook notifications.
     *
     * This controller is intentionally thin — it validates the basic payload
     * structure, dispatches a background job for processing, and immediately
     * returns the required ACKNOWLEDGED response to satisfy the APP's SLA.
     *
     * Security (signature verification) is handled by the VerifyNrsWebhookSignature middleware.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('NRS Webhook Received', [
            'message' => $payload['message'] ?? 'unknown',
            'irn' => $payload['irn'] ?? 'missing',
            'ip' => $request->ip(),
        ]);

        // Basic sanity check — don't waste a queue job if the payload is empty
        if (empty($payload['irn'])) {
            return response()->json([
                'status' => 422,
                'message' => 'Missing IRN in payload',
            ], 422);
        }

        // Dispatch to background for asynchronous processing
        ProcessNrsWebhookJob::dispatch($payload);

        // Return the exact response the APP specification requires
        return response()->json([
            'status' => 200,
            'message' => 'ACKNOWLEDGED',
        ], 200);
    }
}
