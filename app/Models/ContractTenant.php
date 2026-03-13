<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractTenant extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'contract_participants';

    protected $fillable = [
        'contract_id',
        'user_id',
        'is_primary',
        'joined_at',
        'users_username',
        'contracts_contract_number',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'joined_at' => 'datetime',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
