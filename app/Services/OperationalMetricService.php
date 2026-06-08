<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OperationalMetricService
{
    public function increment(string $metric, int $threshold = 0, array $context = []): int
    {
        $key = 'ops_metric:'.$metric.':'.now()->format('YmdH');
        $count = Cache::increment($key);
        Cache::put($key, $count, now()->addHours(2));

        if ($threshold > 0 && $count >= $threshold) {
            Log::alert('Operational metric threshold reached', [
                'metric' => $metric,
                'count' => $count,
                'threshold' => $threshold,
                'context' => $context,
            ]);
        }

        return $count;
    }
}
