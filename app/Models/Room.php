<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    public const STATUSES = ['available' => 'အားလပ်', 'occupied' => 'ဧည့်သည်ရှိ', 'dirty' => 'သန့်ရှင်းရေးလို', 'maintenance' => 'ပြင်ဆင်နေ'];

    protected $attributes = ['status' => 'available', 'floor' => 1];

    protected $fillable = ['number', 'floor', 'type', 'rate', 'status'];

    protected function casts(): array
    {
        return ['rate' => 'integer', 'floor' => 'integer'];
    }

    public function stays()
    {
        return $this->hasMany(Stay::class);
    }

    public function activeStay()
    {
        return $this->hasOne(Stay::class, 'active_room_id');
    }
}
