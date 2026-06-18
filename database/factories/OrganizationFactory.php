<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'nrs_business_id' => (string) Str::uuid7(),
            'tin' => fake()->numerify('########'),
            'email' => fake()->companyEmail(),
            'telephone' => fake()->phoneNumber(),
            'street_name' => fake()->streetAddress(),
            'city_name' => fake()->city(),
            'postal_zone' => fake()->postcode(),
            'country_code' => 'NG',
            'business_description' => fake()->sentence(),
            'service_id' => Str::upper(Str::random(8)),
        ];
    }
}
