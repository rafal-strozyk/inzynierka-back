<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantAssignmentResource;
use App\Models\Property;
use App\Models\Room;
use App\Models\TenantProperty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantAssignmentController extends Controller
{
    /**
     * Przypisanie najemcy do nieruchomosci/pokoju.
     *
     * @group Owner
     * @authenticated
     *
     * @bodyParam tenant_id int required ID najemcy (users.id). Example: 10
     * @bodyParam property_id int required ID nieruchomosci. Example: 1
     * @bodyParam room_id int ID pokoju (musi nalezec do nieruchomosci). Example: 3
     * @bodyParam start_date date required Data rozpoczecia (YYYY-MM-DD). Example: 2026-01-01
     * @bodyParam end_date date Data zakonczenia (YYYY-MM-DD). Example: 2026-12-31
     * @bodyParam is_active boolean Czy przypisanie ma byc aktywne. Example: true
     *
     * @response 201 {
     *  "assignment": {
     *    "id": 1,
     *    "tenant_id": 10,
     *    "property_id": 1,
     *    "room_id": 3,
     *    "start_date": "2026-01-01",
     *    "end_date": "2026-12-31",
     *    "is_active": true
     *  }
     * }
     * @response 403 {
     *  "message": "Forbidden."
     * }
     * @response 422 {
     *  "message": "Room already has an active assignment."
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'tenant')],
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $actor = $request->user();
        $property = Property::query()->findOrFail($validated['property_id']);
        $room = null;

        if ($actor?->role === 'owner' && $property->owner_id !== $actor->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (!empty($validated['room_id'])) {
            $room = Room::query()->findOrFail($validated['room_id']);
            if ($room->property_id !== $property->id) {
                return response()->json(['message' => 'Room does not belong to the property.'], 422);
            }
        }

        $isActive = $validated['is_active'] ?? true;

        if ($isActive && $room) {
            $hasConflict = TenantProperty::query()
                ->where('room_id', $room->id)
                ->where('is_active', true)
                ->exists();

            if ($hasConflict) {
                return response()->json(['message' => 'Room already has an active assignment.'], 422);
            }
        }

        $assignment = TenantProperty::query()->create([
            'tenant_id' => $validated['tenant_id'],
            'property_id' => $property->id,
            'room_id' => $room?->id,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'is_active' => $isActive,
        ]);

        if ($isActive && $room) {
            $room->status = 'zajęty';
            $room->save();
        }

        return response()->json([
            'assignment' => new TenantAssignmentResource($assignment->load(['property', 'room', 'tenant'])),
        ], 201);
    }

    /**
     * Odpiecie najemcy od nieruchomosci/pokoju.
     *
     * @group Owner
     * @authenticated
     *
     * @urlParam assignment int required ID przypisania. Example: 1
     * @bodyParam end_date date Data zakonczenia (YYYY-MM-DD). Example: 2026-12-31
     *
     * @response 200 {
     *  "assignment": {
     *    "id": 1,
     *    "tenant_id": 10,
     *    "property_id": 1,
     *    "room_id": 3,
     *    "start_date": "2026-01-01",
     *    "end_date": "2026-12-31",
     *    "is_active": false
     *  }
     * }
     * @response 403 {
     *  "message": "Forbidden."
     * }
     * @response 409 {
     *  "message": "Assignment already inactive."
     * }
     * @response 422 {
     *  "message": "The end date must be a date after or equal to start date."
     * }
     */
    public function destroy(Request $request, TenantProperty $assignment): JsonResponse
    {
        $actor = $request->user();
        $property = $assignment->property;

        if ($actor?->role === 'owner' && $property?->owner_id !== $actor->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (!$assignment->is_active) {
            return response()->json(['message' => 'Assignment already inactive.'], 409);
        }

        $endDateRules = ['nullable', 'date'];
        if ($assignment->start_date) {
            $endDateRules[] = 'after_or_equal:' . $assignment->start_date->toDateString();
        }

        $validated = $request->validate([
            'end_date' => $endDateRules,
        ]);

        $assignment->end_date = $validated['end_date'] ?? now()->toDateString();
        $assignment->is_active = false;
        $assignment->save();

        if ($assignment->room_id) {
            $room = $assignment->room;
            $hasActiveAssignments = TenantProperty::query()
                ->where('room_id', $assignment->room_id)
                ->where('is_active', true)
                ->exists();

            if ($room && !$hasActiveAssignments) {
                $room->status = 'wolny';
                $room->save();
            }
        }

        return response()->json([
            'assignment' => new TenantAssignmentResource($assignment->load(['property', 'room', 'tenant'])),
        ]);
    }
}
