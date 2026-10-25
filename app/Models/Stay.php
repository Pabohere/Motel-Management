<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Stay extends Model
{
    use SoftDeletes;

    public const GENDERS = ['male' => 'ကျား', 'female' => 'မ'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['check_in_at' => 'datetime', 'expected_check_out_at' => 'datetime', 'check_out_at' => 'datetime', 'room_charge' => 'integer'];
    }

    public function room()
    {
        return $this->belongsTo(Room::class)->withTrashed();
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
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

    /** Load balance totals without hydrating the payment and charge histories. */
    public function scopeWithFinancialTotals(Builder $query, ?CarbonInterface $asOf = null): Builder
    {
        return $query
            ->withSum(['charges' => fn (Builder $charges) => $charges->when($asOf, fn (Builder $charges) => $charges->where('created_at', '<=', $asOf))], 'amount')
            ->withSum(['payments' => fn (Builder $payments) => $payments->when($asOf, fn (Builder $payments) => $payments->where('created_at', '<=', $asOf))], 'amount');
    }

    public function getTotalAttribute(): int
    {
        return $this->room_charge + (int) ($this->relationLoaded('charges') || ! array_key_exists('charges_sum_amount', $this->attributes)
            ? $this->charges->sum('amount')
            : $this->attributes['charges_sum_amount']);
    }

    public function getPaidAttribute(): int
    {
        return (int) ($this->relationLoaded('payments') || ! array_key_exists('payments_sum_amount', $this->attributes)
            ? $this->payments->sum('amount')
            : $this->attributes['payments_sum_amount']);
    }

    public function getBalanceAttribute(): int
    {
        return $this->total - $this->paid;
    }
}
