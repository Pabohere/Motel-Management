<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Floor extends Model
{
    protected $fillable = ['number'];

    protected function casts(): array
    {
        return ['number' => 'integer'];
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }
}
