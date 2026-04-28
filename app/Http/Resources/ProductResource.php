<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'sku' => $this->sku,
            'price' => $this->price,
            'unit' => $this->unit,
            'hsn_code' => $this->hsn_code,
            'product_category' => $this->product_category,
            'created_at' => $this->created_at,
        ];
    }
}
