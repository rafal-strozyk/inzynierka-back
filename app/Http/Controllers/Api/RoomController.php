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
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $query = Room::query()->with('property')->latest();

        return RoomResource::collection($query->paginate($perPage));
    }

    public function indexByProperty(Request $request, Property $property)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $query = $property->rooms()->with('property')->latest();

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
                'file_name' => $photo->file_name,
                'url' => Storage::url($photo->file_path),
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
            'name' => ['required', 'string', 'max:100'],
            'room_number' => ['nullable', 'string', 'max:20'],
            'area' => ['nullable', 'numeric', 'min:0'],
            'rent_cost' => ['required', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['wolny', 'zajęty', 'rezerwacja'])],
        ]);

        $room = $property->rooms()->create($validated);

        return response()->json(['room' => new RoomDetailsResource($room->load('photos'))], 201);
    }

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
