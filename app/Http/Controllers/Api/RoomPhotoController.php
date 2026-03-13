<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RoomPhotoController extends Controller
{
    /**
     * @group Media
     * @unauthenticated
     * @bodyParam photos array required Lista zdjęć.
     * @bodyParam photos.* file required Plik zdjęcia.
     * @pathParam room int ID pokoju.
     * @response 201
     * {"data":[{"id":2,"photo_name":"room.jpg"}]}
     */
    public function store(Request $request, Room $room)
    {
        $validated = $request->validate([
            'photos' => ['required', 'array', 'min:1'],
            'photos.*' => ['file', 'image', 'max:5120'],
        ]);

        $created = [];
        foreach ($validated['photos'] as $index => $file) {
            $path = $file->store("images/rooms/{$room->id}", 'public');
            $created[] = $room->photos()->create([
                'photo_name' => $file->getClientOriginalName(),
                'alt_name' => 'room-photo-' . ($index + 1),
                'path' => $path,
                'is_main' => false,
                'uploaded_at' => now(),
            ]);
        }

        return response()->json([
            'data' => collect($created)->map(function ($photo) {
                return [
                    'id' => $photo->id,
                    'photo_name' => $photo->photo_name,
                    'alt_name' => $photo->alt_name,
                    'url' => Storage::url($photo->path),
                    'is_main' => (bool) $photo->is_main,
                    'uploaded_at' => $photo->uploaded_at?->toISOString(),
                ];
            }),
        ], 201);
    }
}
