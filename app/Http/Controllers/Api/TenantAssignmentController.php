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
     *
     * @urlParam assignment int required ID przypisania. Example: 1
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
