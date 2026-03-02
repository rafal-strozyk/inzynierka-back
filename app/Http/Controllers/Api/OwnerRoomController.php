<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoomAssignmentResource;
use App\Models\Room;
use Illuminate\Http\Request;

class OwnerRoomController extends Controller
{
    public function index(Request $request)
    {
        $actor = $request->user();
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $validated = $request->validate([
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
        ]);

        $query = Room::query()->with(['contracts.contractTenants.user'])->latest();

        if (!empty($validated['property_id'])) {
            $query->where('id', $validated['property_id']);
        }

        if ($actor?->role === 'owner') {
            $query->where('owner_user_id', $actor->id);
        }

        return RoomAssignmentResource::collection($query->paginate($perPage));
    }
}
