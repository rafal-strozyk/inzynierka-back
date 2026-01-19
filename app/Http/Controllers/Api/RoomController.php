<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoomDetailsResource;
use App\Models\Room;
use Illuminate\Support\Facades\Storage;

class RoomController extends Controller
{
    public function show(Room $room)
    {
        return new RoomDetailsResource($room->load('photos'));
    }

    public function photos(Room $room)
    {
        $photos = $room->photos()->get()->map(function ($photo) {
            return [
                'id' => $photo->id,
                'file_name' => $photo->file_name,
                'url' => Storage::url($photo->file_path),
                'uploaded_at' => $photo->uploaded_at?->toISOString(),
            ];
        });

        return response()->json(['data' => $photos]);
    }
}
