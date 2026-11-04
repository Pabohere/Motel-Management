<?php

namespace App\Services;

use App\Models\Payment;
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
            $data['payment_term'] = $data['payment_term'] ?? 'immediate';
            $fingerprint = SubmissionKey::fingerprint($data, ['room_id', 'guest_name', 'gender', 'phone', 'check_in_at', 'expected_check_out_at', 'room_charge', 'deposit', 'method', 'payment_term', 'notes', 'receipt_hash', 'identity_hashes']);
            if (! empty($data['request_key']) && ($existing = Stay::withTrashed()->where('request_key', $data['request_key'])->first())) {
                SubmissionKey::requireSame($existing, $fingerprint);

                return $existing;
            }

            self::require($room->status === 'available' && ! $room->activeStay()->exists(), __('အားလပ်သောအခန်းကိုသာ ရွေးနိုင်ပါသည်။'));
            app(RoomAvailability::class)->ensureNoReservationOverlap($room, $data['check_in_at'], $data['expected_check_out_at'], $data['reservation_id'] ?? null);
            $data['payment_term'] = $data['payment_term'] ?? 'immediate';
            if (! in_array($data['payment_term'], ['immediate', 'credit'], true)) {
                throw ValidationException::withMessages(['payment_term' => __('Choose immediate or credit payment.')]);
            }
            if ($data['payment_term'] === 'immediate' && (int) ($data['deposit'] ?? 0) !== (int) $data['room_charge']) {
                throw ValidationException::withMessages(['deposit' => __('Immediate payment requires the full room charge. Choose credit for partial payment.')]);
            }
            $stay = Stay::create([
                ...collect($data)->only(['guest_name', 'gender', 'phone', 'notes', 'check_in_at', 'expected_check_out_at', 'room_charge'])->all(),
                'room_id' => $room->id, 'active_room_id' => $room->id, 'created_by' => auth()->id(),
                'source' => $data['source'] ?? 'walk_in', 'payment_term' => $data['payment_term'] ?? 'immediate',
                'reservation_id' => $data['reservation_id'] ?? null,
                'request_key' => $data['request_key'] ?? null, 'request_fingerprint' => $fingerprint,
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
                $this->payment($stay, ['amount' => $data['deposit'], 'method' => $data['method'], 'note' => 'Check-in payment', 'receipt_path' => $data['receipt_path'] ?? null]);
            }

            return $stay;
        }, 3);
    }

    public function charge(Stay $stay, array $data): void
    {
        DB::transaction(function () use ($stay, $data) {
            $locked = Stay::lockForUpdate()->findOrFail($stay->id);
            self::require($locked->check_out_at === null, __('ထွက်ပြီးသောဧည့်သည်အတွက် အပိုငွေမထည့်နိုင်ပါ။'));
            $charge = $locked->charges()->create([...$data, 'created_by' => auth()->id()]);
            Audit::record('charge.created', $charge, null, $charge->toArray());
        }, 3);
    }

    public function payment(Stay $stay, array $data): Payment
    {
        $data = validator($data, ['amount' => 'required|integer|min:1|max:100000000', 'method' => 'required|in:cash,transfer,card', 'note' => 'nullable|string|max:255', 'reference' => 'nullable|string|max:120', 'request_key' => 'nullable|uuid', 'receipt_path' => 'nullable|string', 'receipt_hash' => 'nullable|string'])->validate();
        $data['amount'] = (int) $data['amount'];

        return DB::transaction(function () use ($stay, $data): Payment {
            $locked = Stay::withTrashed()->lockForUpdate()->findOrFail($stay->id);
            abort_if($locked->trashed() && ! auth()->user()->isManager(), 403);
            $fingerprint = SubmissionKey::fingerprint([...$data, 'stay_id' => $locked->id], ['stay_id', 'amount', 'method', 'note', 'reference', 'receipt_hash']);
            if (! empty($data['request_key']) && ($existing = Payment::where('request_key', $data['request_key'])->first())) {
                SubmissionKey::requireSame($existing, $fingerprint);

                return $existing;
            }
            self::require($data['amount'] <= $locked->balance, __('ပေးငွေသည် ကျန်ငွေထက် မများရပါ။'));
            $payment = $locked->payments()->create([...collect($data)->only(['amount', 'method', 'note', 'reference', 'request_key', 'receipt_path'])->all(), 'request_fingerprint' => $fingerprint, 'created_by' => auth()->id()]);
            Audit::record('payment.created', $payment, null, $payment->toArray());

            return $payment;
        }, 3);
    }

    public function reversePayment(Stay $stay, Payment $payment, array $data): Payment
    {
        abort_unless(auth()->user()->isManager(), 403);
        $data = validator($data, ['amount' => 'required|integer|min:1|max:100000000', 'reason' => 'required|string|min:3|max:255', 'request_key' => 'required|uuid'])->validate();
        $data['amount'] = (int) $data['amount'];

        return DB::transaction(function () use ($stay, $payment, $data): Payment {
            $lockedStay = Stay::withTrashed()->lockForUpdate()->findOrFail($stay->id);
            $original = Payment::lockForUpdate()->findOrFail($payment->id);
            abort_unless($original->stay_id === $lockedStay->id, 404);
            $fingerprint = SubmissionKey::fingerprint([...$data, 'payment_id' => $original->id], ['payment_id', 'amount', 'reason']);
            if ($existing = Payment::where('request_key', $data['request_key'])->first()) {
                SubmissionKey::requireSame($existing, $fingerprint);

                return $existing;
            }
            self::require($original->amount > 0 && ! $original->reversal_of_id, __('Only an original payment can be reversed.'));
            $remaining = $original->amount + (int) $original->reversals()->sum('amount');
            self::require($data['amount'] <= $remaining && $data['amount'] <= $lockedStay->paid, __('The refund exceeds the remaining original payment.'));
            $reversal = $lockedStay->payments()->create(['amount' => -$data['amount'], 'method' => $original->method, 'reversal_of_id' => $original->id, 'note' => $data['reason'], 'reference' => $original->reference, 'created_by' => auth()->id(), 'request_key' => $data['request_key'], 'request_fingerprint' => $fingerprint]);
            Audit::record('payment.reversed', $reversal, null, ['original_payment_id' => $original->id, 'amount' => $reversal->amount, 'reason' => $data['reason'], 'balance_after' => $lockedStay->fresh()->balance]);

            return $reversal;
        }, 3);
    }

    public function checkOut(Stay $stay): void
    {
        DB::transaction(function () use ($stay) {
            // All room/stay transitions use the same lock order.
            $room = Room::lockForUpdate()->findOrFail($stay->room_id);
            $locked = Stay::lockForUpdate()->findOrFail($stay->id);
            self::require($locked->check_out_at === null, __('ဤဧည့်သည်ကို Check-out လုပ်ပြီးပါပြီ။'));
            self::require($locked->balance === 0 || $locked->payment_term === 'credit', __('ကျန်ငွေကို အရင်ရှင်းပေးပါ။'));
            $before = $locked->toArray();
            $locked->update(['check_out_at' => now(), 'active_room_id' => null]);
            Audit::record('stay.check_out', $locked, $before, $locked->toArray());
            if ($locked->reservation_id) {
                $booking = $locked->reservation()->lockForUpdate()->firstOrFail();
                $reservationBefore = $booking->toArray();
                $booking->update(['status' => 'completed']);
                Audit::record('reservation.completed', $booking, $reservationBefore, $booking->toArray());
            }
            $before = $room->toArray();
            $room->update(['status' => 'dirty', 'housekeeping_by' => null, 'cleaning_started_at' => null, 'cleaned_at' => null, 'inspected_at' => null]);
            Audit::record('room.dirty', $room, $before, $room->toArray());
        }, 3);
    }

    public function roomStatus(Room $room, string $status): void
    {
        DB::transaction(function () use ($room, $status) {
            $locked = Room::lockForUpdate()->findOrFail($room->id);
            self::require(! $locked->activeStay()->exists() && $locked->status !== 'occupied', __('ဧည့်သည်ရှိသောအခန်းကို မပြောင်းနိုင်ပါ။'));
            $transitions = ['available' => ['dirty', 'maintenance'], 'dirty' => ['cleaning', 'maintenance'], 'cleaning' => ['cleaned', 'dirty', 'maintenance'], 'cleaned' => ['inspected', 'dirty', 'maintenance'], 'inspected' => ['available', 'dirty', 'maintenance'], 'maintenance' => ['dirty']];
            self::require(in_array($status, $transitions[$locked->status] ?? [], true), __('Complete cleaning and inspection before marking a room ready.'));
            if (! auth()->user()->isManager()) {
                self::require(in_array($status, ['cleaning', 'cleaned'], true), __('A manager must inspect the room and mark it ready.'));
            }
            if ($status === 'maintenance') {
                self::require(! $locked->reservations()->where('status', 'booked')->where('departure_at', '>', now())->exists(), __('Move or cancel future reservations before placing this room in maintenance.'));
            }
            $before = $locked->toArray();
            $changes = ['status' => $status];
            if ($status === 'dirty') {
                $changes += ['housekeeping_by' => null, 'cleaning_started_at' => null, 'cleaned_at' => null, 'inspected_at' => null];
            }
            if ($status === 'cleaning') {
                $changes += ['housekeeping_by' => auth()->id(), 'cleaning_started_at' => now(), 'cleaned_at' => null, 'inspected_at' => null];
            }
            if ($status === 'cleaned') {
                $changes['cleaned_at'] = now();
            }
            if ($status === 'inspected') {
                $changes['inspected_at'] = now();
            }
            $locked->update($changes);
            Audit::record('room.'.$status, $locked, $before, $locked->toArray());
        }, 3);
    }
}
