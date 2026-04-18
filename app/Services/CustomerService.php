<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\DTOs\Customer\CreateCustomerDTO;
use App\DTOs\Product\CreateProductDTO;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public function create(array $data, int $organizationId): Customer
    {
        return Customer::create(array_merge($data, [
            'organization_id' => $organizationId
        ]));
    }

    public function update(Customer $customer, array $data): bool
    {
        return $customer->update($data);
    }
}

// I'll keep them separate but I can create both here for speed.
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
