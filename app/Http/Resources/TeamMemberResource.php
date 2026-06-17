<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TeamMemberResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->pivot?->role,
            'joined_at' => $this->pivot?->created_at,
            'status' => $this->email_verified_at ? 'active' : 'invited',
        ];
    }
}
