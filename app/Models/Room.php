<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;

    protected $table = 'properties';

    public $timestamps = false;

    protected $fillable = [
        'owner_user_id',
        'name',
        'street',
        'street_number',
        'apartment_number',
        'city',
        'postal_code',
        'area',
        'rooms_count',
        'bathrooms_count',
        'has_balcony',
        'rent_cost',
        'utilities_cost',
        'additional_costs',
        'type',
        'description',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('room_type', function ($query): void {
            $query->where('type', 'room');
        });

        static::creating(function (self $room): void {
            $room->type = 'room';
        });
    }

    protected function casts(): array
    {
        return [
            'area' => 'decimal:2',
            'rent_cost' => 'decimal:2',
            'utilities_cost' => 'decimal:2',
            'additional_costs' => 'decimal:2',
            'has_balcony' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(PropertyPhoto::class, 'property_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'property_id');
    }
}
