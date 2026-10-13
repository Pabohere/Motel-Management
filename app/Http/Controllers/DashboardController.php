<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Room;
use App\Models\Stay;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $rooms = Room::with('activeStay')->orderBy('number')->get();
        $counts = $rooms->countBy('status');
        $start = today();
        $end = today()->addDay();
        $checkIns = Stay::where('check_in_at', '>=', $start)->where('check_in_at', '<', $end)->count();
        $checkOuts = Stay::where('check_out_at', '>=', $start)->where('check_out_at', '<', $end)->count();
        $received = Payment::where('created_at', '>=', $start)->where('created_at', '<', $end)->sum('amount');
        $activeStays = Stay::with(['room', 'charges', 'payments'])->whereNull('check_out_at')->orderBy('expected_check_out_at')->get();

        return view('dashboard', compact('rooms', 'counts', 'checkIns', 'checkOuts', 'received', 'activeStays'));
    }
}
