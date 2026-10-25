<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Payment;
use App\Models\Stay;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MonthlyFinancialStatements
{
    public const ACCOUNTS = [
        'cash' => 'လက်ငင်းငွေနှင့် ဘဏ်ငွေ',
        'receivables' => 'ရရန်အကြွေး',
        'fixed_assets' => 'ပုံသေပိုင်ဆိုင်မှု',
        'other_assets' => 'အခြားပိုင်ဆိုင်မှု',
        'accumulated_depreciation' => 'စုဆောင်းတန်ဖိုးလျော့',
        'payables' => 'ပေးရန်အကြွေး',
        'loans' => 'ချေးငွေပေးရန်',
        'guest_deposits' => 'ဧည့်သည်ကြိုတင်လက်ခံငွေ',
        'other_liabilities' => 'အခြားပေးရန်တာဝန်',
        'capital' => 'ပိုင်ရှင်မတည်ငွေ',
        'opening_equity' => 'အဖွင့်ပိုင်ရှင်အရင်းအနှီး',
        'drawings' => 'ပိုင်ရှင်ထုတ်ယူငွေ',
        'revenue' => 'တည်းခိုခနှင့် ဝန်ဆောင်မှုဝင်ငွေ',
        'other_income' => 'အခြားဝင်ငွေ',
        'operating_expense' => 'လုပ်ငန်းအသုံးစရိတ်',
        'depreciation_expense' => 'တန်ဖိုးလျော့စရိတ်',
    ];

    public const PROFIT_ACCOUNTS = ['revenue', 'other_income', 'operating_expense', 'depreciation_expense'];

    public function build(Carbon $start, Carbon $cutoff): array
    {
        $opening = $this->snapshot($start, true);
        $closing = $this->snapshot($cutoff);
        $change = [];
        foreach (self::ACCOUNTS as $account => $label) {
            $change[$account] = $closing[$account] - $opening[$account];
        }
        $revenue = -$change['revenue'];
        $otherIncome = -$change['other_income'];
        $operatingExpense = $change['operating_expense'];
        $depreciationExpense = $change['depreciation_expense'];
        $profit = $revenue + $otherIncome - $operatingExpense - $depreciationExpense;
        $retained = -$closing['revenue'] - $closing['other_income'] - $closing['operating_expense'] - $closing['depreciation_expense'];
        $assets = $closing['cash'] + $closing['receivables'] + $closing['fixed_assets'] + $closing['other_assets'] + $closing['accumulated_depreciation'];
        $liabilities = -$closing['payables'] - $closing['loans'] - $closing['guest_deposits'] - $closing['other_liabilities'];
        $equity = -$closing['capital'] - $closing['opening_equity'] + $retained - $closing['drawings'];
        $operatingReceipts = (int) Payment::where('created_at', '>=', $start)->where('created_at', '<', $cutoff)->sum('amount');
        $expenseTotals = Expense::where('expense_date', '>=', $start->toDateString())->where('expense_date', '<', $this->dateBoundary($cutoff))
            ->selectRaw('SUM(amount) as total, SUM(CASE WHEN category = ? THEN amount ELSE 0 END) as staff_cost', ['salary'])->first();
        $operatingPayments = (int) $expenseTotals->total;
        $staffCost = (int) $expenseTotals->staff_cost;
        $inflows = ['operating' => $operatingReceipts, 'investing' => 0, 'financing' => 0];
        $outflows = ['operating' => $operatingPayments, 'investing' => 0, 'financing' => 0];
        $entries = DB::table('financial_entries')->where('entry_date', '>=', $start->toDateString())
            ->where('entry_date', '<', $this->dateBoundary($cutoff))->where('is_opening', false)->get();
        foreach ($entries as $entry) {
            if ($entry->debit_account !== 'cash' && $entry->credit_account !== 'cash') {
                continue;
            }
            $counterparty = $entry->debit_account === 'cash' ? $entry->credit_account : $entry->debit_account;
            $section = $entry->cash_flow_category ?? match ($counterparty) {
                'fixed_assets' => 'investing',
                'capital', 'opening_equity', 'drawings', 'loans' => 'financing',
                default => 'operating',
            };
            if ($entry->debit_account === 'cash') {
                $inflows[$section] += (int) $entry->amount;
            } else {
                $outflows[$section] += (int) $entry->amount;
            }
        }
        $flows = [];
        foreach ($inflows as $section => $amount) {
            $flows[$section] = $amount - $outflows[$section];
        }
        $netCash = array_sum($flows);
        $statements = [
            'balance_sheet' => [
                'title' => 'လက်ကျန်ရှင်းတမ်း (Balance Sheet)',
                'rows' => [
                    $this->row(self::ACCOUNTS['cash'], $closing['cash']),
                    $this->row(self::ACCOUNTS['receivables'], $closing['receivables']),
                    $this->row(self::ACCOUNTS['fixed_assets'], $closing['fixed_assets']),
                    $this->row(self::ACCOUNTS['accumulated_depreciation'], $closing['accumulated_depreciation']),
                    $this->row(self::ACCOUNTS['other_assets'], $closing['other_assets']),
                    $this->row('ပိုင်ဆိုင်မှုစုစုပေါင်း', $assets, true),
                    $this->row(self::ACCOUNTS['payables'], -$closing['payables']),
                    $this->row(self::ACCOUNTS['loans'], -$closing['loans']),
                    $this->row(self::ACCOUNTS['guest_deposits'], -$closing['guest_deposits']),
                    $this->row(self::ACCOUNTS['other_liabilities'], -$closing['other_liabilities']),
                    $this->row('ပေးရန်တာဝန်စုစုပေါင်း', $liabilities, true),
                    $this->row(self::ACCOUNTS['opening_equity'], -$closing['opening_equity']),
                    $this->row(self::ACCOUNTS['capital'], -$closing['capital']),
                    $this->row('စုဆောင်းအမြတ် / အရှုံး', $retained),
                    $this->row('ပိုင်ရှင်ထုတ်ယူငွေ (နုတ်)', -$closing['drawings']),
                    $this->row('ပိုင်ရှင်အရင်းအနှီးစုစုပေါင်း', $equity, true),
                    $this->row('ပေးရန်တာဝန်နှင့် အရင်းအနှီးစုစုပေါင်း', $liabilities + $equity, true),
                    $this->row('စာရင်းကွာဟချက်', $assets - $liabilities - $equity, true),
                ],
            ],
            'income_statement' => [
                'title' => 'အမြတ် / အရှုံးရှင်းတမ်း (P&L)',
                'rows' => [
                    $this->row(self::ACCOUNTS['revenue'], $revenue),
                    $this->row(self::ACCOUNTS['other_income'], $otherIncome),
                    $this->row('ဝင်ငွေစုစုပေါင်း', $revenue + $otherIncome, true),
                    $this->row(self::ACCOUNTS['operating_expense'], $operatingExpense),
                    $this->row(self::ACCOUNTS['depreciation_expense'], $depreciationExpense),
                    $this->row('အသုံးစရိတ်စုစုပေါင်း', $operatingExpense + $depreciationExpense, true),
                    $this->row('အသားတင်အမြတ် / အရှုံး', $profit, true),
                ],
            ],
            'cash_flow' => [
                'title' => 'ငွေသားစီးဆင်းမှုရှင်းတမ်း (Cash Flow)',
                'rows' => [
                    $this->row('လအစငွေလက်ကျန်', $opening['cash']),
                    $this->row('လုပ်ငန်းလည်ပတ်မှု လက်ခံငွေ', $inflows['operating']),
                    $this->row('လုပ်ငန်းလည်ပတ်မှု ပေးချေငွေ', -$outflows['operating']),
                    $this->row('လုပ်ငန်းလည်ပတ်မှုမှ ငွေသားစီးဆင်းမှု', $flows['operating'], true),
                    $this->row('ရင်းနှီးမြှုပ်နှံမှု လက်ခံငွေ', $inflows['investing']),
                    $this->row('ရင်းနှီးမြှုပ်နှံမှု ပေးချေငွေ', -$outflows['investing']),
                    $this->row('ရင်းနှီးမြှုပ်နှံမှုမှ ငွေသားစီးဆင်းမှု', $flows['investing'], true),
                    $this->row('ငွေကြေးထောက်ပံ့မှု လက်ခံငွေ', $inflows['financing']),
                    $this->row('ငွေကြေးထောက်ပံ့မှု ပေးချေငွေ', -$outflows['financing']),
                    $this->row('ငွေကြေးထောက်ပံ့မှုမှ ငွေသားစီးဆင်းမှု', $flows['financing'], true),
                    $this->row('အသားတင်ငွေတိုး / လျော့', $netCash, true),
                    $this->row('လကုန်ငွေလက်ကျန်', $closing['cash'], true),
                    $this->row('ငွေလက်ကျန်ကွာဟချက်', $closing['cash'] - $opening['cash'] - $netCash, true),
                ],
            ],
        ];

        return [
            'statements' => $statements, 'earnedRevenue' => $revenue, 'profit' => $profit,
            'assets' => $assets, 'liabilities' => $liabilities, 'equity' => $equity,
            'openingCash' => $opening['cash'], 'closingCash' => $closing['cash'], 'netCash' => $netCash,
            'flows' => $flows, 'balanceDifference' => $assets - $liabilities - $equity,
            'cashDifference' => $closing['cash'] - $opening['cash'] - $netCash,
            'hasOpeningEntries' => DB::table('financial_entries')->where('is_opening', true)->where('entry_date', '<', $this->dateBoundary($cutoff))->exists(),
            'asOf' => $cutoff->copy()->subSecond(),
            'openingBalances' => $opening, 'closingBalances' => $closing, 'accountChanges' => $change,
            'inflows' => $inflows, 'outflows' => $outflows, 'staffCost' => $staffCost,
            'financingComponents' => $this->financingComponents($entries),
        ];
    }

    private function financingComponents(Collection $entries): array
    {
        $components = ['capital' => 0, 'borrowings' => 0, 'repayments' => 0, 'drawings' => 0];
        foreach ($entries as $entry) {
            if ($entry->debit_account !== 'cash' && $entry->credit_account !== 'cash') {
                continue;
            }
            $receipt = $entry->debit_account === 'cash';
            $account = $receipt ? $entry->credit_account : $entry->debit_account;
            $section = $entry->cash_flow_category ?? match ($account) {
                'fixed_assets' => 'investing',
                'capital', 'opening_equity', 'drawings', 'loans' => 'financing',
                default => 'operating',
            };
            if ($section !== 'financing') {
                continue;
            }
            $key = match (true) {
                $receipt && in_array($account, ['capital', 'opening_equity'], true) => 'capital',
                $account === 'loans' && $receipt => 'borrowings',
                $account === 'loans' => 'repayments',
                $account === 'drawings' => 'drawings',
                default => null,
            };
            if ($key) {
                $components[$key] += ($receipt ? 1 : -1) * (int) $entry->amount;
            }
        }

        return $components;
    }

    private function snapshot(Carbon $cutoff, bool $includeOpeningAtBoundary = false): array
    {
        $balances = array_fill_keys(array_keys(self::ACCOUNTS), 0);
        $received = (int) Payment::where('created_at', '<', $cutoff)->sum('amount');
        $spent = (int) Expense::where('expense_date', '<', $this->dateBoundary($cutoff))->sum('amount');
        $balances['cash'] = $received - $spent;
        $balances['operating_expense'] = $spent;
        $stays = Stay::withTrashed()->select(['id', 'room_charge', 'check_in_at', 'expected_check_out_at', 'check_out_at'])
            ->withSum(['charges' => fn (Builder $query) => $query->where('created_at', '<', $cutoff)], 'amount')
            ->withSum(['payments' => fn (Builder $query) => $query->where('created_at', '<', $cutoff)], 'amount')
            ->where('check_in_at', '<', $cutoff)->where('created_at', '<', $cutoff)->lazyById(500);
        foreach ($stays as $stay) {
            $arrival = $stay->check_in_at->copy()->startOfDay();
            $departure = ($stay->check_out_at ?? $stay->expected_check_out_at)->copy()->startOfDay();
            $nights = max(1, (int) $arrival->diffInDays($departure, false));
            $elapsed = max(0, min($nights, (int) $arrival->diffInDays($cutoff->copy()->startOfDay(), false)));
            if ($stay->check_out_at && $stay->check_out_at->lt($cutoff)) {
                $elapsed = $nights;
            }
            $earned = intdiv($stay->room_charge * $elapsed, $nights) + (int) $stay->charges_sum_amount;
            $paid = (int) $stay->payments_sum_amount;
            $balances['revenue'] -= $earned;
            $balances['receivables'] += max(0, $earned - $paid);
            $balances['guest_deposits'] -= max(0, $paid - $earned);
        }
        $entries = DB::table('financial_entries')->where(function ($query) use ($cutoff, $includeOpeningAtBoundary): void {
            $query->where('entry_date', '<', $this->dateBoundary($cutoff));
            if ($includeOpeningAtBoundary) {
                $query->orWhere(fn ($query) => $query->where('is_opening', true)->where('entry_date', $cutoff->toDateString()));
            }
        })->get();
        foreach ($entries as $entry) {
            $balances[$entry->debit_account] += (int) $entry->amount;
            $balances[$entry->credit_account] -= (int) $entry->amount;
        }

        return $balances;
    }

    private function dateBoundary(Carbon $cutoff): string
    {
        return $cutoff->isStartOfDay() ? $cutoff->toDateString() : $cutoff->copy()->addDay()->toDateString();
    }

    private function row(string $label, int $amount, bool $total = false): array
    {
        return compact('label', 'amount', 'total');
    }
}
