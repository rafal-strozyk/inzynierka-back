<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $activeContract = $this->contracts
            ->first(fn ($contract) => in_array($contract->status, ['active', 'draft'], true));

        $primaryTenant = $activeContract?->contractTenants
            ?->first(fn ($assignment) => (bool) $assignment->is_primary)
            ?->user;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'city' => $this->city,
            'street' => $this->street,
            'street_number' => $this->street_number,
            'apartment_number' => $this->apartment_number,
            'rent_cost' => (float) $this->rent_cost,
            'assignment' => $activeContract ? [
                'contract_id' => $activeContract->id,
                'contract_number' => $activeContract->contract_number,
                'status' => $activeContract->status,
                'tenant' => $primaryTenant ? [
                    'id' => $primaryTenant->id,
                    'name' => $primaryTenant->name,
                    'surname' => $primaryTenant->surname,
                    'email' => $primaryTenant->email,
                ] : null,
            ] : null,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
