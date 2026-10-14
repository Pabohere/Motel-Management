<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Room;
use App\Models\Stay;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $rooms = Room::with(['activeStay', 'floor', 'roomType', 'nextReservation'])->join('floors', 'floors.id', '=', 'rooms.floor_id')
            ->orderBy('floors.number')->orderBy('rooms.number')->select('rooms.*')->get();
        $floors = $rooms->groupBy(fn (Room $room) => $room->floor->number);
        $counts = $rooms->countBy('status');
        $start = today();
        $end = today()->addDay();
        $checkIns = Stay::where('check_in_at', '>=', $start)->where('check_in_at', '<', $end)->count();
        $checkOuts = Stay::where('check_out_at', '>=', $start)->where('check_out_at', '<', $end)->count();
        $received = Payment::where('created_at', '>=', $start)->where('created_at', '<', $end)->sum('amount');
        $activeStays = Stay::with(['room', 'charges', 'payments'])->whereNull('check_out_at')->orderByDesc('check_in_at')->orderByDesc('id')->get();

        return view('dashboard', compact('rooms', 'floors', 'counts', 'checkIns', 'checkOuts', 'received', 'activeStays'));
    }
}
