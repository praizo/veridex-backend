<?php

namespace App\Services\Nrs;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use App\Exceptions\NrsApiException;
use App\Exceptions\NrsConnectionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\NrsApiLog;
use Illuminate\Support\Facades\Request;

/**
 * Base Client for NRS Merchant Buyer Solution (MBS) API.
 * 
 * This client handles platform-level authentication using Veridex's 
 * global API Key and Secret stored in the .env file.
 */
class NrsClient
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $apiSecret;
    protected string $breakerKey = 'nrs_circuit_breaker_open';
    protected string $failureCountKey = 'nrs_failure_count';

    public function __construct()
    {
        $this->baseUrl = config('nrs.base_url');
        $this->apiKey = config('nrs.api_key');
        $this->apiSecret = config('nrs.api_secret');

        if (empty($this->apiKey) || empty($this->apiSecret)) {
            Log::error('NRS Platform Credentials are missing in .env');
        }
    }

    /**
     * Send a request to the NRS API.
     */
    public function request(string $method, string $endpoint, array $data = [], array $params = [], array $headers = []): Response
    {
        // 1. Check Circuit Breaker
        if (Cache::has($this->breakerKey)) {
            throw new NrsConnectionException("NRS API is currently unavailable (Circuit Breaker active).");
        }

        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        $startTime = microtime(true);

        $baseHeaders = [
            'x-api-key' => $this->apiKey,
            'x-api-secret' => $this->apiSecret,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        try {
            $response = Http::withHeaders(array_merge($baseHeaders, $headers))
                ->timeout(30)
                ->retry(3, 100, function ($exception) {
                    return $exception instanceof \Illuminate\Http\Client\ConnectionException;
                })
                ->{$method}($url, $method === 'get' ? $params : $data);

            $latency = (microtime(true) - $startTime) * 1000;

            // 2. Log API Interaction
            $this->logInteraction($method, $endpoint, $data, $response, $latency);

            if ($response->failed()) {
                $this->handleFailure();
                $this->handleErrorResponse($response, $endpoint);
            }

            $this->handleSuccess();
            return $response;

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $this->handleFailure();
            Log::error("NRS Connection Error: {$e->getMessage()}", ['endpoint' => $endpoint]);
            throw new NrsConnectionException("Unable to connect to NRS API: " . $e->getMessage());
        } catch (\Exception $e) {
            if (!($e instanceof NrsApiException)) {
                $this->handleFailure();
                Log::error("NRS Unexpected Error: {$e->getMessage()}", ['endpoint' => $endpoint]);
            }
            throw $e;
        }
    }

    protected function logInteraction(string $method, string $endpoint, array $data, Response $response, float $latency): void
    {
        try {
            NrsApiLog::create([
                'organization_id' => auth()->check() ? auth()->user()->current_organization_id : null,
                'irn' => $data['invoice']['irn'] ?? ($data['irn'] ?? null),
                'endpoint' => $endpoint,
                'method' => strtoupper($method),
                'request_payload' => $data,
                'response_body' => $response->json(),
                'status_code' => $response->status(),
                'latency_ms' => $latency,
                'ip_address' => Request::ip(),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to log NRS API interaction: " . $e->getMessage());
        }
    }

    protected function handleSuccess(): void
    {
        Cache::forget($this->failureCountKey);
    }

    protected function handleFailure(): void
    {
        $failures = Cache::increment($this->failureCountKey);
        
        if ($failures >= 5) {
            Log::alert("NRS Circuit Breaker Triggered. Blocking requests for 5 minutes.");
            Cache::put($this->breakerKey, true, now()->addMinutes(5));
        }
    }

    public function get(string $endpoint, array $params = [], array $headers = []): Response
    {
        return $this->request('get', $endpoint, [], $params, $headers);
    }

    public function post(string $endpoint, array $data = [], array $headers = []): Response
    {
        return $this->request('post', $endpoint, $data, [], $headers);
    }

    public function patch(string $endpoint, array $data = [], array $headers = []): Response
    {
        return $this->request('patch', $endpoint, $data, [], $headers);
    }

    /**
     * Handle failed API responses.
     */
    protected function handleErrorResponse(Response $response, string $endpoint): void
    {
        $status = $response->status();
        $body = $response->json();
        $message = $body['message'] ?? 'Unknown NRS API error';

        Log::warning("NRS API Error [{$status}] at {$endpoint}: {$message}", [
            'response' => $body,
        ]);

        throw new NrsApiException($message, $status);
    }
}
