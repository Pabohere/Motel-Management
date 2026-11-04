<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Stay;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $rooms = Room::with(['activeStay', 'floor', 'roomType', 'nextReservation', 'housekeeper'])->join('floors', 'floors.id', '=', 'rooms.floor_id')
            ->orderBy('floors.number')->orderBy('rooms.number')->select('rooms.*')->get();
        $floors = $rooms->groupBy(fn (Room $room) => $room->floor->number);
        $counts = $rooms->countBy('status');
        $start = today();
        $end = today()->addDay();
        $checkIns = Stay::where('check_in_at', '>=', $start)->where('check_in_at', '<', $end)->count();
        $checkOuts = Stay::where('check_out_at', '>=', $start)->where('check_out_at', '<', $end)->count();
        $received = Payment::where('created_at', '>=', $start)->where('created_at', '<', $end)->sum('amount');
        $activeStays = Stay::with('room')->withFinancialTotals()->whereNull('check_out_at')->orderByDesc('check_in_at')->orderByDesc('id')->get();

        $arrivals = Reservation::where('status', 'booked')->where('arrival_at', '>=', $start)->where('arrival_at', '<', $end)->count();
        $departures = $activeStays->filter(fn (Stay $stay): bool => $stay->expected_check_out_at->gte(now()) && $stay->expected_check_out_at->lt($end))->count();
        $overdue = $activeStays->filter(fn (Stay $stay): bool => $stay->expected_check_out_at->lt(now()))->count();

        $outstanding = (int) DB::query()->fromSub(Stay::withTrashed()->withFinancialTotals(), 'balances')->selectRaw('SUM(CASE WHEN room_charge + COALESCE(charges_sum_amount, 0) - COALESCE(payments_sum_amount, 0) > 0 THEN room_charge + COALESCE(charges_sum_amount, 0) - COALESCE(payments_sum_amount, 0) ELSE 0 END) AS outstanding')->value('outstanding');

        return view('dashboard', compact('rooms', 'floors', 'counts', 'checkIns', 'checkOuts', 'received', 'activeStays', 'arrivals', 'departures', 'overdue', 'outstanding'));
    }
}
