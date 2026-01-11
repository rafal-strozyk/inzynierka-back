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
            'nazwa' => $this->name,

            'adres' => trim(sprintf('%s %s%s, m. %s',
            $this -> street, $this -> street_number, '', $this-> apartment_number ?? '-')),

            'miasto' => $this-> city,
            'czynsz' => (float) $this -> rent_cost,
            'media' => (float) $this -> utilities_cost,

            'balkon' => (bool) $this -> has_balcony,
        ];
    }
}
