<?php

namespace App\Services;

use App\Models\Customer;

class CustomerService
{
    public function create(array $data, int $organizationId): Customer
    {
        return Customer::create(array_merge($data, [
            'organization_id' => $organizationId,
        ]));
    }

    public function update(Customer $customer, array $data): bool
    {
        return $customer->update($data);
    }
}
