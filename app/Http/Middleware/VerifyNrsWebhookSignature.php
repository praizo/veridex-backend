<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyNrsWebhookSignature
{
    /**
     * Verify the incoming NRS/APP webhook request.
     *
     * Checks:
     * 1. X-API-Key matches the configured APP key
     * 2. X-Timestamp is recent (within tolerance window)
     * 3. X-Signature matches HMAC-SHA256(payload + timestamp, secret)
     *
     * Can be disabled via NRS_WEBHOOK_VERIFY_SIGNATURE=false for development.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Allow bypassing signature verification during development
        if (! config('services.nrs.webhook_verify_signature')) {
            return $next($request);
        }

        $apiKey = $request->header('X-API-Key');
        $signature = $request->header('X-Signature');
        $timestamp = $request->header('X-Timestamp');

        // --- 1. Check required headers are present ---
        if (! $apiKey || ! $signature || ! $timestamp) {
            Log::warning('NRS Webhook: Missing required security headers', [
                'has_api_key' => (bool) $apiKey,
                'has_signature' => (bool) $signature,
                'has_timestamp' => (bool) $timestamp,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'status' => 401,
                'message' => 'Missing required security headers',
            ], 401);
        }

        // --- 2. Verify API Key ---
        $expectedApiKey = config('services.nrs.webhook_api_key');
        if (! hash_equals($expectedApiKey ?? '', $apiKey)) {
            Log::warning('NRS Webhook: Invalid API Key', ['ip' => $request->ip()]);

            return response()->json([
                'status' => 401,
                'message' => 'Invalid API Key',
            ], 401);
        }

        // --- 3. Verify Timestamp (prevent replay attacks) ---
        try {
            $requestTime = new \DateTimeImmutable($timestamp);
            $now = new \DateTimeImmutable;
            $diffSeconds = abs($now->getTimestamp() - $requestTime->getTimestamp());
            $tolerance = (int) config('services.nrs.webhook_timestamp_tolerance', 300);

            if ($diffSeconds > $tolerance) {
                Log::warning('NRS Webhook: Stale timestamp', [
                    'timestamp' => $timestamp,
                    'diff_seconds' => $diffSeconds,
                    'tolerance' => $tolerance,
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'status' => 401,
                    'message' => 'Request timestamp is too old',
                ], 401);
            }
        } catch (\Exception $e) {
            Log::warning('NRS Webhook: Unparseable timestamp', [
                'timestamp' => $timestamp,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'status' => 401,
                'message' => 'Invalid timestamp format',
            ], 401);
        }

        // --- 4. Verify HMAC-SHA256 Signature ---
        $secret = config('services.nrs.webhook_secret');
        $rawBody = $request->getContent();
        $computedSignature = hash_hmac('sha256', $rawBody.$timestamp, $secret);

        if (! hash_equals($computedSignature, $signature)) {
            Log::warning('NRS Webhook: Signature mismatch', ['ip' => $request->ip()]);

            return response()->json([
                'status' => 401,
                'message' => 'Invalid signature',
            ], 401);
        }

        return $next($request);
    }
}
