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
     * @apiResourceCollection App\Http\Resources\PropertyResource
     * @apiResourceModel App\Models\Property
     *
     * @responseField id int ID nieruchomości.
     * @responseField nazwa string Nazwa nieruchomości.
     * @responseField adres string Adres w formacie: [ulica] [nr], m. [nr mieszkania]
     * @responseField miasto string Miasto.
     * @responseField czynsz number Czynsz (np. 123.09).
     * @responseField media number Koszt mediów (np. 123.09).
     * @responseField balkon boolean Czy nieruchomość posiada balkon (true/false).
     * 
     * @queryParam page int Numer strony. Example: 2
     * @queryParam per_page int Liczba rekordów na stronę. Example: 10
     *
     * @apiResourceCollection App\Http\Resources\PropertyResource
     * @apiResourceModel App\Models\Property
     */

    public function index(Request $request){
        $perPage= (int) $request -> query('per_page',10);
        $perPage = max(1, min($perPage,100));
        return PropertyResource::collection(Property::query()->latest()->paginate($perPage));
    }

    /**
    * Szczegóły nieruchomości.
    *
    * Zwraca komplet informacji o wskazanej nieruchomości.
    *
    * @group Nieruchomości
    *
    * @urlParam property int required ID nieruchomości. Example: 1
    *
    * @apiResource App\Http\Resources\PropertyDetailsResource
    * @apiResourceModel App\Models\Property
    *
    * @responseField id int ID nieruchomości.
    * @responseField owner_id int ID właściciela (users.id).
    * @responseField nazwa string Nazwa nieruchomości.
    * @responseField ulica string Ulica.
    * @responseField numer_budynku string Numer budynku.
    * @responseField numer_mieszkania string|null Numer mieszkania.
    * @responseField miasto string Miasto.
    * @responseField opis string|null Opis nieruchomości.
    * @responseField status string Status: wolna|zajęta|remontowana|nieaktywna.
    * @responseField czynsz number Czynsz (np. 123.09).
    * @responseField media number Koszt mediów (np. 123.09).
    * @responseField dodatkowe_koszty number Dodatkowe koszty (np. 0.00).
    * @responseField powierzchnia_m2 number|null Powierzchnia w m² (np. 35.50).
    * @responseField liczba_lazienek int|null Liczba łazienek.
    * @responseField balkon boolean Czy nieruchomość posiada balkon (true/false).
    * @responseField wynajem_na_pokoje boolean Czy wynajem dotyczy pokoi (true/false).
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
     *
     * @urlParam property int required ID nieruchomości. Example: 1
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
