<?php

namespace App\Services;

use App\Models\Room;
use App\Models\Stay;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FrontDesk
{
    private static function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['operation' => $message]);
        }
    }

    public function checkIn(array $data, array $identityPaths = []): Stay
    {
        return DB::transaction(function () use ($data, $identityPaths) {
            $room = Room::lockForUpdate()->findOrFail($data['room_id']);
            self::require($room->status === 'available' && ! $room->activeStay()->exists(), 'အားလပ်သောအခန်းကိုသာ ရွေးနိုင်ပါသည်။');
            $stay = Stay::create([
                ...collect($data)->only(['guest_name', 'phone', 'notes', 'check_in_at', 'expected_check_out_at', 'room_charge'])->all(),
                'room_id' => $room->id, 'active_room_id' => $room->id, 'created_by' => auth()->id(),
            ]);
            foreach ($identityPaths as $path) {
                $document = $stay->identityDocuments()->create(['path' => $path]);
                Audit::record('stay.identity_added', $document, null, $document->toArray());
            }
            $before = $room->toArray();
            $room->update(['status' => 'occupied']);
            Audit::record('stay.check_in', $stay, null, $stay->toArray());
            Audit::record('room.occupied', $room, $before, $room->toArray());
            if (($data['deposit'] ?? 0) > 0) {
                $this->payment($stay, ['amount' => $data['deposit'], 'method' => $data['method'], 'note' => 'Check-in payment']);
            }

            return $stay;
        }, 3);
    }

    public function charge(Stay $stay, array $data): void
    {
        DB::transaction(function () use ($stay, $data) {
            $locked = Stay::lockForUpdate()->findOrFail($stay->id);
            self::require($locked->check_out_at === null, 'ထွက်ပြီးသောဧည့်သည်အတွက် အပိုငွေမထည့်နိုင်ပါ။');
            $charge = $locked->charges()->create([...$data, 'created_by' => auth()->id()]);
            Audit::record('charge.created', $charge, null, $charge->toArray());
        }, 3);
    }

    public function payment(Stay $stay, array $data): void
    {
        DB::transaction(function () use ($stay, $data) {
            $locked = Stay::lockForUpdate()->findOrFail($stay->id);
            self::require($data['amount'] <= $locked->balance, 'ပေးငွေသည် ကျန်ငွေထက် မများရပါ။');
            $payment = $locked->payments()->create([...$data, 'created_by' => auth()->id()]);
            Audit::record('payment.created', $payment, null, $payment->toArray());
        }, 3);
    }

    public function checkOut(Stay $stay): void
    {
        DB::transaction(function () use ($stay) {
            // All room/stay transitions use the same lock order.
            $room = Room::lockForUpdate()->findOrFail($stay->room_id);
            $locked = Stay::lockForUpdate()->findOrFail($stay->id);
            self::require($locked->check_out_at === null, 'ဤဧည့်သည်ကို Check-out လုပ်ပြီးပါပြီ။');
            self::require($locked->balance === 0, 'ကျန်ငွေကို အရင်ရှင်းပေးပါ။');
            $before = $locked->toArray();
            $locked->update(['check_out_at' => now(), 'active_room_id' => null]);
            Audit::record('stay.check_out', $locked, $before, $locked->toArray());
            $before = $room->toArray();
            $room->update(['status' => 'dirty']);
            Audit::record('room.dirty', $room, $before, $room->toArray());
        }, 3);
    }

    public function roomStatus(Room $room, string $status): void
    {
        DB::transaction(function () use ($room, $status) {
            $locked = Room::lockForUpdate()->findOrFail($room->id);
            self::require(! $locked->activeStay()->exists() && $locked->status !== 'occupied', 'ဧည့်သည်ရှိသောအခန်းကို မပြောင်းနိုင်ပါ။');
            self::require(in_array($status, ['available', 'dirty', 'maintenance'], true), 'အခန်းအခြေအနေ မမှန်ပါ။');
            // Staff may only mark a dirty room clean; managers handle all other transitions.
            if (! auth()->user()->isManager()) {
                self::require($locked->status === 'dirty' && $status === 'available', 'သန့်ရှင်းရေးပြီးသည့်အခန်းကိုသာ ပြောင်းနိုင်ပါသည်။');
            }
            $before = $locked->toArray();
            $locked->update(['status' => $status]);
            Audit::record($status === 'available' ? 'room.cleaned' : 'room.status_changed', $locked, $before, $locked->toArray());
        }, 3);
    }
}
