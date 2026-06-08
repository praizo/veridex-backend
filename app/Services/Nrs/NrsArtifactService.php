<?php

namespace App\Services\Nrs;

use App\Exceptions\NrsApiException;
use App\Models\Invoice;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class NrsArtifactService
{
    public function __construct(
        private readonly NrsClient $client,
    ) {}

    public function officialPdfResponse(Invoice $invoice)
    {
        $artifact = $this->downloadAndStorePdf($invoice);

        $fileName = "invoice_{$invoice->invoice_number}_official.pdf";

        return response($artifact['content'], 200, [
            'Content-Type' => $artifact['content_type'],
            'Content-Disposition' => 'inline; filename="'.$fileName.'"',
            'X-NRS-Official-Artifact' => 'true',
            'X-NRS-Artifact-Hash' => $artifact['hash'],
        ]);
    }

    public function downloadAndStorePdf(Invoice $invoice): array
    {
        $this->ensureDownloadable($invoice);

        if ($invoice->official_pdf_path && Storage::disk('local')->exists($invoice->official_pdf_path)) {
            $cachedContent = Storage::disk('local')->get($invoice->official_pdf_path);
            if (! $this->isPdf($cachedContent)) {
                Storage::disk('local')->delete($invoice->official_pdf_path);
                $invoice->forceFill([
                    'official_pdf_path' => null,
                    'official_pdf_hash' => null,
                    'pdf_hash' => null,
                ])->save();
            } else {
                return [
                    'content' => $cachedContent,
                    'content_type' => 'application/pdf',
                    'hash' => $invoice->official_pdf_hash,
                    'path' => $invoice->official_pdf_path,
                ];
            }
        }

        $response = $this->client->get(
            "api/v1/invoice/download/{$invoice->irn}",
            [],
            ['Accept' => 'application/json'],
            [
                'invoice_id' => $invoice->id,
                'invoice_uuid' => $invoice->uuid,
                'irn' => $invoice->irn,
                'action' => 'download_official_pdf',
            ]
        );

        $content = $this->pdfContentFromResponse($response);
        if (! $this->isPdf($content)) {
            throw new NrsApiException(
                'NRS download did not return a valid PDF artifact. Check the IRN, NRS credentials, and invoice status before retrying.',
                502,
                [
                    'content_type' => $response->header('Content-Type'),
                    'body_preview' => mb_substr(trim($content), 0, 120),
                ]
            );
        }

        $hash = hash('sha256', $content);
        $path = $this->artifactPath($invoice, 'pdf');

        Storage::disk('local')->put($path, $content);

        $invoice->forceFill([
            'official_pdf_path' => $path,
            'official_pdf_hash' => $hash,
            'pdf_hash' => $hash,
        ])->save();

        $this->downloadAndStoreXmlIfAvailable($invoice);

        return [
            'content' => $content,
            'content_type' => 'application/pdf',
            'hash' => $hash,
            'path' => $path,
        ];
    }

    public function downloadAndStoreXmlIfAvailable(Invoice $invoice): ?array
    {
        $this->ensureDownloadable($invoice);

        if ($invoice->official_xml_path && Storage::disk('local')->exists($invoice->official_xml_path)) {
            return [
                'content' => Storage::disk('local')->get($invoice->official_xml_path),
                'hash' => $invoice->official_xml_hash,
                'path' => $invoice->official_xml_path,
            ];
        }

        try {
            $response = $this->client->get(
                "api/v1/invoice/download/{$invoice->irn}",
                [],
                ['Accept' => 'application/xml'],
                [
                    'invoice_id' => $invoice->id,
                    'invoice_uuid' => $invoice->uuid,
                    'irn' => $invoice->irn,
                    'action' => 'download_official_xml',
                ]
            );

            $content = $response->body();
            $xmlContent = $this->artifactContentFromResponse($content) ?? $content;
            if ($xmlContent === '' || ! $this->isXml($xmlContent)) {
                return null;
            }

            $hash = hash('sha256', $xmlContent);
            $path = $this->artifactPath($invoice, 'xml');

            Storage::disk('local')->put($path, $xmlContent);

            $invoice->forceFill([
                'official_xml_path' => $path,
                'official_xml_hash' => $hash,
                'xml_hash' => $hash,
            ])->save();

            return [
                'content' => $xmlContent,
                'hash' => $hash,
                'path' => $path,
            ];
        } catch (\Throwable $e) {
            Log::warning('Official NRS XML artifact could not be downloaded.', [
                'invoice_id' => $invoice->id,
                'irn' => $invoice->irn,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function ensureDownloadable(Invoice $invoice): void
    {
        $status = $invoice->status?->value ?? $invoice->status;
        $fiscalizedStatuses = ['signed', 'pending_transmit', 'transmit_failed', 'transmitted', 'confirmed'];

        if (! $invoice->irn || ! in_array($status, $fiscalizedStatuses, true)) {
            throw new NrsApiException('Official NRS artifacts are only available after an invoice is signed.', 422);
        }
    }

    private function artifactPath(Invoice $invoice, string $extension): string
    {
        return "nrs-artifacts/{$invoice->organization_id}/{$invoice->uuid}/official.{$extension}";
    }

    private function pdfContentFromResponse(Response $response): string
    {
        $content = $response->body();

        if ($this->isPdf($content)) {
            return $content;
        }

        return $this->artifactContentFromResponse($content) ?? $content;
    }

    private function artifactContentFromResponse(string $content): ?string
    {
        $payload = $this->jsonPayload($content);
        if (! $payload) {
            return null;
        }

        if (isset($payload['message'])) {
            throw new NrsApiException('NRS returned message: '.$payload['message'], 502);
        }

        if ($this->isInvoiceMetadataPayload($payload)) {
            throw new NrsApiException(
                'NRS returned invoice metadata instead of a downloadable invoice artifact. Confirm the invoice has reached the NRS downloadable state, then retry the download endpoint.',
                502,
                [
                    'response_kind' => 'invoice_metadata',
                    'irn' => $payload['irn'] ?? null,
                    'business_id' => $payload['business_id'] ?? null,
                    'payment_status' => $payload['payment_status'] ?? null,
                ]
            );
        }

        $envelope = $payload['data'] ?? null;
        if (! is_array($envelope) || ! isset($envelope['iv_hex'], $envelope['pub'], $envelope['data'])) {
            return null;
        }

        $decrypted = $this->decryptNrsEnvelope(
            (string) $envelope['iv_hex'],
            (string) $envelope['pub'],
            (string) $envelope['data'],
        );

        return $this->unwrapArtifactContent($decrypted);
    }

    private function decryptNrsEnvelope(string $ivHex, string $pub, string $encryptedPayload): ?string
    {
        $apiKey = (string) config('nrs.api_key');
        $apiKeyPrefix = explode('-', $apiKey)[0] ?? '';
        $key = $pub.$apiKeyPrefix;

        if ($apiKeyPrefix === '' || strlen($key) !== 32) {
            throw new NrsApiException(
                'NRS returned an encrypted invoice artifact, but the configured API key cannot produce the required AES-256-CFB decryption key.',
                422,
                [
                    'envelope' => 'encrypted',
                    'expected_key_length' => 32,
                    'actual_key_length' => strlen($key),
                ]
            );
        }

        $iv = hex2bin($ivHex);
        if ($iv === false || strlen($iv) !== 16) {
            throw new NrsApiException(
                'NRS returned an encrypted invoice artifact with an invalid IV.',
                422,
                [
                    'envelope' => 'encrypted',
                    'expected_iv_length' => 16,
                    'actual_iv_length' => $iv === false ? 0 : strlen($iv),
                ]
            );
        }

        $cipherText = $this->base64UrlDecode($encryptedPayload);
        if ($cipherText === null) {
            throw new NrsApiException(
                'NRS returned an encrypted invoice artifact that could not be decoded.',
                422,
                ['envelope' => 'encrypted']
            );
        }

        $decrypted = openssl_decrypt($cipherText, 'AES-256-CFB', $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            throw new NrsApiException(
                'NRS encrypted invoice artifact could not be decrypted.',
                422,
                ['envelope' => 'encrypted']
            );
        }

        return $decrypted;
    }

    private function unwrapArtifactContent(string $content): string
    {
        if ($this->isPdf($content) || $this->isXml($content)) {
            return $content;
        }

        $dataUriContent = $this->decodePdfDataUri($content);
        if ($dataUriContent !== null) {
            return $dataUriContent;
        }

        $base64Content = $this->decodePdfString($content);
        if ($base64Content !== null) {
            return $base64Content;
        }

        $payload = $this->jsonPayload($content);
        if ($payload) {
            if ($this->isInvoiceMetadataPayload($payload)) {
                throw new NrsApiException(
                    'NRS encrypted invoice artifact decrypted to invoice metadata, not a PDF. The download endpoint is not returning an official PDF artifact for this IRN.',
                    502,
                    [
                        'envelope' => 'encrypted',
                        'decryption' => 'succeeded',
                        'response_kind' => 'invoice_metadata',
                        'irn' => $payload['irn'] ?? null,
                        'business_id' => $payload['business_id'] ?? null,
                        'payment_status' => $payload['payment_status'] ?? null,
                    ]
                );
            }

            foreach (['pdf', 'file', 'artifact', 'document', 'content', 'data'] as $key) {
                if (! isset($payload[$key]) || ! is_string($payload[$key])) {
                    continue;
                }

                $decoded = $this->decodePdfDataUri($payload[$key]) ?? $this->decodePdfString($payload[$key]);
                if ($decoded !== null) {
                    return $decoded;
                }
            }
        }

        throw new NrsApiException(
            'NRS encrypted invoice artifact decrypted successfully, but the decrypted payload is not a PDF artifact.',
            502,
            [
                'envelope' => 'encrypted',
                'decryption' => 'succeeded',
                'decrypted_size' => strlen($content),
                'decrypted_magic_hex' => bin2hex(substr($content, 0, 16)),
                'decrypted_prefix' => $this->safeAsciiPrefix($content),
            ]
        );
    }

    private function decodePdfDataUri(string $value): ?string
    {
        $trimmed = trim($value);
        if (! str_starts_with($trimmed, 'data:application/pdf;base64,')) {
            return null;
        }

        return $this->decodePdfString(substr($trimmed, strlen('data:application/pdf;base64,')));
    }

    private function decodePdfString(string $value): ?string
    {
        $decoded = $this->base64UrlDecode(trim($value));

        return $decoded !== null && $this->isPdf($decoded) ? $decoded : null;
    }

    private function base64UrlDecode(string $value): ?string
    {
        $base64 = strtr($value, '-_', '+/');
        $padding = strlen($base64) % 4;
        if ($padding > 0) {
            $base64 .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($base64, true);

        return $decoded === false ? null : $decoded;
    }

    private function jsonPayload(string $content): ?array
    {
        try {
            $payload = json_decode($content, true);
        } catch (\Throwable) {
            return null;
        }

        return is_array($payload) ? $payload : null;
    }

    private function isInvoiceMetadataPayload(array $payload): bool
    {
        if (! isset($payload['business_id'], $payload['irn'])) {
            return false;
        }

        $isTopLevelEnvelope = isset($payload['iv_hex'], $payload['pub'], $payload['data']) && is_string($payload['data']);
        $isNestedEnvelope = isset($payload['data']) && is_array($payload['data']) && isset($payload['data']['iv_hex'], $payload['data']['pub'], $payload['data']['data']) && is_string($payload['data']['data']);

        return ! ($isTopLevelEnvelope || $isNestedEnvelope);
    }

    private function isPdf(string $content): bool
    {
        return str_starts_with(ltrim($content), '%PDF-');
    }

    private function isXml(string $content): bool
    {
        $trimmed = ltrim($content);

        return str_starts_with($trimmed, '<?xml') || str_starts_with($trimmed, '<Invoice');
    }

    private function safeAsciiPrefix(string $content): string
    {
        $prefix = substr($content, 0, 80);

        return preg_replace('/[^\x20-\x7E]/', '.', $prefix) ?? '';
    }
}
