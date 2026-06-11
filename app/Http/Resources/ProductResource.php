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
            'name' => $this->name,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'price' => $this->unit_price,
            'unit' => $this->unit_code,
            'hsn_code' => $this->hs_code,
            'item_category' => $this->item_category,
            'product_category' => $this->tax_category,
            'tax_rate' => $this->tax_rate,
            'created_at' => $this->created_at,
        ];
    }
}
