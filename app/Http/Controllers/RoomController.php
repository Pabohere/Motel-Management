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
use Illuminate\Validation\ValidationException;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in([...array_keys(Room::STATUSES), 'archived'])],
            'floor_id' => ['nullable', 'integer', Rule::exists('floors', 'id')],
        ]);
        $status = $data['status'] ?? null;
        $floorId = $data['floor_id'] ?? null;
        if ($status === 'archived' && ! $request->user()->isManager()) {
            abort(403);
        }
        $rooms = Room::query()
            ->when($status === 'archived', fn ($q) => $q->onlyTrashed())
            ->when($status && $status !== 'archived', fn ($q) => $q->where('status', $status))
            ->when($floorId, fn ($q) => $q->where('rooms.floor_id', $floorId))
            ->join('floors', 'floors.id', '=', 'rooms.floor_id')
            ->orderBy('floors.number')->orderBy('rooms.number')->select('rooms.*')->get();
        $floors = Floor::orderBy('number')->get();
        if ($request->routeIs('rooms.all')) {
            $rooms->load(['activeStay', 'floor', 'roomType', 'nextReservation']);

            return view('rooms.all', compact('rooms', 'floors'));
        }

        $totalRooms = $rooms->count();
        $floorNumbers = $floors->pluck('number', 'id');
        $floorRoomCounts = $rooms->countBy(fn (Room $room) => $floorNumbers[$room->floor_id]);
        $rooms->load(['activeStay', 'floor', 'roomType', 'nextReservation']);
        $roomTypes = RoomType::orderBy('name')->get();

        return view('rooms.index', compact('rooms', 'floors', 'roomTypes', 'totalRooms', 'floorRoomCounts'));
    }

    public function storeFloor(Request $request)
    {
        $data = $request->validate(['number' => ['required', 'integer', 'between:1,99', 'unique:floors,number']]);
        DB::transaction(function () use ($data) {
            $floor = Floor::create($data);
            Audit::record('floor.created', $floor, null, $floor->toArray());
        });

        return back()->with('success', __('အထပ်အသစ် ထည့်ပြီးပါပြီ။'));
    }

    public function updateFloor(Request $request, Floor $floor)
    {
        $data = $request->validate(['number' => ['required', 'integer', 'between:1,99', Rule::unique('floors', 'number')->ignore($floor->id)]]);
        DB::transaction(function () use ($floor, $data): void {
            $before = $floor->toArray();
            $floor->update($data);
            Audit::record('floor.updated', $floor, $before, $floor->toArray());
        });

        return back()->with('success', __('အထပ်နံပါတ် ပြင်ပြီးပါပြီ။'));
    }

    public function destroyFloor(Floor $floor)
    {
        DB::transaction(function () use ($floor): void {
            $locked = Floor::lockForUpdate()->findOrFail($floor->id);
            if (Room::withTrashed()->where('floor_id', $locked->id)->exists()) {
                throw ValidationException::withMessages(['operation' => __('အခန်းရှိနေသောအထပ်ကို ဖျက်၍မရပါ။ အခန်းများကို အခြားအထပ်သို့ ရွှေ့ပါ။')]);
            }
            $before = $locked->toArray();
            $locked->delete();
            Audit::record('floor.deleted', $locked, $before);
        });

        return back()->with('success', __('အထပ်ကို ဖျက်ပြီးပါပြီ။'));
    }

    public function storeRoomType(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80', 'unique:room_types,name']]);
        DB::transaction(function () use ($data) {
            $roomType = RoomType::create($data);
            Audit::record('room_type.created', $roomType, null, $roomType->toArray());
        });

        return back()->with('success', __('အခန်းအမျိုးအစား ထည့်ပြီးပါပြီ။'));
    }

    public function updateRoomType(Request $request, RoomType $roomType)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80', Rule::unique('room_types', 'name')->ignore($roomType->id)]]);
        DB::transaction(function () use ($roomType, $data): void {
            $before = $roomType->toArray();
            $roomType->update($data);
            Audit::record('room_type.updated', $roomType, $before, $roomType->toArray());
        });

        return back()->with('success', __('အခန်းအမျိုးအစား ပြင်ပြီးပါပြီ။'));
    }

    public function destroyRoomType(RoomType $roomType)
    {
        DB::transaction(function () use ($roomType): void {
            $locked = RoomType::lockForUpdate()->findOrFail($roomType->id);
            if (Room::withTrashed()->where('room_type_id', $locked->id)->exists()) {
                throw ValidationException::withMessages(['operation' => __('အသုံးပြုနေသောအခန်းအမျိုးအစားကို ဖျက်၍မရပါ။ အခန်းများကို အခြားအမျိုးအစားသို့ ပြောင်းပါ။')]);
            }
            $before = $locked->toArray();
            $locked->delete();
            Audit::record('room_type.deleted', $locked, $before);
        });

        return back()->with('success', __('အခန်းအမျိုးအစားကို ဖျက်ပြီးပါပြီ။'));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->roomRules());
        DB::transaction(function () use ($data) {
            $room = Room::create($data);
            Audit::record('room.created', $room, null, $room->toArray());
        });

        return back()->with('success', __('အခန်းအသစ် ထည့်ပြီးပါပြီ။'));
    }

    public function edit(Room $room)
    {
        $floors = Floor::orderBy('number')->get();
        $roomTypes = RoomType::orderBy('name')->get();

        return view('rooms.edit', compact('room', 'floors', 'roomTypes'));
    }

    public function show(Room $room)
    {
        $room->load(['floor', 'roomType']);
        $stays = $room->stays()->withTrashed()->latest('check_in_at')->get();

        return view('rooms.show', compact('room', 'stays'));
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

        return redirect()->route('rooms.index')->with('success', __('အခန်းအချက်အလက် ပြင်ပြီးပါပြီ။'));
    }

    public function status(Request $request, Room $room, FrontDesk $desk)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['available', 'dirty', 'maintenance'])]]);
        $desk->roomStatus($room, $data['status']);

        return back()->with('success', __('အခန်းအခြေအနေ ပြောင်းပြီးပါပြီ။'));
    }

    public function destroy(Room $room)
    {
        DB::transaction(function () use ($room): void {
            $locked = Room::lockForUpdate()->findOrFail($room->id);
            if ($locked->status === 'occupied' || $locked->activeStay()->exists()) {
                throw ValidationException::withMessages(['operation' => __('ဧည့်သည်ရှိနေသောအခန်းကို ဖျက်၍မရပါ။ Check-out အရင်လုပ်ပါ။')]);
            }
            if ($locked->reservations()->where('status', 'booked')->exists()) {
                throw ValidationException::withMessages(['operation' => __('ကြိုတင် Booking ရှိသောအခန်းကို archive ထဲရွှေ့၍မရပါ။')]);
            }
            $before = $locked->toArray();
            $locked->delete();
            Audit::record('room.archived', $locked, $before, $locked->toArray());
        });

        return redirect()->route('rooms.index')->with('success', __('အခန်းကို archive ထဲရွှေ့ပြီးပါပြီ။ ပြန်ယူနိုင်ပါသည်။'));
    }

    public function restore(Room $room)
    {
        abort_unless($room->trashed(), 404);
        DB::transaction(function () use ($room): void {
            $locked = Room::withTrashed()->lockForUpdate()->findOrFail($room->id);
            $before = $locked->toArray();
            $locked->restore();
            Audit::record('room.restored', $locked, $before, $locked->toArray());
        });

        return redirect()->route('rooms.index', ['status' => 'archived'])->with('success', __('အခန်းကို ပြန်ယူပြီးပါပြီ။'));
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
