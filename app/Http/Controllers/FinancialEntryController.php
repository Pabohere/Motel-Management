<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\MonthlyFinancialStatements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FinancialEntryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'entry_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'description' => 'required|string|max:255',
            'debit_account' => ['required', Rule::in(array_keys(MonthlyFinancialStatements::ACCOUNTS))],
            'credit_account' => ['required', 'different:debit_account', Rule::in(array_keys(MonthlyFinancialStatements::ACCOUNTS))],
            'amount' => 'required|integer|min:1|max:1000000000000',
            'is_opening' => 'sometimes|boolean',
            'cash_flow_category' => ['nullable', Rule::in(['operating', 'investing', 'financing'])],
        ]);
        $data['is_opening'] = $request->boolean('is_opening');
        if ($data['is_opening'] && (substr($data['entry_date'], 8, 2) !== '01'
            || in_array($data['debit_account'], MonthlyFinancialStatements::PROFIT_ACCOUNTS, true)
            || in_array($data['credit_account'], MonthlyFinancialStatements::PROFIT_ACCOUNTS, true))) {
            throw ValidationException::withMessages(['is_opening' => __('အဖွင့်စာရင်းကို လ၏ပထမရက်တွင်သာ ထည့်ပါ။ ဝင်ငွေ / အသုံးစရိတ်အကောင့် မသုံးရပါ။')]);
        }
        DB::transaction(function () use ($data): void {
            $entryId = DB::table('financial_entries')->insertGetId([...$data, 'created_by' => auth()->id(), 'created_at' => now()]);
            AuditLog::create([
                'user_id' => auth()->id(), 'action' => 'financial_entry.created',
                'subject_type' => 'FinancialEntry', 'subject_id' => $entryId,
                'before' => null, 'after' => $data, 'created_at' => now(),
            ]);
        });

        return redirect()->route('reports.monthly', ['month' => substr($data['entry_date'], 0, 7)])
            ->with('success', __('စာရင်းညှိမှတ်တမ်း သိမ်းပြီးပါပြီ။'));
    }
}
