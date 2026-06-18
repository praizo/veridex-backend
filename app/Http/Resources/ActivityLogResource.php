<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'subject_type' => $this->subject_type ? class_basename($this->subject_type) : null,
            'subject_id' => $this->subject_id,
            'user' => $this->whenLoaded('user', function () {
                return $this->user ? [
                    'id' => $this->user->uuid,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ] : null;
            }),
            'organization' => $this->whenLoaded('organization', function () {
                return $this->organization ? [
                    'id' => $this->organization->uuid,
                    'name' => $this->organization->name,
                ] : null;
            }),
            'created_at' => $this->created_at,
        ];
    }
}
