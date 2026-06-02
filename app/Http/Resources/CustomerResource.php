<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'tin' => $this->tin,
            'email' => $this->email,
            'phone' => $this->telephone,
            'address' => $this->street_name,
            'city' => $this->city_name,
            'country_code' => $this->country_code,
            'created_at' => $this->created_at,
        ];
    }
}