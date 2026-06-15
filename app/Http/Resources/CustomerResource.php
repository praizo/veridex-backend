<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'first_name' => $this->first_name ? ucwords($this->first_name) : null,
            'last_name' => $this->last_name ? ucwords($this->last_name) : null,
            'name' => $this->name ? ucwords($this->name) : null,
            'type' => $this->type,
            'tin' => $this->tin,
            'email' => $this->email,
            'phone' => $this->telephone,
            'address' => $this->street_name,
            'city' => $this->city_name,
            'postal_zone' => $this->postal_zone,
            'country_code' => $this->country_code,
            'created_at' => $this->created_at,
        ];
    }
}
