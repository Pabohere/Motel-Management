@extends('layouts.app')
@section('title', __('လစဉ်စာရင်း'))
@section('content')
<div class="page-heading monthly-heading">
    <div>
        <span class="eyebrow">MONTHLY REPORT · {{ $month }}</span>
        <h1>{{ __('လစဉ်ဘဏ္ဍာရေးရှင်းတမ်း') }}</h1>
        <p class="muted">{{ __('Balance Sheet၊ P&L နှင့် Cash Flow · Asia/Yangon') }}</p>
    </div>
    <form class="filters report-actions" method="get" action="{{ route('reports.monthly') }}" data-auto-filter>
        <input type="month" name="month" value="{{ $month }}" aria-label="{{ __('လရွေးရန်') }}" required>
        <button class="btn secondary">{{ __('ကြည့်မည်') }}</button>
        <a class="btn secondary" href="{{ route('reports.monthly.export', ['month' => $month]) }}">CSV Download</a>
        <a class="btn" href="{{ route('reports.monthly.statements', ['month' => $month]) }}">{{ __('ဘဏ္ဍာရေးရှင်းတမ်း Download (Excel)') }}</a>
        <a class="btn secondary" href="{{ route('reports.monthly.pdf', ['month' => $month]) }}">PDF Download</a>
    </form>
</div>
<div class="section-heading"><h2>{{ __('လက်ခံငွေ / ပေးချေငွေ အသေးစိတ်') }}</h2></div>
<div class="stats monthly-stats">
    <div class="stat"><span>{{ __('ဝင်ငွေ · လက်ခံရရှိငွေ') }}</span><strong>{{ number_format($income) }}<small> K</small></strong></div>
    <div class="stat"><span>{{ __('ထွက်ငွေ') }}</span><strong>{{ number_format($outgoings) }}<small> K</small></strong></div>
    <div class="stat"><span>{{ __('လက်ခံငွေနှင့် ပေးချေငွေကွာခြားချက်') }}</span><strong class="{{ $net < 0 ? 'balance-due' : '' }}">{{ number_format($net) }}<small> K</small></strong></div>
    <div class="stat"><span>{{ __('ဧည့်သည် ဝင် / ထွက်') }}</span><strong>{{ $checkIns }}<small> / {{ $checkOuts }} ဦး</small></strong></div>
    <div class="stat"><span>{{ __('အကြွေးကျန်') }}</span><strong class="{{ $outstanding > 0 ? 'balance-due' : '' }}">{{ number_format($outstanding) }}<small> K</small></strong></div>
</div>
<section class="panel report-section monthly-cashflow"><div class="section-heading"><h2>{{ __('နေ့အလိုက် ဝင်ငွေ / ထွက်ငွေ') }}</h2><span class="muted">{{ $month }}</span></div><div class="report-table-scroll"><table><thead><tr><th>{{ __('ရက်စွဲ') }}</th><th>{{ __('ဝင်ငွေ') }}</th><th>{{ __('ထွက်ငွေ') }}</th><th>{{ __('လက်ခံငွေနှင့် ပေးချေငွေကွာခြားချက်') }}</th></tr></thead><tbody>
@forelse($dailyPayments->keys()->merge($dailyExpenses->keys())->unique()->sort() as $day)
@php($dayIncome = $dailyPayments[$day] ?? 0) @php($dayExpense = $dailyExpenses[$day] ?? 0)
<tr><td>{{ $day }}</td><td>{{ number_format($dayIncome) }} K</td><td>{{ number_format($dayExpense) }} K</td><td>{{ number_format($dayIncome - $dayExpense) }} K</td></tr>
@empty<tr><td colspan="4" class="empty">{{ __('ဤလအတွက် မှတ်တမ်း မရှိသေးပါ။') }}</td></tr>@endforelse
</tbody></table></div></section>
<div class="two-columns monthly-ledgers">
    <section class="panel">
        <h2>{{ __('ဝင်ငွေ အသေးစိတ်') }}</h2>
        <div class="daily-strip monthly-breakdown">
            @foreach(['cash' => 'ငွေသား', 'transfer' => 'ငွေလွှဲ', 'card' => 'ကတ်'] as $method => $label)
                <div><span class="muted">{{ __($label) }}</span><strong>{{ number_format($payments->where('method', $method)->sum('amount')) }} K</strong></div>
            @endforeach
        </div>
        <div class="monthly-table-scroll"><table><thead><tr><th>{{ __('ရက်စွဲ') }}</th><th>{{ __('ဧည့်သည် / အခန်း') }}</th><th>{{ __('ငွေပေးနည်း') }}</th><th>{{ __('လက်ခံငွေ') }}</th></tr></thead><tbody>
            @forelse($payments as $payment)
                <tr><td>{{ $payment->created_at->format('d M H:i') }}</td><td><a href="{{ route('stays.show', $payment->stay) }}">{{ $payment->stay->guest_name }}</a><small>{{ __('အခန်း') }} {{ $payment->stay->room->number }} · {{ $payment->creator->name }}</small></td><td>{{ __(['cash' => 'ငွေသား', 'transfer' => 'ငွေလွှဲ', 'card' => 'ကတ်'][$payment->method]) }} @if($payment->receipt_path)<small><a href="{{ route('stays.payments.receipt', [$payment->stay, $payment]) }}" target="_blank" rel="noopener">{{ __('ပြေစာကြည့်ရန်') }}</a></small>@endif</td><td>{{ number_format($payment->amount) }} K</td></tr>
            @empty
                <tr><td colspan="4" class="empty">{{ __('ဤလအတွက် လက်ခံငွေ မရှိသေးပါ။') }}</td></tr>
            @endforelse
        </tbody></table></div>
    </section>
    <section class="panel">
        <h2>{{ __('ထွက်ငွေ အသေးစိတ်') }}</h2>
        <div class="daily-strip monthly-breakdown">
            @foreach($categories as $key => $label)
                @if($expenses->where('category', $key)->isNotEmpty())
                    <div><span class="muted">{{ __($label) }}</span><strong>{{ number_format($expenses->where('category', $key)->sum('amount')) }} K</strong></div>
                @endif
            @endforeach
            @if($expenses->isEmpty())<span class="muted">{{ __('မှတ်တမ်း မရှိသေးပါ။') }}</span>@endif
        </div>
        <div class="monthly-table-scroll"><table><thead><tr><th>{{ __('သုံးစွဲရက်') }}</th><th>{{ __('အမျိုးအစား / အကြောင်းအရာ') }}</th><th>{{ __('ထွက်ငွေ') }}</th></tr></thead><tbody>
            @forelse($expenses as $expense)
                <tr><td>{{ $expense->expense_date->format('d M Y') }}</td><td>{{ __($categories[$expense->category] ?? $expense->category) }}<small>{{ $expense->description }} · {{ $expense->creator->name }}</small></td><td>{{ number_format($expense->amount) }} K</td></tr>
            @empty
                <tr><td colspan="3" class="empty">{{ __('ဤလအတွက် ထွက်ငွေ မရှိသေးပါ။') }}</td></tr>
            @endforelse
        </tbody></table></div>
    </section>
</div>
<section class="panel top-space report-section"><div class="section-heading"><h2>{{ __('အကြွေးကျန်စာရင်း') }}</h2><span class="count-pill">{{ $outstandingStays->count() }}</span></div><p class="muted">{{ __('ရွေးထားသောလ ကုန်ဆုံးချိန်အထိ မရရှိသေးသောငွေ') }}</p><div class="report-table-scroll"><table><thead><tr><th>{{ __('ဧည့်သည်') }}</th><th>{{ __('အခန်း') }}</th><th>{{ __('ကျသင့်ငွေ') }}</th><th>{{ __('ရရှိငွေ') }}</th><th>{{ __('ကျန်ငွေ') }}</th></tr></thead><tbody>@forelse($outstandingStays as $stay)<tr><td><a href="{{ route('stays.show', $stay) }}">{{ $stay->guest_name }}</a></td><td>{{ $stay->room->number }}</td><td>{{ number_format($stay->total) }} K</td><td>{{ number_format($stay->paid) }} K</td><td class="balance-due">{{ number_format($stay->balance) }} K</td></tr>@empty<tr><td colspan="5" class="empty">{{ __('အကြွေးကျန် မရှိပါ။') }}</td></tr>@endforelse</tbody></table></div></section>
@include('reports.financial-entries')
<section class="panel top-space expense-form-panel">
    <div><span class="eyebrow">MANAGER ONLY</span><h2>{{ __('ထွက်ငွေ မှတ်တမ်းတင်ရန်') }}</h2></div>
    <form method="post" action="{{ route('expenses.store') }}">
        @csrf
        <div class="form-grid">
            <label>{{ __('သုံးစွဲရက်') }}<input name="expense_date" type="date" max="{{ today()->toDateString() }}" value="{{ old('expense_date', today()->toDateString()) }}" required></label>
            <label>{{ __('အမျိုးအစား') }}<select name="category" required>@foreach($categories as $key => $label)<option value="{{ $key }}" @selected(old('category') === $key)>{{ __($label) }}</option>@endforeach</select></label>
            <label>{{ __('အကြောင်းအရာ') }}<input name="description" value="{{ old('description') }}" maxlength="255" required></label>
            <label>{{ __('ပမာဏ (MMK)') }}<input name="amount" type="number" min="1" max="100000000" value="{{ old('amount') }}" required></label>
        </div>
        <button class="btn">{{ __('+ ထွက်ငွေထည့်မည်') }}</button>
    </form>
</section>
<section class="panel top-space report-section"><div class="section-heading"><h2>{{ __('ထွက်ငွေအမျိုးအစား စီမံရန်') }}</h2></div><p class="muted">{{ __('အမျိုးအစားအမည်ပြောင်းလျှင် ယခင်ထွက်ငွေများတွင်လည်း အမည်အသစ်ပြပါမည်။') }}</p><form class="catalog-create" method="post" action="{{ route('expense-categories.store') }}">@csrf<label>{{ __('အမျိုးအစားအသစ်') }}<input name="name" maxlength="80" required></label><button class="btn secondary">{{ __('+ ထည့်မည်') }}</button></form><details class="catalog-editor"><summary>{{ __('ရှိပြီးသောအမျိုးအစားများ ပြင်ရန်') }}</summary>@foreach($categories as $key => $label)<form method="post" action="{{ route('expense-categories.update', $key) }}">@csrf @method('PUT')<label>{{ __($label) }}<input name="name" maxlength="80" value="{{ $label }}" required></label><button class="btn secondary small">{{ __('သိမ်းမည်') }}</button></form>@endforeach</details></section>
@endsection
