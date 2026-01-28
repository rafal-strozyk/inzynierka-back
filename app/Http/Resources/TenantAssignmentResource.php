<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantAssignmentResource extends JsonResource
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
            'tenant_id' => $this->tenant_id,
            'property_id' => $this->property_id,
            'room_id' => $this->room_id,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'is_active' => $this->is_active,
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
            'room' => $this->whenLoaded('room', function () {
                if (!$this->room) {
                    return null;
                }

                return [
                    'id' => $this->room->id,
                    'name' => $this->room->name,
                    'room_number' => $this->room->room_number,
                    'area' => $this->room->area !== null ? (float) $this->room->area : null,
                    'rent_cost' => $this->room->rent_cost !== null ? (float) $this->room->rent_cost : null,
                ];
            }),
            'tenant' => $this->whenLoaded('tenant', function () {
                return [
                    'id' => $this->tenant?->id,
                    'name' => $this->tenant?->name,
                    'email' => $this->tenant?->email,
                    'phone' => $this->tenant?->phone,
                ];
            }),
        ];
    }
}
