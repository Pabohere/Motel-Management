<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use SoftDeletes;

    public const STATUSES = ['available' => 'အားလပ်', 'occupied' => 'ဧည့်သည်ရှိ', 'dirty' => 'သန့်ရှင်းရေးလို', 'maintenance' => 'ပြင်ဆင်နေ'];

    protected $attributes = ['status' => 'available'];

    protected $fillable = ['number', 'floor_id', 'room_type_id', 'rate', 'status'];

    protected function casts(): array
    {
        return ['rate' => 'integer'];
    }

    public function floor()
    {
        return $this->belongsTo(Floor::class);
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    public function stays()
    {
        return $this->hasMany(Stay::class);
    }

    public function activeStay()
    {
        return $this->hasOne(Stay::class, 'active_room_id');
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function nextReservation()
    {
        return $this->hasOne(Reservation::class)->where('status', 'booked')->oldestOfMany('arrival_at');
    }
}
