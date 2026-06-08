<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NrsRawDebugExporter
{
    public function enabled(): bool
    {
        return (bool) config('audit.nrs_raw_debug_export') && App::environment(['local', 'development', 'testing']);
    }

    public function export(
        string $method,
        string $url,
        array $headers,
        array $requestPayload,
        ?Response $response = null,
        ?array $responsePayload = null,
        ?array $context = null,
        ?string $error = null,
    ): ?string {
        if (! $this->enabled()) {
            return null;
        }

        $context ??= [];
        $timestamp = now()->format('Ymd_His_u');
        $submissionPart = isset($context['submission_id']) ? 'submission-'.$context['submission_id'].'_' : '';
        $invoicePart = isset($context['invoice_id']) ? 'invoice-'.$context['invoice_id'].'_' : '';
        $actionPart = isset($context['action']) ? Str::slug((string) $context['action']).'_' : '';
        $fileName = $timestamp.'_'.$submissionPart.$invoicePart.$actionPart.Str::random(8).'.json';
        $path = trim(config('audit.nrs_raw_debug_path', 'nrs-debug'), '/').'/'.$fileName;

        $artifact = [
            'warning' => 'Development-only raw NRS debug export. Contains taxpayer/invoice payload data. Do not commit or publish.',
            'context' => $context,
            'request' => [
                'method' => strtoupper($method),
                'url' => $url,
                'headers' => $this->maskHeaders($headers),
                'payload' => $requestPayload,
            ],
            'response' => [
                'status' => $response?->status(),
                'payload' => $responsePayload,
            ],
            'error' => $error,
            'exported_at' => now()->toISOString(),
        ];

        Storage::disk(config('audit.nrs_raw_debug_disk', 'local'))
            ->put($path, json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    private function maskHeaders(array $headers): array
    {
        $masked = [];

        foreach ($headers as $key => $value) {
            $normalized = strtolower((string) $key);
            $masked[$key] = in_array($normalized, ['x-api-key', 'x-api-secret', 'authorization'], true)
                ? '[REDACTED]'
                : $value;
        }

        return $masked;
    }
}
