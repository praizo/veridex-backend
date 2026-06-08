<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class NrsConnectionException extends Exception
{
    public function render($request): JsonResponse
    {
        return response()->json([
            'code' => 'NRS_CONNECTION_UNAVAILABLE',
            'message' => $this->getMessage(),
            'retryable' => true,
        ], 503);
    }
}
