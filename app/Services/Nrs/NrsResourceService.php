<?php

namespace App\Services\Nrs;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service to manage and cache dynamic resources from the FIRS NRS API.
 * Ensures that both the UI and the Backend use the same source of truth for compliance.
 */
class NrsResourceService
{
    public function __construct(
        protected NrsClient $nrsClient
    ) {}

    /**
     * Get all valid Tax Categories and their rates.
     */
    public function getTaxCategories(): array
    {
        return $this->fetch('api/v1/invoice/resources/tax-categories', 'nrs_tax_categories');
    }

    /**
     * Get all valid Currencies.
     */
    public function getCurrencies(): array
    {
        return $this->fetch('api/v1/invoice/resources/currencies', 'nrs_currencies');
    }

    /**
     * Get all valid Invoice Types.
     */
    public function getInvoiceTypes(): array
    {
        return $this->fetch('api/v1/invoice/resources/invoice-types', 'nrs_invoice_types');
    }

    /**
     * Get all valid Payment Means.
     */
    public function getPaymentMeans(): array
    {
        return $this->fetch('api/v1/invoice/resources/payment-means', 'nrs_payment_means');
    }

    /**
     * Get all valid HS Codes.
     */
    public function getHsCodes(): array
    {
        return $this->fetch('api/v1/invoice/resources/hs-codes', 'nrs_hs_codes');
    }

    /**
     * Centralized fetcher with caching and Sandbox-wrapping resolution.
     */
    protected function fetch(string $endpoint, string $cacheKey): array
    {
        return Cache::remember($cacheKey, 86400, function () use ($endpoint) {
            try {
                $response = $this->nrsClient->get($endpoint);
                $body = $response->json();

                // Sandbox structures can be nested: {"data": [...]} OR {"data": {"data": [...]}}
                $result = $body;
                if (isset($body['data']) && is_array($body['data'])) {
                    $result = $body['data'];
                }

                if (isset($result['data']) && is_array($result['data'])) {
                    $result = $result['data'];
                }

                return is_array($result) ? $result : [];
            } catch (\Exception $e) {
                Log::warning("NRS Dynamic Resource Fetch Failed [$endpoint]: ".$e->getMessage());

                return [];
            }
        });
    }
}
