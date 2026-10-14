<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ExpenseCategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);
        $data = $request->validate(['name' => ['required', 'string', 'max:80', 'unique:expense_categories,name']]);
        DB::transaction(function () use ($data): void {
            $category = ExpenseCategory::create(['key' => (string) Str::uuid(), 'name' => trim($data['name'])]);
            Audit::record('expense_category.created', $category, null, $category->toArray());
        });

        return back()->with('success', __('ထွက်ငွေအမျိုးအစား ထည့်ပြီးပါပြီ။'));
    }

    public function update(Request $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);
        $data = $request->validate(['name' => ['required', 'string', 'max:80', Rule::unique('expense_categories', 'name')->ignore($expenseCategory->key, 'key')]]);
        DB::transaction(function () use ($expenseCategory, $data): void {
            $before = $expenseCategory->toArray();
            $expenseCategory->update(['name' => trim($data['name'])]);
            Audit::record('expense_category.updated', $expenseCategory, $before, $expenseCategory->toArray());
        });

        return back()->with('success', __('ထွက်ငွေအမျိုးအစား ပြင်ပြီးပါပြီ။'));
    }
}
