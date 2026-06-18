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
    private const DEFAULT_FRESH_TTL_SECONDS = 86400;

    private const STABLE_FRESH_TTL_DAYS = 30;

    private const STALE_TTL_DAYS = 180;

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
     * Get all valid VAT Exemptions.
     */
    public function getVatExemptions(): array
    {
        return $this->fetch('api/v1/invoice/resources/vat-exemptions', 'nrs_vat_exemptions');
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
     * Get all valid Countries.
     */
    public function getCountries(): array
    {
        $countries = $this->fetch('api/v1/invoice/resources/countries', 'nrs_countries');

        return array_map(function ($country) {
            return [
                'code' => $country['alpha_2'] ?? ($country['code'] ?? null),
                'name' => $country['name'] ?? null,
            ];
        }, $countries);
    }

    /**
     * Get all valid LGAs.
     */
    public function getLgas(): array
    {
        return $this->fetch('api/v1/invoice/resources/lgas', 'nrs_lgas');
    }

    /**
     * Get all valid States.
     */
    public function getStates(): array
    {
        return $this->fetch('api/v1/invoice/resources/states', 'nrs_states');
    }

    /**
     * Get all valid Service Codes.
     */
    public function getServiceCodes(): array
    {
        return $this->fetch('api/v1/invoice/resources/services-codes', 'nrs_service_codes');
    }

    /**
     * Centralized fetcher with caching and Sandbox-wrapping resolution.
     */
    protected function fetch(string $endpoint, string $cacheKey): array
    {
        $staleKey = $this->staleCacheKey($cacheKey);
        $fresh = Cache::get($cacheKey);

        if (is_array($fresh) && $fresh !== []) {
            Cache::put($staleKey, $fresh, now()->addDays(self::STALE_TTL_DAYS));

            return $fresh;
        }

        if ($fresh === []) {
            Cache::forget($cacheKey);
        }

        try {
            $response = $this->nrsClient->get($endpoint);
            $result = $this->extractResourceData($response->json());

            if ($result === []) {
                Log::warning("NRS Dynamic Resource Fetch Returned Empty [$endpoint]");

                return $this->staleResource($staleKey);
            }

            Cache::put($cacheKey, $result, $this->freshTtl($cacheKey));
            Cache::put($staleKey, $result, now()->addDays(self::STALE_TTL_DAYS));

            return $result;
        } catch (\Throwable $e) {
            Log::warning("NRS Dynamic Resource Fetch Failed [$endpoint]: ".$e->getMessage());

            return $this->staleResource($staleKey);
        }
    }

    protected function extractResourceData(array $body): array
    {
        // Sandbox structures can be nested: {"data": [...]} OR {"data": {"data": [...]}}
        $result = $body;
        if (isset($body['data']) && is_array($body['data'])) {
            $result = $body['data'];
        }

        if (isset($result['data']) && is_array($result['data'])) {
            $result = $result['data'];
        }

        return is_array($result) ? $result : [];
    }

    protected function staleResource(string $staleKey): array
    {
        $stale = Cache::get($staleKey);

        return is_array($stale) ? $stale : [];
    }

    protected function staleCacheKey(string $cacheKey): string
    {
        return "{$cacheKey}:stale";
    }

    protected function freshTtl(string $cacheKey): int|\DateTimeInterface
    {
        return in_array($cacheKey, ['nrs_countries', 'nrs_currencies'], true)
            ? now()->addDays(self::STABLE_FRESH_TTL_DAYS)
            : self::DEFAULT_FRESH_TTL_SECONDS;
    }
}
