<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(function (Payment $payment): void {
            if (array_diff(array_keys($payment->getDirty()), ['receipt_path', 'updated_at'])) {
                throw new \LogicException('Payments are immutable. Create a reversal and a replacement entry.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Payments cannot be deleted. Use an audited reversal.'));
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reversal_of_id');
    }

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    public function stay()
    {
        return $this->belongsTo(Stay::class)->withTrashed();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
