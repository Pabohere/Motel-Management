<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Stay;
use App\Services\MonthlyFinancialStatements;
use App\Services\MonthlyStatementPdf;
use App\Services\MonthlyStatementTemplate;
use App\Services\MonthlyStatementWorkbook;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MonthlyReportController extends Controller
{
    public function index(Request $request): View
    {
        return view('reports.monthly', $this->report($request, false));
    }

    public function statements(Request $request, MonthlyStatementWorkbook $workbook): BinaryFileResponse
    {
        $report = $this->report($request);

        return response()->download($workbook->create($report), 'motel-financial-statements-'.$report['month'].'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ])->deleteFileAfterSend(true);
    }

    public function pdf(Request $request, MonthlyStatementPdf $pdf): BinaryFileResponse|Response
    {
        $report = $this->report($request);

        if (! $pdf->browserPath()) {
            return response()->view('reports.financial-pdf', [
                ...$report,
                'embeddedFont' => base64_encode(file_get_contents(public_path('fonts/noto-sans-myanmar-variable.ttf'))),
                'templateSheets' => app(MonthlyStatementWorkbook::class)->preview($report),
                'templateNotes' => app(MonthlyStatementTemplate::class)->notes($report),
                'printNonce' => bin2hex(random_bytes(16)),
            ])->header('Cache-Control', 'private, no-store');
        }

        return response()->download($pdf->create($report), 'motel-financial-statements-'.$report['month'].'.pdf', [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
        ])->deleteFileAfterSend(true);
    }

    public function export(Request $request): StreamedResponse
    {
        $report = $this->report($request);

        return response()->streamDownload(function () use ($report): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [$this->csvText((string) config('app.name')), __('လစဉ်ဘဏ္ဍာရေးရှင်းတမ်း'), $report['month']]);
            fputcsv($output, [__('စာရင်းအခြေခံ'), __('အခန်းခကို တည်းခိုပြီးသည့်ညအလိုက် ခွဲတွက်သည်။ အပိုဝန်ဆောင်မှုကို မှတ်တမ်းတင်ရက်အလိုက် တွက်သည်။')]);
            if (! $report['financials']['hasOpeningEntries']) {
                fputcsv($output, [__('အဖွင့်စာရင်း မထည့်ရသေးပါ။ စနစ်တွင် မှတ်တမ်းရှိသောငွေနှင့် စာရင်းညှိမှုများကိုသာ အခြေခံထားသည်။ ယခင်ပိုင်ဆိုင်မှု၊ အကြွေးနှင့် မတည်ငွေများကို အဖွင့်စာရင်းထည့်ပါ။')]);
            }
            foreach (app(MonthlyStatementWorkbook::class)->preview($report) as $statement) {
                fputcsv($output, []);
                $alias = match ($statement['name']) {
                    'pl' => 'P&L', 'bs' => 'Balance Sheet', 'cashflow' => 'Cash Flow', default => 'Changes in Equity'
                };
                fputcsv($output, [$statement['title'], $alias]);
                fputcsv($output, $statement['name'] === 'equity' ? ['Description', 'Share capital', 'Retained earnings', 'Total', 'MMK'] : ['Description', $report['month'], $report['comparisonMonth'], 'MMK']);
                foreach ($statement['rows'] as $row) {
                    fputcsv($output, [$row['label'], ...$row['values'], 'MMK']);
                }
            }
            fputcsv($output, []);
            fputcsv($output, ['Notes']);
            foreach (app(MonthlyStatementTemplate::class)->notes($report) as $note) {
                fputcsv($output, [$note]);
            }
            fputcsv($output, []);
            fputcsv($output, ['လက်ခံရရှိငွေ (MMK)', $report['income']]);
            fputcsv($output, ['ထွက်ငွေ (MMK)', $report['outgoings']]);
            fputcsv($output, ['လက်ခံငွေနှင့် ပေးချေငွေကွာခြားချက် (MMK)', $report['net']]);
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
            fputcsv($output, [__('စာရင်းညှိမှတ်တမ်း'), __('ရက်စွဲ'), 'Debit', 'Credit', __('ငွေပမာဏ (MMK)'), __('အကြောင်းအရာ'), __('အဖွင့်စာရင်း')]);
            foreach ($report['financialEntries'] as $entry) {
                fputcsv($output, [__('စာရင်းညှိမှတ်တမ်း'), $entry->entry_date, __($report['financialAccounts'][$entry->debit_account]), __($report['financialAccounts'][$entry->credit_account]), $entry->amount, $this->csvText($entry->description), $entry->is_opening ? 'Opening' : '']);
            }
            fputcsv($output, []);
            fputcsv($output, ['အကြွေးကျန်ဧည့်သည်', 'အခန်း', 'ကျန်ငွေ (MMK)']);
            foreach ($report['outstandingStays'] as $stay) {
                fputcsv($output, [$this->csvText($stay->guest_name), $this->csvText($stay->room->number), $stay->balance]);
            }
            fclose($output);
        }, 'motel-monthly-'.$report['month'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function report(Request $request, bool $includeStatements = true): array
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
        $outstandingStays = Stay::withTrashed()->withFinancialTotals($asOf)
            ->where('check_in_at', '<=', $asOf)->where('created_at', '<=', $asOf)->get()->filter(fn ($stay) => $stay->balance > 0)->values()->load('room');

        $financials = $includeStatements ? app(MonthlyFinancialStatements::class)->build($start, $end->isFuture() ? now()->addSecond() : $end) : null;
        $comparisonStart = $start->copy()->subMonth();
        $comparativeFinancials = $includeStatements ? app(MonthlyFinancialStatements::class)->build($comparisonStart, $start) : null;

        return [
            'financials' => $financials,
            'comparativeFinancials' => $comparativeFinancials,
            'comparisonMonth' => $comparisonStart->format('Y-m'),
            'financialAccounts' => MonthlyFinancialStatements::ACCOUNTS,
            'financialEntries' => DB::table('financial_entries')->join('users', 'users.id', '=', 'financial_entries.created_by')
                ->where('entry_date', '>=', $start->toDateString())->where('entry_date', '<', $end->toDateString())
                ->orderByDesc('entry_date')->orderByDesc('financial_entries.id')
                ->select('financial_entries.*', 'users.name as creator_name')->get(),
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
