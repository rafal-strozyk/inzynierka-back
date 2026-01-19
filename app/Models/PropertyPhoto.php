<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyPhoto extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'property_id',
        'file_path',
        'file_name',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
