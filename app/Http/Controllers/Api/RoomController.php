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
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        return RoomResource::collection(Room::query()->latest()->paginate($perPage));
    }

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

    public function show(Room $room)
    {
        return new RoomDetailsResource($room->load('photos'));
    }

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

    public function store(Request $request, Property $property): JsonResponse
    {
        $accessError = $this->ensurePropertyAccess($request, $property);
        if ($accessError) {
            return $accessError;
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'area' => ['required', 'numeric', 'min:0'],
            'rooms_count' => ['nullable', 'integer', 'min:1'],
            'bathrooms_count' => ['nullable', 'integer', 'min:1'],
            'has_balcony' => ['nullable', 'boolean'],
            'rent_cost' => ['required', 'numeric', 'min:0'],
            'utilities_cost' => ['nullable', 'numeric', 'min:0'],
            'additional_costs' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
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
            'rooms_count' => $validated['rooms_count'] ?? 1,
            'bathrooms_count' => $validated['bathrooms_count'] ?? 1,
            'has_balcony' => (bool) ($validated['has_balcony'] ?? false),
            'rent_cost' => $validated['rent_cost'],
            'utilities_cost' => $validated['utilities_cost'] ?? 0,
            'additional_costs' => $validated['additional_costs'] ?? 0,
            'description' => $validated['description'] ?? null,
            'type' => 'room',
        ]);

        return response()->json(['room' => new RoomDetailsResource($room->load('photos'))], 201);
    }

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
