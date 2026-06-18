<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'tin' => $this->tin,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'street_name' => $this->street_name,
            'city_name' => $this->city_name,
            'postal_zone' => $this->postal_zone,
            'country_code' => $this->country_code,
            'business_description' => $this->business_description,
            'service_id' => $this->service_id,
            'nrs_business_id' => $this->nrs_business_id,
            'users_count' => $this->whenCounted('users'),
            'invoices_count' => $this->whenCounted('invoices'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
