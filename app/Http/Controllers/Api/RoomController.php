<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoomDetailsResource;
use App\Http\Resources\RoomResource;
use App\Models\Property;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;

class RoomController extends Controller
{
    /**
     * @group Rooms
     * @unauthenticated
     * Lista wszystkich pokoi.
     * @queryParam per_page int Ilość elementów na stronie.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        return RoomResource::collection(Room::query()->latest()->paginate($perPage));
    }

    /**
     * @group Rooms
     * @unauthenticated
     * @queryParam per_page int Ilość elementów na stronę. Example: 10
     * @pathParam property int ID nieruchomości.
     */
    public function indexByProperty(Request $request, Property $property)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $query = Room::query()
            ->where('owner_user_id', $property->owner_user_id)
            ->where('city', $property->city)
            ->where('street', $property->street)
            ->where('street_number', $property->street_number)
            ->where('postal_code', $property->postal_code)
            ->latest();

        return RoomResource::collection($query->paginate($perPage));
    }

    /**
     * @group Rooms
     * @unauthenticated
     * @pathParam room int ID pokoju.
     */
    public function show(Room $room)
    {
        return new RoomDetailsResource($room->load('photos'));
    }

    /**
     * @group Rooms
     * @unauthenticated
     * @pathParam room int ID pokoju.
     * @response 200
     * {"data":[{"id":1,"name":"pokoj"}]}
     */
    public function photos(Room $room)
    {
        $photos = $room->photos()->get()->map(function ($photo) {
            return [
                'id' => $photo->id,
                'photo_name' => $photo->photo_name,
                'alt_name' => $photo->alt_name,
                'url' => Storage::url($photo->path),
                'is_main' => (bool) $photo->is_main,
                'uploaded_at' => $photo->uploaded_at?->toISOString(),
            ];
        });

        return response()->json(['data' => $photos]);
    }

    /**
     * @group Rooms
     * @authenticated
     * Tworzenie pokoju dla istniejącej nieruchomości.
     * @bodyParam name string required Nazwa pokoju. Example: Pokój 1
     * @bodyParam area number required Powierzchnia. Example: 18.5
     * @bodyParam rent_cost number required Czynsz. Example: 600
     * @pathParam property int ID nieruchomości.
     */
    public function store(Request $request, Property $property): JsonResponse
    {
        $accessError = $this->ensurePropertyAccess($request, $property);
        if ($accessError) {
            return $accessError;
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'area' => ['required', 'numeric', 'min:0'],
            'has_balcony' => ['nullable', 'boolean'],
            'rent_cost' => ['required', 'numeric', 'min:0'],
            'utilities_cost' => ['nullable', 'numeric', 'min:0'],
            'additional_costs' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],

            'owner_user_id' => ['prohibited'],
            'street' => ['prohibited'],
            'street_number' => ['prohibited'],
            'apartment_number' => ['prohibited'],
            'city' => ['prohibited'],
            'postal_code' => ['prohibited'],
            'rooms_count' => ['prohibited'],
            'bathrooms_count' => ['prohibited'],
            'type' => ['prohibited'],
        ]);

        $room = Room::query()->create([
            'owner_user_id' => $property->owner_user_id,
            'name' => $validated['name'],
            'street' => $property->street,
            'street_number' => $property->street_number,
            'apartment_number' => $property->apartment_number,
            'city' => $property->city,
            'postal_code' => $property->postal_code,
            'area' => $validated['area'],
            'rooms_count' => 1,
            'bathrooms_count' => $property->bathrooms_count,
            'has_balcony' => (bool) ($validated['has_balcony'] ?? false),
            'rent_cost' => $validated['rent_cost'],
            'utilities_cost' => $validated['utilities_cost'] ?? 0,
            'additional_costs' => $validated['additional_costs'] ?? 0,
            'description' => $validated['description'] ?? null,
            'type' => 'room',
        ]);

        return response()->json(['room' => new RoomDetailsResource($room->load('photos'))], 201);
    }

    /**
     * @group Rooms
     * @authenticated
     * @pathParam room int ID pokoju.
     * @bodyParam name string Nazwa pokoju.
     * @bodyParam area number Powierzchnia.
     * @bodyParam rent_cost number Czynsz.
     * @bodyParam utilities_cost number Koszty mediów.
     * @bodyParam additional_costs number Dodatkowe koszty.
     * @bodyParam description string Opis.
     */
    public function update(Request $request, Room $room): JsonResponse
    {
        $accessError = $this->ensureRoomAccess($request, $room);
        if ($accessError) {
            return $accessError;
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'area' => ['sometimes', 'numeric', 'min:0'],
            'rooms_count' => ['sometimes', 'integer', 'min:1'],
            'bathrooms_count' => ['sometimes', 'integer', 'min:1'],
            'has_balcony' => ['sometimes', 'boolean'],
            'rent_cost' => ['sometimes', 'numeric', 'min:0'],
            'utilities_cost' => ['sometimes', 'numeric', 'min:0'],
            'additional_costs' => ['sometimes', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'owner_user_id' => ['prohibited'],
            'type' => ['prohibited'],
        ]);

        $room->fill($validated)->save();

        return response()->json(['room' => new RoomDetailsResource($room->load('photos'))]);
    }

    /**
     * @group Rooms
     * @authenticated
     * @pathParam room int ID pokoju.
     * @response 200
     * {"message":"Room deleted."}
     */
    public function destroy(Request $request, Room $room): JsonResponse
    {
        $accessError = $this->ensureRoomAccess($request, $room);
        if ($accessError) {
            return $accessError;
        }

        try {
            $room->delete();
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Room cannot be deleted because it is linked to active records.',
            ], 409);
        }

        return response()->json(['message' => 'Room deleted.']);
    }

    private function ensurePropertyAccess(Request $request, Property $property): ?JsonResponse
    {
        $actor = $request->user();

        if ($actor?->role === 'owner' && $property->owner_user_id !== $actor->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return null;
    }

    private function ensureRoomAccess(Request $request, Room $room): ?JsonResponse
    {
        $actor = $request->user();

        if ($actor?->role === 'owner' && $room->owner_user_id !== $actor->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return null;
    }
}
