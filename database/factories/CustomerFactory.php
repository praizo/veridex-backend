<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'type' => 'business',
            'tin' => fake()->numerify('########'),
            'email' => fake()->safeEmail(),
            'telephone' => fake()->phoneNumber(),
            'street_name' => fake()->streetAddress(),
            'city_name' => fake()->city(),
            'postal_zone' => fake()->postcode(),
            'country_code' => 'NG',
        ];
    }
}
