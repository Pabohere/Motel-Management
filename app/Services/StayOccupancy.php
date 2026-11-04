<?php

namespace App\Services;

use App\Models\Room;
use App\Models\Stay;
use App\Models\StayExtraBed;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StayOccupancy
{
    public static function rules(bool $withBeds = true): array
    {
        return [
            'companions' => 'sometimes|array|max:49',
            'companions.*' => 'array:id,name,gender,phone,type,age',
            'companions.*.id' => 'nullable|integer|distinct',
            'companions.*.name' => 'required|string|max:150',
            'companions.*.gender' => 'nullable|in:male,female',
            'companions.*.phone' => 'nullable|string|max:40',
            'companions.*.type' => 'required|in:adult,child',
            'companions.*.age' => 'nullable|integer|between:0,120',
            ...($withBeds ? [
                'extra_beds' => 'sometimes|integer|between:0,10',
                'extra_bed_nights' => 'required_with:extra_beds|integer|between:1,3650',
                'extra_bed_rate' => 'required_with:extra_beds|integer|between:0,100000000',
            ] : []),
        ];
    }

    public function validateCapacity(Room $room, int $people, int $beds): void
    {
        if ($beds > $room->max_extra_beds || $beds < 0) {
            throw ValidationException::withMessages(['extra_beds' => __('The extra-bed quantity exceeds this room’s limit.')]);
        }
        if ($people > $room->capacity + $beds) {
            throw ValidationException::withMessages(['companions' => __('Adults and children together exceed the room capacity, including extra beds.')]);
        }
    }

    public function bedTotal(int $quantity, int $nights, int $rate): int
    {
        $total = $quantity * $nights * $rate;
        if ($total > 100000000) {
            throw ValidationException::withMessages(['extra_bed_rate' => __('Extra-bed charges cannot exceed 100,000,000 MMK.')]);
        }

        return $total;
    }

    /** Caller holds the room and stay locks in a transaction. */
    public function syncGuests(Stay $stay, array $data): void
    {
        $primary = $stay->guests()->where('is_primary', true)->first();
        $values = ['name' => $stay->guest_name, 'gender' => $stay->gender, 'phone' => $stay->phone, 'type' => 'adult', 'is_primary' => true];
        $before = $primary?->toArray();
        if ($primary) {
            $primary->update($values);
        } else {
            $primary = $stay->guests()->create($values);
            $stay->identityDocuments()->whereNull('stay_guest_id')->update(['stay_guest_id' => $primary->id]);
        }
        if ($before !== $primary->toArray()) {
            Audit::record('guest.updated', $primary, $before, $primary->toArray());
        }
        if (! array_key_exists('companions', $data)) {
            return;
        }
        $kept = [];
        foreach ($data['companions'] as $index => $guest) {
            $record = empty($guest['id']) ? null : $stay->guests()->where('is_primary', false)->find($guest['id']);
            if (! empty($guest['id']) && ! $record) {
                throw ValidationException::withMessages(["companions.$index.id" => __('Choose a guest belonging to this stay.')]);
            }
            $values = collect($guest)->only(['name', 'gender', 'phone', 'type', 'age'])->all();
            $before = $record?->toArray();
            if ($record) {
                $record->update($values);
            } else {
                $record = $stay->guests()->create([...$values, 'is_primary' => false]);
            }
            $kept[] = $record->id;
            Audit::record('guest.updated', $record, $before, $record->toArray());
        }
        foreach ($stay->guests()->where('is_primary', false)->whereNotIn('id', $kept)->get() as $removed) {
            $before = $removed->toArray();
            $removed->delete();
            Audit::record('guest.removed', $removed, $before, $removed->toArray());
        }
    }

    public function addBeds(Stay $stay, array $data): StayExtraBed
    {
        $data = validator($data, [
            'quantity' => 'required|integer|between:1,10', 'nights' => 'required|integer|between:1,3650',
            'rate' => 'required|integer|between:0,100000000', 'request_key' => 'nullable|uuid',
        ])->validate();

        return DB::transaction(function () use ($stay, $data): StayExtraBed {
            $room = Room::lockForUpdate()->findOrFail($stay->room_id);
            $locked = Stay::lockForUpdate()->findOrFail($stay->id);
            if (! empty($data['request_key']) && ($existing = StayExtraBed::where('request_key', $data['request_key'])->first())) {
                if ($existing->stay_id !== $locked->id || $existing->quantity !== (int) $data['quantity'] || $existing->nights !== (int) $data['nights'] || $existing->rate !== (int) $data['rate']) {
                    throw ValidationException::withMessages(['request_key' => __('This submission has already been used with different details.')]);
                }

                return $existing;
            }
            if ($locked->check_out_at) {
                throw ValidationException::withMessages(['operation' => __('Extra beds can only be changed during an active stay.')]);
            }
            $active = (int) $locked->extraBeds()->whereNull('returned_at')->sum('quantity');
            // Occupants are synchronized after the initial beds are allocated at check-in.
            $this->validateCapacity($room, 1, $active + (int) $data['quantity']);
            $amount = $this->bedTotal((int) $data['quantity'], (int) $data['nights'], (int) $data['rate']);
            $charge = null;
            if ($amount > 0) {
                $charge = $locked->charges()->create([
                    'amount' => $amount,
                    'description' => __('Extra bed').' · '.$data['quantity'].' × '.$data['nights'].' '.__('nights').' × '.$data['rate'].' MMK',
                    'created_by' => auth()->id(),
                ]);
                Audit::record('charge.created', $charge, null, $charge->toArray());
            }
            $bed = $locked->extraBeds()->create([...$data, 'charge_id' => $charge?->id, 'created_by' => auth()->id()]);
            Audit::record('extra_bed.added', $bed, null, $bed->toArray());

            return $bed;
        }, 3);
    }

    public function returnBeds(Stay $stay, StayExtraBed $bed): void
    {
        DB::transaction(function () use ($stay, $bed): void {
            $room = Room::lockForUpdate()->findOrFail($stay->room_id);
            $locked = Stay::lockForUpdate()->findOrFail($stay->id);
            $bed = StayExtraBed::lockForUpdate()->findOrFail($bed->id);
            abort_unless($bed->stay_id === $locked->id, 404);
            if ($bed->returned_at) {
                return;
            }
            if ($locked->check_out_at) {
                throw ValidationException::withMessages(['operation' => __('Extra beds can only be changed during an active stay.')]);
            }
            $remaining = (int) $locked->extraBeds()->whereNull('returned_at')->sum('quantity') - $bed->quantity;
            $this->validateCapacity($room, $locked->guest_count, $remaining);
            $before = $bed->toArray();
            $bed->update(['returned_at' => now()]);
            Audit::record('extra_bed.returned', $bed, $before, $bed->toArray());
        }, 3);
    }
}
