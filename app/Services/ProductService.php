<?php

namespace App\Services;

use App\Models\Product;

class ProductService
{
    public function create(array $data, int $organizationId): Product
    {
        return Product::create(array_merge($data, [
            'organization_id' => $organizationId
        ]));
    }

    public function update(Product $product, array $data): bool
    {
        return $product->update($data);
    }
}
