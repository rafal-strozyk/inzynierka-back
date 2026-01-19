<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PropertyDetailsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this -> id,
            'owner_id' => $this -> owner_id,
            'name' => $this -> name,
            // 'address' => trim(sprintf('%s %s%s, m. %s',
            // $this -> street, $this -> street_number, '', $this-> apartment_number ?? '-')),
            'street' => $this-> street,
            'street_number' => $this -> street_number,
            'apartment_number' => $this -> apartment_number,
            'city' => $this-> city,
            'rent_cost' => (float) $this -> rent_cost,
            'utilities_cost' => (float) $this -> utilities_cost,
            'additional_costs' => (float) $this -> additional_costs,
            'description' => (string) $this -> description,
            'area_total' => (float) $this  -> area_total,
            'bathrooms_count' => $this -> bathrooms_count,
            'status' => $this -> status,
            'has_balcony' => (bool) $this -> has_balcony,
            'rent_by_rooms' => (bool) $this ->rent_by_rooms,
            'photos' => $this->whenLoaded('photos', function () {
                return $this->photos->map(function ($photo) {
                    return [
                        'id' => $photo->id,
                        'file_name' => $photo->file_name,
                        'url' => Storage::url($photo->file_path),
                        'uploaded_at' => $photo->uploaded_at?->toISOString(),
                    ];
                });
            }),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

        ];
    }
}
