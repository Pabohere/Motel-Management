<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use SoftDeletes;

    public const STATUSES = ['available' => 'အားလပ်', 'occupied' => 'ဧည့်သည်ရှိ', 'dirty' => 'သန့်ရှင်းရေးလို', 'cleaning' => 'Cleaning in progress', 'cleaned' => 'Cleaned', 'inspected' => 'Inspected', 'maintenance' => 'ပြင်ဆင်နေ'];

    protected $attributes = ['status' => 'available'];

    protected $fillable = ['number', 'floor_id', 'room_type_id', 'rate', 'capacity', 'max_extra_beds', 'extra_bed_rate', 'status', 'housekeeping_by', 'cleaning_started_at', 'cleaned_at', 'inspected_at'];

    protected function casts(): array
    {
        return ['capacity' => 'integer', 'max_extra_beds' => 'integer', 'extra_bed_rate' => 'integer', 'rate' => 'integer', 'cleaning_started_at' => 'datetime', 'cleaned_at' => 'datetime', 'inspected_at' => 'datetime'];
    }

    public function housekeeper()
    {
        return $this->belongsTo(User::class, 'housekeeping_by');
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
