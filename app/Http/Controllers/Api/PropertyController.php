<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\PropertyResource;
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
     * @responseField taras boolean Czy nieruchomość posiada taras (true/false).
     */

    public function index(){
        return PropertyResource::collection(Property::query()->latest()->paginate(10));
    }
}
