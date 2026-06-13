<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'item_type' => $this->item_type ?? 'goods',
            'name' => $this->name,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'price' => $this->unit_price,
            'unit' => $this->unit_code,
            'hsn_code' => $this->hs_code,
            'item_category' => $this->item_category,
            'isic_code' => $this->isic_code,
            'service_category' => $this->service_category,
            'product_category' => $this->tax_category,
            'tax_rate' => $this->tax_rate,
            'created_at' => $this->created_at,
        ];
    }
}
