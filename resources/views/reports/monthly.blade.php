@extends('layouts.app')
@section('title', 'လစဉ်စာရင်း')
@section('content')
<div class="page-heading monthly-heading">
    <div>
        <span class="eyebrow">MONTHLY REPORT · {{ $month }}</span>
        <h1>လစဉ် ဝင်ငွေ / ထွက်ငွေ</h1>
        <p class="muted">ဝင်ငွေမှာ လက်ခံရရှိသည့်ရက်၊ ထွက်ငွေမှာ သုံးစွဲသည့်ရက်အလိုက် ဖြစ်သည်။ · Asia/Yangon</p>
    </div>
    <form class="filters report-actions" method="get" action="{{ route('reports.monthly') }}">
        <input type="month" name="month" value="{{ $month }}" aria-label="လရွေးရန်" required>
        <button class="btn secondary">ကြည့်မည်</button>
        <a class="btn secondary" href="{{ route('reports.monthly.export', ['month' => $month]) }}">CSV Download</a>
        <button class="btn secondary" type="button" onclick="window.print()">Print / PDF</button>
    </form>
</div>
<div class="stats monthly-stats">
    <div class="stat"><span>ဝင်ငွေ · လက်ခံရရှိငွေ</span><strong>{{ number_format($income) }}<small> K</small></strong></div>
    <div class="stat"><span>ထွက်ငွေ</span><strong>{{ number_format($outgoings) }}<small> K</small></strong></div>
    <div class="stat"><span>အသားတင် လက်ကျန်</span><strong class="{{ $net < 0 ? 'balance-due' : '' }}">{{ number_format($net) }}<small> K</small></strong></div>
    <div class="stat"><span>ဧည့်သည် ဝင် / ထွက်</span><strong>{{ $checkIns }}<small> / {{ $checkOuts }} ဦး</small></strong></div>
</div>
<div class="two-columns monthly-ledgers">
    <section class="panel">
        <h2>ဝင်ငွေ အသေးစိတ်</h2>
        <div class="daily-strip monthly-breakdown">
            @foreach(['cash' => 'ငွေသား', 'transfer' => 'ငွေလွှဲ', 'card' => 'ကတ်'] as $method => $label)
                <div><span class="muted">{{ $label }}</span><strong>{{ number_format($payments->where('method', $method)->sum('amount')) }} K</strong></div>
            @endforeach
        </div>
        <div class="monthly-table-scroll"><table><thead><tr><th>ရက်စွဲ</th><th>ဧည့်သည် / အခန်း</th><th>ငွေပေးနည်း</th><th>လက်ခံငွေ</th></tr></thead><tbody>
            @forelse($payments as $payment)
                <tr><td>{{ $payment->created_at->format('d M H:i') }}</td><td><a href="{{ route('stays.show', $payment->stay) }}">{{ $payment->stay->guest_name }}</a><small>အခန်း {{ $payment->stay->room->number }} · {{ $payment->creator->name }}</small></td><td>{{ ['cash' => 'ငွေသား', 'transfer' => 'ငွေလွှဲ', 'card' => 'ကတ်'][$payment->method] }}</td><td>{{ number_format($payment->amount) }} K</td></tr>
            @empty
                <tr><td colspan="4" class="empty">ဤလအတွက် လက်ခံငွေ မရှိသေးပါ။</td></tr>
            @endforelse
        </tbody></table></div>
    </section>
    <section class="panel">
        <h2>ထွက်ငွေ အသေးစိတ်</h2>
        <div class="daily-strip monthly-breakdown">
            @foreach($categories as $key => $label)
                @if($expenses->where('category', $key)->isNotEmpty())
                    <div><span class="muted">{{ $label }}</span><strong>{{ number_format($expenses->where('category', $key)->sum('amount')) }} K</strong></div>
                @endif
            @endforeach
            @if($expenses->isEmpty())<span class="muted">မှတ်တမ်း မရှိသေးပါ။</span>@endif
        </div>
        <div class="monthly-table-scroll"><table><thead><tr><th>သုံးစွဲရက်</th><th>အမျိုးအစား / အကြောင်းအရာ</th><th>ထွက်ငွေ</th></tr></thead><tbody>
            @forelse($expenses as $expense)
                <tr><td>{{ $expense->expense_date->format('d M Y') }}</td><td>{{ $categories[$expense->category] }}<small>{{ $expense->description }} · {{ $expense->creator->name }}</small></td><td>{{ number_format($expense->amount) }} K</td></tr>
            @empty
                <tr><td colspan="3" class="empty">ဤလအတွက် ထွက်ငွေ မရှိသေးပါ။</td></tr>
            @endforelse
        </tbody></table></div>
    </section>
</div>
<section class="panel top-space expense-form-panel">
    <div><span class="eyebrow">MANAGER ONLY</span><h2>ထွက်ငွေ မှတ်တမ်းတင်ရန်</h2></div>
    <form method="post" action="{{ route('expenses.store') }}">
        @csrf
        <div class="form-grid">
            <label>သုံးစွဲရက်<input name="expense_date" type="date" max="{{ today()->toDateString() }}" value="{{ old('expense_date', today()->toDateString()) }}" required></label>
            <label>အမျိုးအစား<select name="category" required>@foreach($categories as $key => $label)<option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>@endforeach</select></label>
            <label>အကြောင်းအရာ<input name="description" value="{{ old('description') }}" maxlength="255" required></label>
            <label>ပမာဏ (MMK)<input name="amount" type="number" min="1" max="100000000" value="{{ old('amount') }}" required></label>
        </div>
        <button class="btn">+ ထွက်ငွေထည့်မည်</button>
    </form>
</section>
@endsection
