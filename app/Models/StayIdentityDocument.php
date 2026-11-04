<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StayIdentityDocument extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function stay(): BelongsTo
    {
        return $this->belongsTo(Stay::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(StayGuest::class, 'stay_guest_id')->withTrashed();
    }

    public function isImage(): bool
    {
        return in_array(strtolower(pathinfo($this->path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true);
    }
}
