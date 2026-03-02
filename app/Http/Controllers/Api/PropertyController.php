<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PropertyDetailsResource;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PropertyController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'name' => ['nullable', 'string', 'max:150'],
            'city' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', Rule::in(['room', 'flat'])],
            'rent_min' => ['nullable', 'numeric', 'min:0', 'lte:rent_max'],
            'rent_max' => ['nullable', 'numeric', 'min:0', 'gte:rent_min'],
            'has_balcony' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', Rule::in(['name', 'city', 'rent_cost', 'utilities_cost', 'type', 'created_at'])],
            'sort_dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $query = Property::query();

        if (!empty($validated['search'])) {
            $term = '%' . $validated['search'] . '%';
            $query->where(function ($subQuery) use ($term) {
                $subQuery->where('name', 'like', $term)
                    ->orWhere('city', 'like', $term)
                    ->orWhere('street', 'like', $term)
                    ->orWhere('street_number', 'like', $term)
                    ->orWhere('apartment_number', 'like', $term);
            });
        }

        if (!empty($validated['name'])) {
            $query->where('name', 'like', '%' . $validated['name'] . '%');
        }

        if (!empty($validated['city'])) {
            $query->where('city', 'like', '%' . $validated['city'] . '%');
        }

        if (!empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (isset($validated['rent_min'])) {
            $query->where('rent_cost', '>=', $validated['rent_min']);
        }

        if (isset($validated['rent_max'])) {
            $query->where('rent_cost', '<=', $validated['rent_max']);
        }

        if (isset($validated['has_balcony'])) {
            $query->where('has_balcony', (bool) $validated['has_balcony']);
        }

        if (!empty($validated['sort_by'])) {
            $query->orderBy($validated['sort_by'], $validated['sort_dir'] ?? 'asc');
        } else {
            $query->latest();
        }

        return PropertyResource::collection($query->paginate($perPage));
    }

    public function show(Property $property)
    {
        return new PropertyDetailsResource($property->load('photos'));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $rules = $this->basePropertyRules();
        if ($user?->role === 'admin') {
            $rules['owner_user_id'] = ['required', Rule::exists('users', 'id')->where('role', 'owner')];
        } else {
            $rules['owner_user_id'] = ['prohibited'];
        }

        $validated = $request->validate($rules);

        if ($user?->role !== 'admin') {
            $validated['owner_user_id'] = $user?->id;
        }

        $property = Property::query()->create($validated);

        return response()->json(['property' => new PropertyDetailsResource($property->load('photos'))], 201);
    }

    public function update(Request $request, Property $property): JsonResponse
    {
        $user = $request->user();

        if ($user?->role !== 'admin' && $property->owner_user_id !== $user?->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $rules = $this->basePropertyRules(true);
        if ($user?->role === 'admin') {
            $rules['owner_user_id'] = ['sometimes', Rule::exists('users', 'id')->where('role', 'owner')];
        } else {
            $rules['owner_user_id'] = ['prohibited'];
        }

        $validated = $request->validate($rules);

        $property->fill($validated)->save();

        return response()->json(['property' => new PropertyDetailsResource($property->load('photos'))]);
    }

    public function destroy(Request $request, Property $property): JsonResponse
    {
        $user = $request->user();

        if ($user?->role !== 'admin' && $property->owner_user_id !== $user?->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $property->delete();

        return response()->json(['message' => 'Property deleted.']);
    }

    private function basePropertyRules(bool $isUpdate = false): array
    {
        $required = $isUpdate ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:150'],
            'street' => [$required, 'string', 'max:150'],
            'street_number' => [$required, 'string', 'max:30'],
            'apartment_number' => ['nullable', 'string', 'max:30'],
            'city' => [$required, 'string', 'max:120'],
            'postal_code' => [$required, 'string', 'max:12'],
            'area' => [$required, 'numeric', 'min:0'],
            'rooms_count' => [$required, 'integer', 'min:1'],
            'bathrooms_count' => ['nullable', 'integer', 'min:1'],
            'has_balcony' => ['nullable', 'boolean'],
            'rent_cost' => [$required, 'numeric', 'min:0'],
            'utilities_cost' => ['nullable', 'numeric', 'min:0'],
            'additional_costs' => ['nullable', 'numeric', 'min:0'],
            'type' => [$required, Rule::in(['room', 'flat'])],
            'description' => ['nullable', 'string'],
        ];
    }
}
