<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function assignments(): HasMany
    {
        return $this->hasMany(TenantProperty::class);
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(TenantProperty::class)
            ->where('is_active', true)
            ->orderByDesc('start_date');
    }
}
