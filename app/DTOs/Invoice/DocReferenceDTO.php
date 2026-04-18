<?php

namespace App\DTOs\Invoice;

final readonly class DocReferenceDTO
{
    public function __construct(
        public string $reference_type,
        public string $document_id,
        public ?string $issue_date = null,
        public ?string $document_type_code = null,
        public ?string $document_description = null,
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}