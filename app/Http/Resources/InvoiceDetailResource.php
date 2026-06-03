<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceDetailResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);
        $data['id'] = $this->uuid;

        return $data;
    }
}
