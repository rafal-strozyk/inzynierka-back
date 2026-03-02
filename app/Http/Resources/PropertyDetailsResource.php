<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PropertyDetailsResource extends JsonResource
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
            'area' => (float) $this->area,
            'rooms_count' => (int) $this->rooms_count,
            'bathrooms_count' => (int) $this->bathrooms_count,
            'has_balcony' => (bool) $this->has_balcony,
            'rent_cost' => (float) $this->rent_cost,
            'utilities_cost' => (float) $this->utilities_cost,
            'additional_costs' => (float) $this->additional_costs,
            'type' => $this->type,
            'description' => $this->description,
            'photos' => $this->whenLoaded('photos', function () {
                return $this->photos->map(function ($photo) {
                    return [
                        'id' => $photo->id,
                        'photo_name' => $photo->photo_name,
                        'alt_name' => $photo->alt_name,
                        'url' => Storage::url($photo->path),
                        'is_main' => (bool) $photo->is_main,
                        'uploaded_at' => $photo->uploaded_at?->toISOString(),
                    ];
                });
            }),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
