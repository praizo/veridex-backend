<?php

namespace App\Support;

class SensitiveDataRedactor
{
    public function redact(mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->redactArray($value);
        }

        if (is_object($value)) {
            return $this->redactArray(json_decode(json_encode($value), true) ?? []);
        }

        return $value;
    }

    private function redactArray(array $data): array
    {
        $redacted = [];

        foreach ($data as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $redacted[$key] = config('audit.redacted_value', '[REDACTED]');

                continue;
            }

            $redacted[$key] = is_array($value) || is_object($value)
                ? $this->redact($value)
                : $value;
        }

        return $redacted;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', ' '], '_', $key));

        foreach (config('audit.sensitive_keys', []) as $sensitiveKey) {
            $candidate = strtolower(str_replace(['-', ' '], '_', $sensitiveKey));

            if ($normalized === $candidate || str_contains($normalized, $candidate)) {
                return true;
            }
        }

        return false;
    }
}
