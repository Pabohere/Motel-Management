<?php

namespace App\Http\Controllers;

use App\Models\Floor;
use App\Models\Room;
use App\Models\RoomType;
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
        $rooms = Room::with(['activeStay', 'floor', 'roomType'])
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->join('floors', 'floors.id', '=', 'rooms.floor_id')
            ->orderBy('floors.number')->orderBy('rooms.number')->select('rooms.*')->get();
        $floors = Floor::orderBy('number')->get();
        $roomTypes = RoomType::orderBy('name')->get();

        return view('rooms.index', compact('rooms', 'floors', 'roomTypes'));
    }

    public function storeFloor(Request $request)
    {
        $data = $request->validate(['number' => ['required', 'integer', 'between:1,99', 'unique:floors,number']]);
        DB::transaction(function () use ($data) {
            $floor = Floor::create($data);
            Audit::record('floor.created', $floor, null, $floor->toArray());
        });

        return back()->with('success', 'အထပ်အသစ် ထည့်ပြီးပါပြီ။');
    }

    public function storeRoomType(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80', 'unique:room_types,name']]);
        DB::transaction(function () use ($data) {
            $roomType = RoomType::create($data);
            Audit::record('room_type.created', $roomType, null, $roomType->toArray());
        });

        return back()->with('success', 'အခန်းအမျိုးအစား ထည့်ပြီးပါပြီ။');
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->roomRules());
        DB::transaction(function () use ($data) {
            $room = Room::create($data);
            Audit::record('room.created', $room, null, $room->toArray());
        });

        return back()->with('success', 'အခန်းအသစ် ထည့်ပြီးပါပြီ။');
    }

    public function edit(Room $room)
    {
        $floors = Floor::orderBy('number')->get();
        $roomTypes = RoomType::orderBy('name')->get();

        return view('rooms.edit', compact('room', 'floors', 'roomTypes'));
    }

    public function update(Request $request, Room $room)
    {
        $data = $request->validate($this->roomRules($room));
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

    private function roomRules(?Room $room = null): array
    {
        return [
            'number' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('rooms', 'number')->ignore($room?->id)],
            'floor_id' => ['required', 'integer', Rule::exists('floors', 'id')],
            'room_type_id' => ['required', 'integer', Rule::exists('room_types', 'id')],
            'rate' => ['required', 'integer', 'between:1,100000000'],
        ];
    }
}
