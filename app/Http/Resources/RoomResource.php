<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'owner_user_id' => $this->owner_user_id,
            'name' => $this->name,
            'street' => $this->street,
            'street_number' => $this->street_number,
            'apartment_number' => $this->apartment_number,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'area' => $this->area !== null ? (float) $this->area : null,
            'rent_cost' => $this->rent_cost !== null ? (float) $this->rent_cost : null,
            'utilities_cost' => $this->utilities_cost !== null ? (float) $this->utilities_cost : null,
            'additional_costs' => $this->additional_costs !== null ? (float) $this->additional_costs : null,
            'type' => $this->type,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
