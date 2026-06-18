<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'stats' => $this['stats'],
            'recent_activity' => ActivityLogResource::collection($this['recent_activity'])->resolve($request),
        ];
    }
}
