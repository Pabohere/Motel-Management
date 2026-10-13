<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Services\Audit;
use App\Services\FrontDesk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(array_keys(Room::STATUSES))]]);
        $rooms = Room::with('activeStay')->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))->orderBy('number')->get();

        return view('rooms.index', compact('rooms'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['number' => 'required|string|max:20|unique:rooms', 'type' => 'required|string|max:80', 'rate' => 'required|integer|min:0|max:100000000']);
        DB::transaction(function () use ($data) {
            $room = Room::create($data);
            Audit::record('room.created', $room, null, $room->toArray());
        });

        return back()->with('success', 'အခန်းအသစ် ထည့်ပြီးပါပြီ။');
    }

    public function edit(Room $room)
    {
        return view('rooms.edit', compact('room'));
    }

    public function update(Request $request, Room $room)
    {
        $data = $request->validate(['number' => ['required', 'string', 'max:20', Rule::unique('rooms')->ignore($room->id)], 'type' => 'required|string|max:80', 'rate' => 'required|integer|min:0|max:100000000']);
        DB::transaction(function () use ($room, $data) {
            $locked = Room::lockForUpdate()->findOrFail($room->id);
            $before = $locked->toArray();
            $locked->update($data);
            Audit::record('room.updated', $locked, $before, $locked->toArray());
        });

        return redirect()->route('rooms.index')->with('success', 'အခန်းအချက်အလက် ပြင်ပြီးပါပြီ။');
    }

    public function status(Request $request, Room $room, FrontDesk $desk)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['available', 'dirty', 'maintenance'])]]);
        $desk->roomStatus($room, $data['status']);

        return back()->with('success', 'အခန်းအခြေအနေ ပြောင်းပြီးပါပြီ။');
    }
}
