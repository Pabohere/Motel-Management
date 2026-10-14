<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Stay;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MonthlyReportController extends Controller
{
    public function index(Request $request): View
    {
        return view('reports.monthly', $this->report($request));
    }

    public function export(Request $request): StreamedResponse
    {
        $report = $this->report($request);

        return response()->streamDownload(function () use ($report): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['လစဉ်စာရင်း', $report['month']]);
            fputcsv($output, ['ဝင်ငွေ (MMK)', $report['income']]);
            fputcsv($output, ['ထွက်ငွေ (MMK)', $report['outgoings']]);
            fputcsv($output, ['အသားတင် လက်ကျန် (MMK)', $report['net']]);
            fputcsv($output, ['အကြွေးကျန် (MMK)', $report['outstanding']]);
            fputcsv($output, []);
            fputcsv($output, ['အမျိုးအစား', 'ရက်စွဲ', 'အကြောင်းအရာ', 'အသေးစိတ်', 'တာဝန်ခံ', 'ငွေပမာဏ (MMK)']);
            foreach ($report['payments'] as $payment) {
                fputcsv($output, ['ဝင်ငွေ', $payment->created_at->format('Y-m-d H:i'), $this->csvText($payment->stay->guest_name.' / '.$payment->stay->room->number), $payment->method, $this->csvText($payment->creator->name), $payment->amount]);
            }
            foreach ($report['expenses'] as $expense) {
                fputcsv($output, ['ထွက်ငွေ', $expense->expense_date->format('Y-m-d'), $this->csvText($expense->description), $this->csvText($report['categories'][$expense->category] ?? $expense->category), $this->csvText($expense->creator->name), $expense->amount]);
            }
            fputcsv($output, []);
            fputcsv($output, ['အကြွေးကျန်ဧည့်သည်', 'အခန်း', 'ကျန်ငွေ (MMK)']);
            foreach ($report['outstandingStays'] as $stay) {
                fputcsv($output, [$this->csvText($stay->guest_name), $this->csvText($stay->room->number), $stay->balance]);
            }
            fclose($output);
        }, 'motel-monthly-'.$report['month'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function report(Request $request): array
    {
        $data = $request->validate(['month' => 'nullable|date_format:Y-m']);
        $month = $data['month'] ?? today()->format('Y-m');
        $start = Carbon::createFromFormat('!Y-m', $month)->startOfMonth();
        if ($start->isFuture()) {
            throw ValidationException::withMessages(['month' => __('အနာဂတ်လအတွက် စာရင်းမရှိသေးပါ။')]);
        }
        $end = $start->copy()->addMonth();
        $payments = Payment::with(['stay.room', 'creator'])
            ->where('created_at', '>=', $start)->where('created_at', '<', $end)->orderBy('created_at')->get();
        $expenses = Expense::with('creator')
            ->where('expense_date', '>=', $start->toDateString())->where('expense_date', '<', $end->toDateString())
            ->orderBy('expense_date')->orderBy('id')->get();
        $income = (int) $payments->sum('amount');
        $outgoings = (int) $expenses->sum('amount');
        $asOf = $end->isFuture() ? now() : $end->copy()->subSecond();
        $outstandingStays = Stay::withTrashed()->with(['room', 'charges' => fn ($query) => $query->where('created_at', '<=', $asOf), 'payments' => fn ($query) => $query->where('created_at', '<=', $asOf)])
            ->where('check_in_at', '<=', $asOf)->where('created_at', '<=', $asOf)->get()->filter(fn ($stay) => $stay->balance > 0)->values();

        return [
            'month' => $month, 'payments' => $payments, 'expenses' => $expenses,
            'income' => $income, 'outgoings' => $outgoings, 'net' => $income - $outgoings,
            'outstandingStays' => $outstandingStays, 'outstanding' => $outstandingStays->sum('balance'),
            'checkIns' => Stay::withTrashed()->where('check_in_at', '>=', $start)->where('check_in_at', '<', $end)->count(),
            'checkOuts' => Stay::withTrashed()->where('check_out_at', '>=', $start)->where('check_out_at', '<', $end)->count(),
            'categories' => ExpenseCategory::pluck('name', 'key'),
            'dailyPayments' => $payments->groupBy(fn ($payment) => $payment->created_at->toDateString())->map->sum('amount'),
            'dailyExpenses' => $expenses->groupBy(fn ($expense) => $expense->expense_date->toDateString())->map->sum('amount'),
        ];
    }

    private function csvText(string $value): string
    {
        return preg_match('/^\s*[=+\-@]/u', $value) ? "'".$value : $value;
    }
}
