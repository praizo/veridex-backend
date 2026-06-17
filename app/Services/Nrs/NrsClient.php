<?php

declare(strict_types=1);

namespace App\Services\Nrs;

use App\Exceptions\NrsApiException;
use App\Exceptions\NrsConnectionException;
use App\Models\NrsApiLog;
use App\Services\Operations\OperationalMetricService;
use App\Support\NrsRawDebugExporter;
use App\Support\SensitiveDataRedactor;
use Exception;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

    protected SensitiveDataRedactor $redactor;

    protected NrsRawDebugExporter $rawDebugExporter;

    protected OperationalMetricService $metrics;

    public function __construct()
    {
        $this->baseUrl = config('nrs.base_url');
        $this->apiKey = config('nrs.api_key');
        $this->apiSecret = config('nrs.api_secret');
        $this->redactor = app(SensitiveDataRedactor::class);
        $this->rawDebugExporter = app(NrsRawDebugExporter::class);
        $this->metrics = app(OperationalMetricService::class);

        if (empty($this->apiKey) || empty($this->apiSecret)) {
            Log::error('NRS Platform Credentials are missing in .env');
        }
    }

    /**
     * Send a request to the NRS API.
     */
    public function request(string $method, string $endpoint, array $data = [], array $params = [], array $headers = [], array $debugContext = []): Response
    {
        // 1. Check Circuit Breaker
        if (Cache::has($this->breakerKey)) {
            throw new NrsConnectionException('NRS API is currently unavailable (Circuit Breaker active).');
        }

        $url = $this->baseUrl.'/'.ltrim($endpoint, '/');
        $startTime = microtime(true);

        $baseHeaders = [
            'x-api-key' => $this->apiKey,
            'x-api-secret' => $this->apiSecret,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        try {
            $mergedHeaders = array_merge($baseHeaders, $headers);

            $response = Http::withHeaders($mergedHeaders)
                ->timeout(30)
                ->retry(3, 100, function ($exception) {
                    return $exception instanceof ConnectionException;
                })
                ->{$method}($url, $method === 'get' ? $params : $data);

            $latency = (microtime(true) - $startTime) * 1000;

            Log::info('NRS request completed', [
                'ENDPOINT' => '['.$method.'] '.$url,
                'REQUEST_PAYLOAD' => $this->redactor->redact($data),
                'RESPONSE_STATUS' => $response->status(),
                'RESPONSE_BODY' => $this->redactor->redact($this->safeJson($response)),
            ]);

            $debugPath = $this->rawDebugExporter->export(
                method: $method,
                url: $url,
                headers: $mergedHeaders,
                requestPayload: $method === 'get' ? $params : $data,
                response: $response,
                responsePayload: $this->safeJson($response),
                context: $debugContext,
            );

            if ($debugPath) {
                Log::warning('Raw NRS debug export written for development support.', [
                    'path' => $debugPath,
                    'context' => $debugContext,
                ]);
            }

            // 2. Log API Interaction
            $this->logInteraction($method, $endpoint, $data, $response, $latency, $debugContext);

            if ($response->failed()) {
                $this->handleFailure();
                $this->handleErrorResponse($response, $endpoint);
            }

            $this->handleSuccess();

            return $response;

        } catch (ConnectionException|ConnectException $e) {
            $this->handleFailure();
            Log::error('NRS Connection Error: '.$e->getMessage(), ['endpoint' => $endpoint]);
            throw new NrsConnectionException('The official FIRS/NRS service is temporarily unreachable. Please check your internet connection or try again later.');
        } catch (RequestException $e) {
            $this->handleFailure();
            if ($e->response) {
                Log::info('NRS request failed', [
                    'ENDPOINT' => '['.$method.'] '.$url,
                    'REQUEST_PAYLOAD' => $this->redactor->redact($data),
                    'RESPONSE_STATUS' => $e->response->status(),
                    'RESPONSE_BODY' => $this->redactor->redact($this->safeJson($e->response)),
                ]);
                $this->rawDebugExporter->export(
                    method: $method,
                    url: $url,
                    headers: $mergedHeaders ?? $baseHeaders,
                    requestPayload: $method === 'get' ? $params : $data,
                    response: $e->response,
                    responsePayload: $this->safeJson($e->response),
                    context: $debugContext,
                    error: $e->getMessage(),
                );
                $this->handleErrorResponse($e->response, $endpoint);
            }
            throw $e;
        } catch (Exception $e) {
            if (! ($e instanceof NrsApiException)) {
                $this->handleFailure();
                Log::error('NRS Unexpected Error: '.$e->getMessage(), ['endpoint' => $endpoint]);
            }
            throw $e;
        }
    }

    protected function logInteraction(string $method, string $endpoint, array $data, Response $response, float $latency, array $debugContext = []): void
    {
        try {
            NrsApiLog::create([
                'organization_id' => $debugContext['organization_id'] ?? null,
                'irn' => $data['invoice']['irn'] ?? ($data['irn'] ?? null),
                'endpoint' => $endpoint,
                'method' => strtoupper($method),
                'request_payload' => $this->redactor->redact($data),
                'response_body' => $this->redactor->redact($this->safeJson($response)),
                'status_code' => $response->status(),
                'latency_ms' => $latency,
                'ip_address' => $debugContext['ip_address'] ?? null,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to log NRS API interaction: '.$e->getMessage());
        }
    }

    protected function handleSuccess(): void
    {
        Cache::forget($this->failureCountKey);
    }

    protected function handleFailure(): void
    {
        $failures = Cache::increment($this->failureCountKey);
        $this->metrics->increment('nrs_failure_rate', 10);

        if ($failures >= 5) {
            Log::alert('NRS Circuit Breaker Triggered. Blocking requests for 5 minutes.');
            Cache::put($this->breakerKey, true, now()->addMinutes(5));
        }
    }

    public function get(string $endpoint, array $params = [], array $headers = [], array $debugContext = []): Response
    {
        return $this->request('get', $endpoint, [], $params, $headers, $debugContext);
    }

    public function post(string $endpoint, array $data = [], array $headers = [], array $debugContext = []): Response
    {
        return $this->request('post', $endpoint, $data, [], $headers, $debugContext);
    }

    public function patch(string $endpoint, array $data = [], array $headers = [], array $debugContext = []): Response
    {
        return $this->request('patch', $endpoint, $data, [], $headers, $debugContext);
    }

    /**
     * Handle failed API responses.
     */
    protected function handleErrorResponse(Response $response, string $endpoint): void
    {
        $status = $response->status();
        $body = $this->safeJson($response) ?? [];
        $message = $body['message'] ?? 'Unknown NRS API error';
        $errorDetails = null;

        if (isset($body['error'])) {
            $errorDetails = is_array($body['error']) ? $body['error'] : ['raw' => $body['error']];
            if (isset($errorDetails['public_message'])) {
                $message = $errorDetails['public_message'];
            }
        }

        Log::warning("NRS API Error [{$status}] at {$endpoint}: {$message}", [
            'response' => $this->redactor->redact($body),
        ]);

        throw new NrsApiException($message, $status, $errorDetails);
    }

    private function safeJson(Response $response): ?array
    {
        try {
            $json = $response->json();

            return is_array($json) ? $json : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
