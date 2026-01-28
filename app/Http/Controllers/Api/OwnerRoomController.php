<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoomAssignmentResource;
use App\Models\Room;
use Illuminate\Http\Request;

class OwnerRoomController extends Controller
{
    /**
     * Lista pokoi z przypisaniami.
     *
     * @group Owner
     * @authenticated
     *
     * @queryParam page int Numer strony. Example: 2
     * @queryParam per_page int Liczba rekordow na strone. Example: 10
     * @queryParam property_id int Filtrowanie po nieruchomosci. Example: 1
     *
     * @apiResourceCollection App\Http\Resources\RoomAssignmentResource
     * @apiResourceModel App\Models\Room
     */
    public function index(Request $request)
    {
        $actor = $request->user();
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $validated = $request->validate([
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
        ]);

        $query = Room::query()
            ->with(['property', 'activeAssignment.tenant'])
            ->latest();

        if (!empty($validated['property_id'])) {
            $query->where('property_id', $validated['property_id']);
        }

        if ($actor?->role === 'owner') {
            $query->whereHas('property', function ($subQuery) use ($actor) {
                $subQuery->where('owner_id', $actor->id);
            });
        }

        return RoomAssignmentResource::collection($query->paginate($perPage));
    }
}
