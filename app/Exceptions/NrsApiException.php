<?php

namespace App\Exceptions;

use Exception;
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
}
