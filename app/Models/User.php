<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'email',
        'password',
        'role',
        'name',
        'surname',
        'phone',
        'address',
        'postal_code',
        'birth_date',
        'pesel',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'birth_date' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function ownedProperties(): HasMany
    {
        return $this->hasMany(Property::class, 'owner_user_id');
    }

    public function contractTenants(): HasMany
    {
        return $this->hasMany(ContractTenant::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'paid_by_user_id');
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class, 'owner_user_id');
    }

    public function ticketsCreated(): HasMany
    {
        return $this->hasMany(Ticket::class, 'created_by_user_id');
    }

    public function ticketReplies(): HasMany
    {
        return $this->hasMany(TicketReply::class, 'responded_by_user_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }
}
