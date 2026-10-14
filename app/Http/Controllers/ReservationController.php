<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\Stay;
use App\Services\Audit;
use App\Services\FrontDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReservationController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(['booked', 'checked_in', 'cancelled'])]]);
        $reservations = Reservation::with(['room.floor', 'room.roomType', 'stay'])
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByRaw("CASE WHEN status = 'booked' THEN 0 ELSE 1 END")
            ->orderBy('arrival_at')->paginate(20)->withQueryString();

        return view('reservations.index', compact('reservations'));
    }

    public function create(): View
    {
        $rooms = Room::with(['floor', 'roomType'])->where('status', '!=', 'maintenance')->orderBy('number')->get();

        return view('reservations.create', compact('rooms'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'room_id' => ['required', 'integer', Rule::exists('rooms', 'id')->whereNull('deleted_at')],
            'guest_name' => 'required|string|max:150', 'gender' => ['required', Rule::in(array_keys(Stay::GENDERS))],
            'phone' => 'required|string|max:40', 'notes' => 'nullable|string|max:2000',
            'arrival_at' => 'required|date|after:now', 'departure_at' => 'required|date|after:arrival_at',
            'room_charge' => 'required|integer|min:0|max:100000000',
        ]);
        DB::transaction(function () use ($data): void {
            $room = Room::lockForUpdate()->findOrFail($data['room_id']);
            if ($room->status === 'maintenance') {
                throw ValidationException::withMessages(['room_id' => __('ပြင်ဆင်နေသောအခန်းကို ကြိုတင်ချိန်း၍မရပါ။')]);
            }
            if ($room->reservations()->where('status', 'booked')->where('arrival_at', '<', $data['departure_at'])->where('departure_at', '>', $data['arrival_at'])->exists()) {
                throw ValidationException::withMessages(['arrival_at' => __('ဤအချိန်တွင် အခန်းကို ကြိုတင်ချိန်းထားပြီးဖြစ်သည်။')]);
            }
            if ($room->activeStay()->where('expected_check_out_at', '>', $data['arrival_at'])->exists()) {
                throw ValidationException::withMessages(['arrival_at' => __('လက်ရှိတည်းခိုမှုနှင့် အချိန်တိုက်နေပါသည်။')]);
            }
            $reservation = Reservation::create([...$data, 'status' => 'booked', 'created_by' => auth()->id()]);
            Audit::record('reservation.created', $reservation, null, $reservation->toArray());
        }, 3);

        return redirect()->route('reservations.index')->with('success', __('အခန်းကြိုတင်ချိန်းထားပြီးပါပြီ။'));
    }

    public function checkIn(Reservation $reservation, FrontDesk $desk): RedirectResponse
    {
        $stay = DB::transaction(function () use ($reservation, $desk): Stay {
            $room = Room::lockForUpdate()->findOrFail($reservation->room_id);
            $booking = Reservation::lockForUpdate()->findOrFail($reservation->id);
            if ($booking->status !== 'booked' || $booking->departure_at->isPast()) {
                throw ValidationException::withMessages(['operation' => __('ဤ reservation ကို Check-in မလုပ်နိုင်ပါ။')]);
            }
            $stay = $desk->checkIn([
                'room_id' => $room->id, 'guest_name' => $booking->guest_name, 'gender' => $booking->gender,
                'phone' => $booking->phone, 'notes' => $booking->notes, 'check_in_at' => now(),
                'expected_check_out_at' => $booking->departure_at, 'room_charge' => $booking->room_charge,
                'deposit' => 0, 'method' => 'cash', 'source' => 'booking', 'payment_term' => 'credit',
                'reservation_id' => $booking->id,
            ]);
            $before = $booking->toArray();
            $booking->update(['status' => 'checked_in']);
            Audit::record('reservation.checked_in', $booking, $before, $booking->toArray());

            return $stay;
        }, 3);

        return redirect()->route('stays.show', $stay)->with('success', __('Reservation Check-in လုပ်ပြီးပါပြီ။'));
    }

    public function cancel(Reservation $reservation): RedirectResponse
    {
        DB::transaction(function () use ($reservation): void {
            $booking = Reservation::lockForUpdate()->findOrFail($reservation->id);
            if ($booking->status !== 'booked') {
                throw ValidationException::withMessages(['operation' => __('ကြိုတင်ချိန်းထားသော reservation ကိုသာ ပယ်ဖျက်နိုင်ပါသည်။')]);
            }
            $before = $booking->toArray();
            $booking->update(['status' => 'cancelled']);
            Audit::record('reservation.cancelled', $booking, $before, $booking->toArray());
        }, 3);

        return back()->with('success', __('Reservation ကို ပယ်ဖျက်ပြီးပါပြီ။'));
    }
}
