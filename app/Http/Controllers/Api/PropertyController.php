<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\PropertyResource;
use App\Http\Resources\PropertyDetailsResource;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class PropertyController extends Controller
{
    //Lista nieruchomości
    /**
     * Lista nieruchomości.
     *
     * Zwraca listę nieruchomości wraz z podstawowymi informacjami.
     *
     * @group Nieruchomości
     * @authenticated
     * @apiResourceCollection App\Http\Resources\PropertyResource
     * @apiResourceModel App\Models\Property
     *
     * @responseField id int ID nieruchomości.
     * @responseField name string Nazwa nieruchomości.
     * @responseField address string Adres w formacie: [ulica] [nr], m. [nr mieszkania]
     * @responseField city string Miasto.
     * @responseField rent_cost number Czynsz (np. 123.09).
     * @responseField utilities_cost number Koszt mediów (np. 123.09).
     * @responseField has_balcony boolean Czy nieruchomość posiada balkon (true/false).
     * 
     * @queryParam page int Numer strony. Example: 2
     * @queryParam per_page int Liczba rekordów na stronę. Example: 10
     * @queryParam search string Wyszukiwanie po nazwie/adresie/mieście. Example: Centrum
     * @queryParam name string Filtrowanie po nazwie (częściowe dopasowanie). Example: Apartament
     * @queryParam address string Filtrowanie po adresie (ulica/nr/mieszkanie). Example: Glowna 10
     * @queryParam city string Filtrowanie po mieście (częściowe dopasowanie). Example: Warszawa
     * @queryParam rent_min number Minimalny czynsz. Example: 1500
     * @queryParam rent_max number Maksymalny czynsz. Example: 3500
     * @queryParam utilities_min number Minimalne media. Example: 200
     * @queryParam utilities_max number Maksymalne media. Example: 600
     * @queryParam has_balcony boolean Filtrowanie po balkonie. Example: true
     * @queryParam sort_by string Pole sortowania: name|address|city|rent_cost|utilities_cost|has_balcony. Example: rent_cost
     * @queryParam sort_dir string Kierunek sortowania: asc|desc. Example: desc
     */

    public function index(Request $request){
        $perPage= (int) $request -> query('per_page',10);
        $perPage = max(1, min($perPage,100));

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'name' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:200'],
            'city' => ['nullable', 'string', 'max:100'],
            'rent_min' => ['nullable', 'numeric', 'min:0', 'lte:rent_max'],
            'rent_max' => ['nullable', 'numeric', 'min:0', 'gte:rent_min'],
            'utilities_min' => ['nullable', 'numeric', 'min:0', 'lte:utilities_max'],
            'utilities_max' => ['nullable', 'numeric', 'min:0', 'gte:utilities_min'],
            'has_balcony' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', Rule::in(['name', 'address', 'city', 'rent_cost', 'utilities_cost', 'has_balcony'])],
            'sort_dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $query = Property::query();

        if (!empty($validated['search'])) {
            $query->where(function ($subQuery) use ($validated) {
                $term = '%' . $validated['search'] . '%';
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

        if (!empty($validated['address'])) {
            $query->where(function ($subQuery) use ($validated) {
                $term = '%' . $validated['address'] . '%';
                $subQuery->where('street', 'like', $term)
                    ->orWhere('street_number', 'like', $term)
                    ->orWhere('apartment_number', 'like', $term);
            });
        }

        if (!empty($validated['city'])) {
            $query->where('city', 'like', '%' . $validated['city'] . '%');
        }

        if (isset($validated['rent_min'])) {
            $query->where('rent_cost', '>=', $validated['rent_min']);
        }

        if (isset($validated['rent_max'])) {
            $query->where('rent_cost', '<=', $validated['rent_max']);
        }

        if (isset($validated['utilities_min'])) {
            $query->where('utilities_cost', '>=', $validated['utilities_min']);
        }

        if (isset($validated['utilities_max'])) {
            $query->where('utilities_cost', '<=', $validated['utilities_max']);
        }

        if (isset($validated['has_balcony'])) {
            $query->where('has_balcony', (bool) $validated['has_balcony']);
        }

        if (!empty($validated['sort_by'])) {
            $direction = $validated['sort_dir'] ?? 'asc';

            if ($validated['sort_by'] === 'address') {
                $query->orderBy('street', $direction)
                    ->orderBy('street_number', $direction)
                    ->orderBy('apartment_number', $direction);
            } else {
                $query->orderBy($validated['sort_by'], $direction);
            }
        } else {
            $query->latest();
        }

        return PropertyResource::collection($query->paginate($perPage));
    }

    /**
    * Szczegóły nieruchomości.
    *
    * Zwraca komplet informacji o wskazanej nieruchomości.
    *
    * @group Nieruchomości
    * @authenticated
    *
    * @urlParam property int required ID nieruchomości. Example: 1
    *
    * @apiResource App\Http\Resources\PropertyDetailsResource
    * @apiResourceModel App\Models\Property
    *
    * @responseField id int ID nieruchomości.
    * @responseField owner_id int ID właściciela (users.id).
    * @responseField name string Nazwa nieruchomości.
    * @responseField street string Ulica.
    * @responseField street_number string Numer budynku.
    * @responseField apartment_number string|null Numer mieszkania.
    * @responseField city string Miasto.
    * @responseField description string|null Opis nieruchomości.
    * @responseField status string Status: wolna|zajęta|remontowana|nieaktywna.
    * @responseField rent_cost number Czynsz (np. 123.09).
    * @responseField utilities_cost number Koszt mediów (np. 123.09).
    * @responseField additional_costs number Dodatkowe koszty (np. 0.00).
    * @responseField area_total number|null Powierzchnia w m² (np. 35.50).
    * @responseField bathrooms_count int|null Liczba łazienek.
    * @responseField has_balcony boolean Czy nieruchomość posiada balkon (true/false).
    * @responseField rent_by_rooms boolean Czy wynajem dotyczy pokoi (true/false).
    * @responseField created_at string Data utworzenia (ISO 8601).
    * @responseField updated_at string Data aktualizacji (ISO 8601).
    */

    public function show(Property $property){
        return new PropertyDetailsResource($property->load('photos'));
    }

    /**
     * Tworzenie nieruchomości.
     *
     * @group Nieruchomości
     * @authenticated
     *
     * @bodyParam owner_id int ID właściciela (wymagane dla admina). Example: 2
     * @bodyParam name string required Nazwa nieruchomości. Example: Apartament Centrum
     * @bodyParam street string required Ulica. Example: Glowna
     * @bodyParam street_number string required Numer budynku. Example: 10
     * @bodyParam apartment_number string Numer mieszkania. Example: 12
     * @bodyParam city string required Miasto. Example: Warszawa
     * @bodyParam description string Opis nieruchomości. Example: Blisko centrum.
     * @bodyParam status string Status: wolna|zajęta|remontowana|nieaktywna. Example: wolna
     * @bodyParam rent_cost number required Czynsz (np. 123.09). Example: 2500
     * @bodyParam utilities_cost number Koszt mediów (np. 123.09). Example: 350
     * @bodyParam additional_costs number Dodatkowe koszty (np. 0.00). Example: 0
     * @bodyParam area_total number Powierzchnia w m² (np. 35.50). Example: 45.5
     * @bodyParam bathrooms_count int Liczba łazienek. Example: 1
     * @bodyParam has_balcony boolean Czy nieruchomość posiada balkon (true/false). Example: true
     * @bodyParam rent_by_rooms boolean Czy wynajem dotyczy pokoi (true/false). Example: false
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $rules = $this->basePropertyRules();

        if ($user?->role === 'admin') {
            $rules['owner_id'] = ['required', Rule::exists('users', 'id')->where('role', 'owner')];
        } else {
            $rules['owner_id'] = ['prohibited'];
        }

        $validated = $request->validate($rules);

        if ($user?->role !== 'admin') {
            $validated['owner_id'] = $user->id;
        }

        $property = Property::query()->create($validated);

        return response()->json(['property' => new PropertyDetailsResource($property->load('photos'))], 201);
    }

    /**
     * Edycja nieruchomości.
     *
     * @group Nieruchomości
     * @authenticated
     *
     * @urlParam property int required ID nieruchomości. Example: 1
     * @bodyParam owner_id int ID właściciela (tylko admin). Example: 2
     * @bodyParam name string Nazwa nieruchomości. Example: Apartament Centrum
     * @bodyParam street string Ulica. Example: Glowna
     * @bodyParam street_number string Numer budynku. Example: 10
     * @bodyParam apartment_number string Numer mieszkania. Example: 12
     * @bodyParam city string Miasto. Example: Warszawa
     * @bodyParam description string Opis nieruchomości. Example: Blisko centrum.
     * @bodyParam status string Status: wolna|zajęta|remontowana|nieaktywna. Example: wolna
     * @bodyParam rent_cost number Czynsz (np. 123.09). Example: 2500
     * @bodyParam utilities_cost number Koszt mediów (np. 123.09). Example: 350
     * @bodyParam additional_costs number Dodatkowe koszty (np. 0.00). Example: 0
     * @bodyParam area_total number Powierzchnia w m² (np. 35.50). Example: 45.5
     * @bodyParam bathrooms_count int Liczba łazienek. Example: 1
     * @bodyParam has_balcony boolean Czy nieruchomość posiada balkon (true/false). Example: true
     * @bodyParam rent_by_rooms boolean Czy wynajem dotyczy pokoi (true/false). Example: false
     */
    public function update(Request $request, Property $property): JsonResponse
    {
        $user = $request->user();

        if ($user?->role !== 'admin' && $property->owner_id !== $user?->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $rules = $this->basePropertyRules(true);

        if ($user?->role === 'admin') {
            $rules['owner_id'] = ['sometimes', Rule::exists('users', 'id')->where('role', 'owner')];
        } else {
            $rules['owner_id'] = ['prohibited'];
        }

        $validated = $request->validate($rules);

        $property->fill($validated)->save();

        return response()->json(['property' => new PropertyDetailsResource($property->load('photos'))]);
    }

    /**
     * Usuwanie nieruchomości.
     *
     * @group Nieruchomości
     * @authenticated
     *
     * @urlParam property int required ID nieruchomości. Example: 1
     */
    public function destroy(Request $request, Property $property): JsonResponse
    {
        $user = $request->user();

        if ($user?->role !== 'admin' && $property->owner_id !== $user?->id) {
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
            'street_number' => [$required, 'string', 'max:20'],
            'apartment_number' => ['nullable', 'string', 'max:20'],
            'city' => [$required, 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['wolna', 'zajęta', 'remontowana', 'nieaktywna'])],
            'rent_cost' => [$required, 'numeric', 'min:0'],
            'utilities_cost' => ['nullable', 'numeric', 'min:0'],
            'additional_costs' => ['nullable', 'numeric', 'min:0'],
            'area_total' => ['nullable', 'numeric', 'min:0'],
            'bathrooms_count' => ['nullable', 'integer', 'min:0', 'max:20'],
            'has_balcony' => ['nullable', 'boolean'],
            'rent_by_rooms' => ['nullable', 'boolean'],
        ];
    }
}
