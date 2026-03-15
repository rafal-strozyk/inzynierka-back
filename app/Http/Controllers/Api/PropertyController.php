<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PropertyDetailsResource;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;

class PropertyController extends Controller
{
    /**
     * @group Properties
     * @unauthenticated
     * @queryParam search string Search po nazwie, mieście, adresie.
     * @queryParam name string Filtr po nazwie.
     * @queryParam city string Filtr po mieście.
     * @queryParam type string Typ (`room`, `flat`).
     * @queryParam rent_min number Minimalny czynsz.
     * @queryParam rent_max number Maksymalny czynsz.
     * @queryParam has_balcony boolean Filtracja po `has_balcony`.
     * @queryParam sort_by string Sortowanie (`name`,`city`,`rent_cost`,`utilities_cost`,`type`,`created_at`).
     * @queryParam sort_dir string Kierunek sortowania (`asc`, `desc`).
     * @queryParam per_page int Liczba elementów na stronę.
     * @response 200
     * {
     *   "data": []
     * }
     */
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

    /**
     * @group Properties
     * @unauthenticated
     * @pathParam property int ID nieruchomości.
     * @response
     * {
     *   "id": 3,
     *   "name": "Apartment 1",
     *   "type": "flat"
     * }
     */
    public function show(Property $property)
    {
        return new PropertyDetailsResource($property->load('photos'));
    }

    /**
     * @group Properties
     * @authenticated
     * @bodyParam name string required Nazwa nieruchomości. Example: Apartament 101
     * @bodyParam street string required Ulica. Example: Marszałkowska
     * @bodyParam street_number string required Numer ulicy. Example: 10
     * @bodyParam apartment_number string required Numer mieszkania/lokalu. Example: 5
     * @bodyParam city string required Miasto. Example: Warszawa
     * @bodyParam postal_code string required Kod pocztowy. Example: 00-001
     * @bodyParam area number required Powierzchnia m². Example: 48.5
     * @bodyParam rooms_count number required Liczba pokoi. Example: 2
     * @bodyParam bathrooms_count number required Liczba łazienek. Example: 1
     * @bodyParam has_balcony number required Czy ma balkon. Example: 1
     * @bodyParam rent_cost number required Czynsz. Example: 2000
     * @bodyParam utilities_cost number required Koszty mediów. Example: 250
     * @bodyParam additional_costs number required Koszty dodatkowe. Example: 50
     * @bodyParam description string Opis nieruchomości.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $input = $request->all();
        if (!array_key_exists('owner_user_id', $input) && array_key_exists('owner_id', $input)) {
            $input['owner_user_id'] = $input['owner_id'];
        }

        $request->merge($input);

        $rules = $this->basePropertyRules();
        $rules['type'] = ['prohibited'];
        if ($user?->role === 'admin') {
            $rules['owner_user_id'] = ['required', Rule::exists('users', 'id')->where('role', 'owner')];
        } else {
            $rules['owner_user_id'] = ['prohibited'];
        }

        $validated = $request->validate($rules);

        if ($user?->role !== 'admin') {
            $validated['owner_user_id'] = $user?->id;
        }
        $validated['type'] = 'flat';

        try {
            $property = Property::query()->create($validated);
        } catch (QueryException $e) {
            $sqlState = (string) ($e->errorInfo[0] ?? '');
            $driverCode = (string) ($e->errorInfo[1] ?? '');
            $message = mb_strtolower($e->getMessage());

            if (
                $sqlState === '23000' && $driverCode === '1062'
                || $sqlState === '23505'
                || str_contains($message, 'duplicate entry')
            ) {
                return response()->json([
                    'message' => 'Property name already exists.',
                    'errors' => ['name' => ['A property with this name already exists.']],
                ], 409);
            }

            if (
                $sqlState === '23000' && $driverCode === '1452'
                || $sqlState === '23503'
                || str_contains($message, 'foreign key constraint fails')
                || str_contains($message, 'foreign key constraint failed')
            ) {
                return response()->json([
                    'message' => 'Invalid owner.',
                    'errors' => ['owner_user_id' => ['The selected owner does not exist.']],
                ], 422);
            }

            return response()->json([
                'message' => 'Could not create property.',
            ], 409);
        }

        return response()->json(['property' => new PropertyDetailsResource($property->load('photos'))], 201);
    }

    public function update(Request $request, Property $property): JsonResponse
    {
        $user = $request->user();

        if ($user?->role !== 'admin' && $property->owner_user_id !== $user?->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $input = $request->all();
        if (!array_key_exists('owner_user_id', $input) && array_key_exists('owner_id', $input)) {
            $input['owner_user_id'] = $input['owner_id'];
        }

        if ($user?->role !== 'admin') {
            unset($input['owner_user_id'], $input['owner_id']);
        }

        $request->replace($input);

        $rules = $this->basePropertyRules(true);
        if ($user?->role === 'admin') {
            $rules['owner_user_id'] = ['sometimes', Rule::exists('users', 'id')->where('role', 'owner')];
        }

        $validated = $request->validate($rules);

        if ($user?->role !== 'admin') {
            $validated['owner_user_id'] = $property->owner_user_id;
        }

        $property->fill($validated)->save();

        return response()->json(['property' => new PropertyDetailsResource($property->load('photos'))]);
    }

    /**
     * @group Properties
     * @authenticated
     * Aktualizacja nieruchomości.
     * @pathParam property int ID nieruchomości.
     * @bodyParam name string Nazwa nieruchomości. Example: Apartament 101
     * @bodyParam street string Ulica.
     * @bodyParam street_number string Numer.
     * @bodyParam apartment_number string Numer lokalu.
     * @bodyParam city string Miasto.
     * @bodyParam postal_code string Kod pocztowy.
     * @bodyParam area number Powierzchnia m². Example: 48.5
     * @bodyParam rooms_count number Liczba pokoi.
     * @bodyParam bathrooms_count number Liczba łazienek.
     * @bodyParam has_balcony number Czy ma balkon (0/1). Example: 1
     * @bodyParam rent_cost number Czynsz.
     * @bodyParam utilities_cost number Koszty mediów.
     * @bodyParam additional_costs number Dodatkowe koszty.
     * @bodyParam description string Opis.
     * @bodyParam owner_user_id int ID właściciela (admin).
     */

    /**
     * @group Properties
     * @authenticated
     */
    public function destroy(Request $request, Property $property): JsonResponse
    {
        $user = $request->user();

        if ($user?->role !== 'admin' && $property->owner_user_id !== $user?->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        try {
            $property->delete();
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Property cannot be deleted because it is linked to active records.',
            ], 409);
        }

        return response()->json(['message' => 'Property deleted.']);
    }

    private function basePropertyRules(bool $isUpdate = false): array
    {
        $required = $isUpdate ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:150'],
            'street' => [$required, 'string', 'max:150'],
            'street_number' => [$required, 'string', 'max:30'],
            'apartment_number' => [$required, 'string', 'max:30'],
            'city' => [$required, 'string', 'max:120'],
            'postal_code' => [$required, 'string', 'max:12'],
            'area' => [$required, 'numeric', 'min:0'],
            'rooms_count' => [$required, 'integer', 'min:1'],
            'bathrooms_count' => [$required, 'integer', 'min:1'],
            'has_balcony' => [$required, 'boolean'],
            'rent_cost' => [$required, 'numeric', 'min:0'],
            'utilities_cost' => [$required, 'numeric', 'min:0'],
            'additional_costs' => [$required, 'numeric', 'min:0'],
            'type' => [$required, Rule::in(['room', 'flat'])],
            'description' => ['nullable', 'string'],
        ];
    }
}
