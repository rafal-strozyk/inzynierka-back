<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PropertyPhotoController extends Controller
{
    /**
     * Lista zdjec nieruchomosci.
     *
     * @group Nieruchomości
     * @authenticated
     *
     * @urlParam property int required ID nieruchomosci. Example: 1
     *
     * @response 200 {
     *  "data": [
     *    {
     *      "id": 1,
     *      "file_name": "mieszkanie-1.jpg",
     *      "url": "https://example.com/storage/images/properties/1/mieszkanie-1.jpg",
     *      "uploaded_at": "2026-01-11T10:00:00+00:00"
     *    }
     *  ]
     * }
     */
    public function index(Property $property)
    {
        $photos = $property->photos()->get()->map(function ($photo) {
            return [
                'id' => $photo->id,
                'file_name' => $photo->file_name,
                'url' => Storage::url($photo->file_path),
                'uploaded_at' => $photo->uploaded_at?->toISOString(),
            ];
        });

        return response()->json(['data' => $photos]);
    }

    /**
     * Dodanie zdjec nieruchomosci.
     *
     * @group Nieruchomości
     * @authenticated
     *
     * @urlParam property int required ID nieruchomosci. Example: 1
     * @bodyParam photos[] file required Zdjecia do dodania. Example: storage/app/scribe/example.jpg
     *
     * @response 201 {
     *  "data": [
     *    {
     *      "id": 1,
     *      "file_name": "mieszkanie-1.jpg",
     *      "url": "https://example.com/storage/images/properties/1/mieszkanie-1.jpg",
     *      "uploaded_at": "2026-01-11T10:00:00+00:00"
     *    }
     *  ]
     * }
     */
    public function store(Request $request, Property $property)
    {
        $validated = $request->validate([
            'photos' => ['required', 'array', 'min:1'],
            'photos.*' => ['file', 'image', 'max:5120'],
        ]);

        $created = [];
        foreach ($validated['photos'] as $file) {
            $path = $file->store("images/properties/{$property->id}", 'public');
            $created[] = $property->photos()->create([
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'uploaded_at' => now(),
            ]);
        }

        return response()->json([
            'data' => collect($created)->map(function ($photo) {
                return [
                    'id' => $photo->id,
                    'file_name' => $photo->file_name,
                    'url' => Storage::url($photo->file_path),
                    'uploaded_at' => $photo->uploaded_at?->toISOString(),
                ];
            }),
        ], 201);
    }
}
