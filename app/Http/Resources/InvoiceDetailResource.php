<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceDetailResource extends JsonResource
{
    public function toArray($request): array
    {
        return parent::toArray($request); // Scaffold complete dump for details in Phase 1
    }
}
