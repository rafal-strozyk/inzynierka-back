<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoomDetailsResource;
use App\Http\Resources\RoomResource;
use App\Models\Property;
use App\Models\Room;
use App\Models\TenantProperty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{
    /**
     * Lista pokoi.
     *
     * Zwraca pokoje z podstawowymi danymi nieruchomosci.
     *
     * @group Pokoje
     * @authenticated
     *
     * @queryParam page int Numer strony. Example: 2
     * @queryParam per_page int Liczba rekordow na strone. Example: 10
     *
     * @apiResourceCollection App\Http\Resources\RoomResource
     * @apiResourceModel App\Models\Room
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $query = Room::query()->with('property')->latest();

        return RoomResource::collection($query->paginate($perPage));
    }

    /**
     * Lista pokoi w nieruchomosci.
     *
     * @group Pokoje
     * @authenticated
     *
     * @urlParam property int required ID nieruchomosci. Example: 1
     * @queryParam page int Numer strony. Example: 2
     * @queryParam per_page int Liczba rekordow na strone. Example: 10
     *
     * @apiResourceCollection App\Http\Resources\RoomResource
     * @apiResourceModel App\Models\Room
     */
    public function indexByProperty(Request $request, Property $property)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $query = $property->rooms()->with('property')->latest();

        return RoomResource::collection($query->paginate($perPage));
    }

    /**
     * Szczegoly pokoju.
     *
     * @group Pokoje
     * @authenticated
     *
     * @urlParam room int required ID pokoju. Example: 1
     *
     * @apiResource App\Http\Resources\RoomDetailsResource
     * @apiResourceModel App\Models\Room
     */
    public function show(Room $room)
    {
        return new RoomDetailsResource($room->load('photos'));
    }

    /**
     * Lista zdjec pokoju.
     *
     * @group Pokoje
     * @authenticated
     *
     * @urlParam room int required ID pokoju. Example: 1
     *
     * @response 200 {
     *  "data": [
     *    {
     *      "id": 1,
     *      "file_name": "pokoj-1.jpg",
     *      "url": "https://example.com/storage/images/rooms/1/pokoj-1.jpg",
     *      "uploaded_at": "2026-01-11T10:00:00+00:00"
     *    }
     *  ]
     * }
     */
    public function photos(Room $room)
    {
        $photos = $room->photos()->get()->map(function ($photo) {
            return [
                'id' => $photo->id,
                'file_name' => $photo->file_name,
                'url' => Storage::url($photo->file_path),
                'uploaded_at' => $photo->uploaded_at?->toISOString(),
            ];
        });

        return response()->json(['data' => $photos]);
    }

    /**
     * Dodanie pokoju do nieruchomosci.
     *
     * @group Pokoje
     * @authenticated
     *
     * @urlParam property int required ID nieruchomosci. Example: 1
     * @bodyParam name string required Nazwa pokoju. Example: Pokoj dzienny
     * @bodyParam room_number string Numer pokoju. Example: 2A
     * @bodyParam area number Powierzchnia w m2. Example: 12.5
     * @bodyParam rent_cost number required Czynsz za pokoj. Example: 1200
     * @bodyParam status string Status: wolny|zajęty|rezerwacja. Example: wolny
     *
     * @response 201 {
     *  "room": {
     *    "id": 1,
     *    "property_id": 1,
     *    "name": "Pokoj dzienny",
     *    "room_number": "2A",
     *    "area": 12.5,
     *    "rent_cost": 1200,
     *    "status": "wolny",
     *    "photos": [],
     *    "created_at": "2026-01-11T10:00:00+00:00",
     *    "updated_at": "2026-01-11T10:00:00+00:00"
     *  }
     * }
     * @response 403 {
     *  "message": "Forbidden."
     * }
     */
    public function store(Request $request, Property $property): JsonResponse
    {
        $accessError = $this->ensurePropertyAccess($request, $property);
        if ($accessError) {
            return $accessError;
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'room_number' => ['nullable', 'string', 'max:20'],
            'area' => ['nullable', 'numeric', 'min:0'],
            'rent_cost' => ['required', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['wolny', 'zajęty', 'rezerwacja'])],
        ]);

        $room = $property->rooms()->create($validated);

        return response()->json(['room' => new RoomDetailsResource($room->load('photos'))], 201);
    }

    /**
     * Edycja pokoju.
     *
     * @group Pokoje
     * @authenticated
     *
     * @urlParam room int required ID pokoju. Example: 1
     * @bodyParam name string Nazwa pokoju. Example: Pokoj dzienny
     * @bodyParam room_number string Numer pokoju. Example: 2A
     * @bodyParam area number Powierzchnia w m2. Example: 12.5
     * @bodyParam rent_cost number Czynsz za pokoj. Example: 1200
     * @bodyParam status string Status: wolny|zajęty|rezerwacja. Example: wolny
     *
     * @response 200 {
     *  "room": {
     *    "id": 1,
     *    "property_id": 1,
     *    "name": "Pokoj dzienny",
     *    "room_number": "2A",
     *    "area": 12.5,
     *    "rent_cost": 1200,
     *    "status": "wolny",
     *    "photos": [],
     *    "created_at": "2026-01-11T10:00:00+00:00",
     *    "updated_at": "2026-01-11T10:00:00+00:00"
     *  }
     * }
     * @response 403 {
     *  "message": "Forbidden."
     * }
     */
    public function update(Request $request, Room $room): JsonResponse
    {
        $accessError = $this->ensurePropertyAccess($request, $room->property);
        if ($accessError) {
            return $accessError;
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'room_number' => ['nullable', 'string', 'max:20'],
            'area' => ['nullable', 'numeric', 'min:0'],
            'rent_cost' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['wolny', 'zajęty', 'rezerwacja'])],
            'property_id' => ['prohibited'],
        ]);

        $room->fill($validated)->save();

        return response()->json(['room' => new RoomDetailsResource($room->load('photos'))]);
    }

    /**
     * Usuniecie pokoju.
     *
     * @group Pokoje
     * @authenticated
     *
     * @urlParam room int required ID pokoju. Example: 1
     *
     * @response 200 {
     *  "message": "Room deleted."
     * }
     * @response 403 {
     *  "message": "Forbidden."
     * }
     * @response 409 {
     *  "message": "Room has active assignments."
     * }
     */
    public function destroy(Request $request, Room $room): JsonResponse
    {
        $accessError = $this->ensurePropertyAccess($request, $room->property);
        if ($accessError) {
            return $accessError;
        }

        $hasActiveAssignments = TenantProperty::query()
            ->where('room_id', $room->id)
            ->where('is_active', true)
            ->exists();

        if ($hasActiveAssignments) {
            return response()->json(['message' => 'Room has active assignments.'], 409);
        }

        $room->delete();

        return response()->json(['message' => 'Room deleted.']);
    }

    private function ensurePropertyAccess(Request $request, Property $property): ?JsonResponse
    {
        $actor = $request->user();

        if ($actor?->role !== 'owner') {
            return null;
        }

        if ($property->owner_id !== $actor->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return null;
    }
}
