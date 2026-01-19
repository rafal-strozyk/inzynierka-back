<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'name',
        'room_number',
        'area',
        'rent_cost',
        'status',
    ];

    protected $casts = [
        'area' => 'decimal:2',
        'rent_cost' => 'decimal:2',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function photos()
    {
        return $this->hasMany(RoomPhoto::class);
    }
}
