<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['arrival_at' => 'datetime', 'departure_at' => 'datetime', 'room_charge' => 'integer'];
    }

    public function room()
    {
        return $this->belongsTo(Room::class)->withTrashed();
    }

    public function stay()
    {
        return $this->hasOne(Stay::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
