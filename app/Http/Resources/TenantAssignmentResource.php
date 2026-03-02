<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_id' => $this->contract_id,
            'user_id' => $this->user_id,
            'is_primary' => (bool) $this->is_primary,
            'joined_at' => $this->joined_at?->toISOString(),
            'contract' => $this->whenLoaded('contract', function () {
                return [
                    'id' => $this->contract?->id,
                    'contract_number' => $this->contract?->contract_number,
                    'status' => $this->contract?->status,
                    'start_date' => $this->contract?->start_date?->toDateString(),
                    'end_date' => $this->contract?->end_date?->toDateString(),
                    'property' => $this->contract?->relationLoaded('property') ? [
                        'id' => $this->contract?->property?->id,
                        'name' => $this->contract?->property?->name,
                        'type' => $this->contract?->property?->type,
                        'city' => $this->contract?->property?->city,
                    ] : null,
                ];
            }),
            'tenant' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user?->id,
                    'name' => $this->user?->name,
                    'surname' => $this->user?->surname,
                    'email' => $this->user?->email,
                    'phone' => $this->user?->phone,
                ];
            }),
        ];
    }
}
