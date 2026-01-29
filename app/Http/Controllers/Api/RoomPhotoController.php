<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RoomPhotoController extends Controller
{
    /**
     * Dodanie zdjec pokoju.
     *
     * @group Pokoje
     * @authenticated
     *
     * @urlParam room int required ID pokoju. Example: 1
     * @bodyParam photos[] file required Zdjecia do dodania.
     *
     * @response 201 {
     *  "data": [
     *    {
     *      "id": 1,
     *      "file_name": "pokoj-1.jpg",
     *      "url": "https://example.com/storage/images/rooms/1/pokoj-1.jpg",
     *      "uploaded_at": "2026-01-11T10:00:00+00:00"
     *    }
     *  ]
     * }
     */
    public function store(Request $request, Room $room)
    {
        $validated = $request->validate([
            'photos' => ['required', 'array', 'min:1'],
            'photos.*' => ['file', 'image', 'max:5120'],
        ]);

        $created = [];
        foreach ($validated['photos'] as $file) {
            $path = $file->store("images/rooms/{$room->id}", 'public');
            $created[] = $room->photos()->create([
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
