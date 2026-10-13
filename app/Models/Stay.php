<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stay extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['check_in_at' => 'datetime', 'expected_check_out_at' => 'datetime', 'check_out_at' => 'datetime', 'room_charge' => 'integer'];
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function charges()
    {
        return $this->hasMany(Charge::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function identityDocuments(): HasMany
    {
        return $this->hasMany(StayIdentityDocument::class)->orderBy('id');
    }

    public function getTotalAttribute(): int
    {
        return $this->room_charge + (int) $this->charges->sum('amount');
    }

    public function getPaidAttribute(): int
    {
        return (int) $this->payments->sum('amount');
    }

    public function getBalanceAttribute(): int
    {
        return $this->total - $this->paid;
    }
}
