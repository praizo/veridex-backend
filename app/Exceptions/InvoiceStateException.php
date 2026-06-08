<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class InvoiceStateException extends Exception
{
    public function render($request): JsonResponse
    {
        return response()->json([
            'code' => 'INVOICE_STATE_TRANSITION_BLOCKED',
            'message' => $this->getMessage(),
            'retryable' => false,
        ], 409);
    }
}
