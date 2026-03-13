<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tax extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'tax';

    protected $fillable = [
        'owner_user_id',
        'period_from',
        'period_to',
        'username',
        'contract_number',
        'tax_rate_percent',
        'income_base_amount',
        'tax_amount',
        'due_date',
        'status',
        'paid_amount',
        'payment_date',
        'paid_by',
    ];

    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'due_date' => 'date',
            'payment_date' => 'date',
            'tax_rate_percent' => 'decimal:2',
            'income_base_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
