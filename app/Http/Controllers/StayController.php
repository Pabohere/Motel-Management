<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Stay;
use App\Services\Audit;
use App\Services\FrontDesk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StayController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['q' => 'nullable|string|max:100', 'status' => ['nullable', Rule::in(['active', 'completed'])]]);
        $stays = Stay::with(['room', 'charges', 'payments'])
            ->when($data['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q->where('guest_name', 'like', '%'.$term.'%')->orWhere('phone', 'like', '%'.$term.'%')))
            ->when(($data['status'] ?? '') === 'active', fn ($q) => $q->whereNull('check_out_at'))
            ->when(($data['status'] ?? '') === 'completed', fn ($q) => $q->whereNotNull('check_out_at'))
            ->latest('check_in_at')->paginate(20)->withQueryString();

        return view('stays.index', compact('stays'));
    }

    public function create(Request $request)
    {
        $rooms = Room::where('status', 'available')->orderBy('number')->get();
        $selectedRoom = $rooms->firstWhere('id', (int) $request->query('room'));

        return view('stays.create', compact('rooms', 'selectedRoom'));
    }

    public function store(Request $request, FrontDesk $desk)
    {
        $data = $request->validate([
            'room_id' => 'required|integer|exists:rooms,id', 'guest_name' => 'required|string|max:150', 'phone' => 'required|string|max:40', 'notes' => 'nullable|string|max:2000',
            'check_in_at' => 'required|date|before_or_equal:now', 'expected_check_out_at' => 'required|date|after:check_in_at|after:now',
            'room_charge' => 'required|integer|min:0|max:100000000', 'deposit' => 'required|integer|min:0|lte:room_charge',
            'method' => ['required', Rule::in(['cash', 'transfer', 'card'])],
        ]);
        $stay = $desk->checkIn($data);

        return redirect()->route('stays.show', $stay)->with('success', 'Check-in လုပ်ပြီးပါပြီ။');
    }

    public function show(Stay $stay)
    {
        $stay->load(['room', 'charges.creator', 'payments.creator']);

        return view('stays.show', compact('stay'));
    }

    public function update(Request $request, Stay $stay)
    {
        $data = $request->validate(['guest_name' => 'required|string|max:150', 'phone' => 'required|string|max:40', 'notes' => 'nullable|string|max:2000', 'expected_check_out_at' => 'required|date|after:'.$stay->check_in_at->toDateTimeString().'|after:now']);
        DB::transaction(function () use ($stay, $data) {
            $locked = Stay::lockForUpdate()->findOrFail($stay->id);
            if ($locked->check_out_at) {
                throw ValidationException::withMessages(['operation' => 'ထွက်ပြီးမှ ပြင်၍မရပါ။']);
            }
            $before = $locked->toArray();
            $locked->update($data);
            Audit::record('stay.updated', $locked, $before, $locked->toArray());
        });

        return back()->with('success', 'ဧည့်သည်အချက်အလက် ပြင်ပြီးပါပြီ။');
    }

    public function charge(Request $request, Stay $stay, FrontDesk $desk)
    {
        $data = $request->validate(['description' => 'required|string|max:255', 'amount' => 'required|integer|min:1|max:100000000']);
        $desk->charge($stay, $data);

        return back()->with('success', 'အပိုကျသင့်ငွေ ထည့်ပြီးပါပြီ။');
    }

    public function payment(Request $request, Stay $stay, FrontDesk $desk)
    {
        $data = $request->validate(['amount' => 'required|integer|min:1|max:100000000', 'method' => ['required', Rule::in(['cash', 'transfer', 'card'])], 'note' => 'nullable|string|max:255']);
        $desk->payment($stay, $data);

        return back()->with('success', 'လက်ခံရရှိငွေ မှတ်ပြီးပါပြီ။');
    }

    public function checkOut(Stay $stay, FrontDesk $desk)
    {
        $desk->checkOut($stay);

        return back()->with('success', 'Check-out လုပ်ပြီးပါပြီ။ အခန်းကို သန့်ရှင်းရေးလုပ်ရန် လိုအပ်ပါသည်။');
    }
}
