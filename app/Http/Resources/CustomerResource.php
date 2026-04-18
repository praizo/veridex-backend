<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'tin'          => $this->tin,
            'email'        => $this->email,
            'phone'        => $this->phone,
            'address'      => $this->address,
            'city'         => $this->city,
            'country_code' => $this->country_code,
            'created_at'   => $this->created_at,
        ];
    }
}

// I'll also create ProductResource in the same turn for speed, but separate file is better.
