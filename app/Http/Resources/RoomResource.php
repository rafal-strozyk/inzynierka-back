<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'property_id' => $this->property_id,
            'name' => $this->name,
            'room_number' => $this->room_number,
            'area' => $this->area !== null ? (float) $this->area : null,
            'rent_cost' => $this->rent_cost !== null ? (float) $this->rent_cost : null,
            'status' => $this->status,
            'property' => $this->whenLoaded('property', function () {
                return [
                    'id' => $this->property?->id,
                    'name' => $this->property?->name,
                    'street' => $this->property?->street,
                    'street_number' => $this->property?->street_number,
                    'apartment_number' => $this->property?->apartment_number,
                    'city' => $this->property?->city,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
