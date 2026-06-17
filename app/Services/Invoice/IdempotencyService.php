<?php

namespace App\Services\Invoice;

use App\Models\IdempotencyRecord;

class IdempotencyService
{
    public function keyFromHeaders(?string $idempotencyKey, ?string $legacyKey): ?string
    {
        return $idempotencyKey ?: $legacyKey;
    }

    public function replay(int $organizationId, string $scope, ?string $key): ?IdempotencyRecord
    {
        if (! $key) {
            return null;
        }

        return IdempotencyRecord::where('organization_id', $organizationId)
            ->where('scope', $scope)
            ->where('key', $key)
            ->whereNotNull('completed_at')
            ->first();
    }

    public function store(int $organizationId, string $scope, ?string $key, int $statusCode, array $payload): void
    {
        if (! $key) {
            return;
        }

        IdempotencyRecord::updateOrCreate([
            'organization_id' => $organizationId,
            'scope' => $scope,
            'key' => $key,
        ], [
            'status_code' => $statusCode,
            'response_payload' => $payload,
            'completed_at' => now(),
        ]);
    }
}
