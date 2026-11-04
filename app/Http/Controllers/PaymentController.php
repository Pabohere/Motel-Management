<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __invoke(Request $request): View
    {
        $data = $request->validate(['q' => 'nullable|string|max:100', 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $payments = Payment::with(['stay.room', 'creator'])
            ->when($data['q'] ?? null, fn ($query, $term) => $query->where(fn ($query) => $query->where('reference', 'like', '%'.$term.'%')->orWhere('stay_id', ltrim($term, '#'))->orWhereHas('stay', fn ($stay) => $stay->withTrashed()->where(fn ($match) => $match->where('guest_name', 'like', '%'.$term.'%')->orWhere('phone', 'like', '%'.$term.'%')->orWhereHas('room', fn ($room) => $room->where('number', 'like', '%'.$term.'%'))))))
            ->when($data['from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($data['to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('id')->paginate(30)->withQueryString();

        return view('payments.index', compact('payments'));
    }
}
