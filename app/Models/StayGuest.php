<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StayGuest extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'age' => 'integer'];
    }

    public function stay(): BelongsTo
    {
        return $this->belongsTo(Stay::class)->withTrashed();
    }

    public function identityDocuments(): HasMany
    {
        return $this->hasMany(StayIdentityDocument::class);
    }
}
