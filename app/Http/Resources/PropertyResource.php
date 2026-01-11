<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyResource extends JsonResource
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
            'name' => $this->name,

            'address' => trim(sprintf('%s %s%s, m. %s',
            $this -> street, $this -> street_number, '', $this-> apartment_number ?? '-')),

            'city' => $this-> city,
            'rent_cost' => (float) $this -> rent_cost,
            'utilities_cost' => (float) $this -> utilities_cost,

            'has_balcony' => (bool) $this -> has_balcony,
        ];
    }
}
