<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Throwable;

class NrsApiException extends Exception
{
    protected ?array $details = null;

    public function __construct(string $message, int $code = 0, ?array $details = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->details = $details;
    }

    public function getDetails(): ?array
    {
        return $this->details;
    }

    public function render($request): JsonResponse
    {
        $status = $this->getCode() >= 400 && $this->getCode() < 600 ? $this->getCode() : 422;

        return response()->json([
            'code' => 'NRS_API_ERROR',
            'message' => $this->getMessage(),
            'details' => $this->details,
            'retryable' => in_array($status, [408, 429, 500, 502, 503, 504], true),
        ], $status);
    }
}
