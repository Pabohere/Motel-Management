<?php

namespace App\Http\Controllers;

use App\Models\Stay;
use App\Models\StayExtraBed;
use App\Services\StayOccupancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StayExtraBedController extends Controller
{
    public function store(Request $request, Stay $stay, StayOccupancy $occupancy): RedirectResponse
    {
        $occupancy->addBeds($stay, $request->validate([
            'quantity' => 'required|integer|between:1,10', 'nights' => 'required|integer|between:1,3650',
            'rate' => 'required|integer|between:0,100000000', 'request_key' => 'required|uuid',
        ]));

        return back()->with('success', __('Extra beds and their charge have been recorded.'));
    }

    public function update(Stay $stay, StayExtraBed $bed, StayOccupancy $occupancy): RedirectResponse
    {
        $occupancy->returnBeds($stay, $bed);

        return back()->with('success', __('Beds returned. The agreed charge is preserved.'));
    }
}
