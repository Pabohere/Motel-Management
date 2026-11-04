<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StayExtraBed extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'nights' => 'integer', 'rate' => 'integer', 'returned_at' => 'datetime'];
    }

    public function stay(): BelongsTo
    {
        return $this->belongsTo(Stay::class)->withTrashed();
    }
}
