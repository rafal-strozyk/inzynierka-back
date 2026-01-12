<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\PropertyResource;
use App\Http\Resources\PropertyDetailsResource;
use App\Models\Property;

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

    public function index(){
        return PropertyResource::collection(Property::query()->latest()->paginate(10));
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
        return new PropertyDetailsResource($property);
    }
}
