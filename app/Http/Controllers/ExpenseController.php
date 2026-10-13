<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'expense_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'category' => ['required', Rule::in(['supplies', 'maintenance', 'utilities', 'salary', 'other'])],
            'description' => 'required|string|max:255',
            'amount' => 'required|integer|min:1|max:100000000',
        ]);

        DB::transaction(function () use ($data): void {
            $expense = Expense::create([...$data, 'created_by' => auth()->id()]);
            Audit::record('expense.created', $expense, null, $expense->toArray());
        });

        return redirect()->route('reports.monthly', ['month' => substr($data['expense_date'], 0, 7)])
            ->with('success', 'ထွက်ငွေ မှတ်တမ်းတင်ပြီးပါပြီ။');
    }
}
