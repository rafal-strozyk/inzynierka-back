<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use HasFactory;
    protected $fillable = [
        'owner_id',
        'name',
        'street',
        'street_number',
        'apartment_number',
        'city',
        'description',
        'status',
        'rent_cost',
        'utilities_cost',
        'additional_costs',
        'area_total',
        'has_balcony',
        'rent_by_rooms',
    ];

    protected $casts = [
        'rent_cost' => 'decimal:2',
        'utilities_cost' => 'decimal:2',
        'additional_costs' => 'decimal:2',
        'area_total' => 'decimal:2',

        'has_balcony' => 'boolean',
        'rent_by_rooms' => 'boolean',
    ];

    public function photos()
    {
        return $this->hasMany(PropertyPhoto::class);
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }
}
