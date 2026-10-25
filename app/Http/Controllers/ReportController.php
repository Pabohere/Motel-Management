<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Stay;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate(['date' => 'nullable|date_format:Y-m-d|before_or_equal:today']);
        $date = $data['date'] ?? today()->toDateString();
        $start = Carbon::parse($date)->startOfDay();
        $end = $start->copy()->addDay();
        $checkIns = Stay::withTrashed()->with('room')->where('check_in_at', '>=', $start)->where('check_in_at', '<', $end)->get();
        $checkOuts = Stay::withTrashed()->with('room')->where('check_out_at', '>=', $start)->where('check_out_at', '<', $end)->get();
        $payments = Payment::with(['stay.room', 'creator'])->where('created_at', '>=', $start)->where('created_at', '<', $end)->latest()->get();
        $asOf = $end->isFuture() ? now() : $end->copy()->subSecond();
        $occupied = Stay::withTrashed()->where('check_in_at', '<=', $asOf)->where(fn ($q) => $q->whereNull('check_out_at')->orWhere('check_out_at', '>', $asOf))->distinct()->count('room_id');
        $totalRooms = Room::withTrashed()->where('created_at', '<=', $asOf)
            ->where(fn ($q) => $q->whereNull('deleted_at')->orWhere('deleted_at', '>', $asOf))->count();
        $outstandingStays = Stay::withTrashed()->withFinancialTotals($asOf)
            ->where('check_in_at', '<=', $asOf)->where('created_at', '<=', $asOf)->get()->filter(fn ($stay) => $stay->balance > 0)->values()->load('room');
        $outstanding = $outstandingStays->sum('balance');
        $expenses = Expense::with('creator')->where('expense_date', $date)->orderBy('id')->get();
        $categories = ExpenseCategory::pluck('name', 'key');

        return view('reports.daily', compact('date', 'checkIns', 'checkOuts', 'payments', 'occupied', 'totalRooms', 'outstanding', 'outstandingStays', 'expenses', 'categories'));
    }
}
