@extends('layouts.app')
@section('title', __('ဧည့်သည် Check-in'))
@section('content')
<div class="page-heading"><div><h1>{{ __('ဧည့်သည် Check-in') }}</h1><p class="muted">{{ __('Walk-in ဧည့်သည်ကို အားလပ်သောအခန်းတွင် တည်းခိုစေပါ။') }}</p></div><div class="record-actions"><a class="btn secondary" href="{{ route('reservations.create') }}">{{ __('အကြို Booking') }}</a><a href="{{ route('stays.index') }}">{{ __('← ဧည့်သည်စာရင်း') }}</a></div></div>
@if($rooms->isEmpty())
<div class="alert error">{{ __('အားလပ်သောအခန်း မရှိပါ။ သန့်ရှင်းရေးပြီးသောအခန်းကို အရင်ပြောင်းပေးပါ။') }}</div>
@else
<form class="panel" method="post" action="{{ route('stays.store') }}" id="check-in-form" enctype="multipart/form-data">
    @csrf
    <h2>{{ __('ဧည့်သည် အချက်အလက်') }}</h2>
    <div class="form-grid">
        <label>{{ __('အမည်') }}<input name="guest_name" value="{{ old('guest_name') }}" maxlength="150" required autofocus></label>
        <label>{{ __('ကျား / မ') }}<select name="gender" required><option value="">{{ __('ရွေးပါ') }}</option>@foreach(\App\Models\Stay::GENDERS as $value => $label)<option value="{{ $value }}" @selected(old('gender') === $value)>{{ __($label) }}</option>@endforeach</select></label>
        <label>{{ __('ဖုန်းနံပါတ်') }}<input name="phone" value="{{ old('phone') }}" type="tel" maxlength="40" required></label>
        <label>{{ __('မှတ်ပုံတင် / အထောက်အထားဖိုင်') }}<input name="identity_documents[]" type="file" multiple accept="image/jpeg,image/png,image/webp,application/pdf" data-max-files="5"><small class="muted">{{ __('ဓာတ်ပုံ/PDF ၅ ဖိုင်အထိ · တစ်ဖိုင် 5 MB အထိ · ဝန်ထမ်းသာ ကြည့်နိုင်သည်') }}</small></label>
    </div>
    <h2 class="top-space">{{ __('အခန်းနဲ့ တည်းခိုချိန်') }}</h2>
    <div class="form-grid">
        <label>{{ __('အခန်း') }}<select name="room_id" id="room-select" required><option value="">{{ __('အခန်းရွေးပါ') }}</option>@foreach($rooms as $room)<option value="{{ $room->id }}" data-rate="{{ $room->rate }}" @selected(old('room_id', $selectedRoom?->id) == $room->id)>{{ $room->floor->number }} {{ __('ထပ်') }} · {{ $room->number }} · {{ $room->roomType->name }} · {{ number_format($room->rate) }} K / {{ __('ည') }}</option>@endforeach</select></label>
        <label>{{ __('ဝင်ချိန်') }}<input name="check_in_at" id="check-in" type="datetime-local" value="{{ old('check_in_at', now()->format('Y-m-d\TH:i')) }}" required></label>
        <label>{{ __('ထွက်ရန် သတ်မှတ်ချိန်') }}<input name="expected_check_out_at" id="check-out" type="datetime-local" value="{{ old('expected_check_out_at', now()->addDay()->setTime(12, 0)->format('Y-m-d\TH:i')) }}" required></label>
    </div>
    <h2 class="top-space">{{ __('ငွေစာရင်း') }}</h2>
    <p class="muted">{{ __('အခန်းခမှာ တည်းခိုမှုတစ်ခုလုံးအတွက် ဖြစ်ပါသည်။ နှုန်း × ညအရေအတွက် တွက်ပေးမည်။ မသိမ်းမီ စစ်ဆေး/ပြင်ဆင်နိုင်ပါသည်။') }}</p>
    <div class="form-grid">
        <label>{{ __('အခန်းခ စုစုပေါင်း (MMK)') }}<input name="room_charge" id="room-charge" type="number" value="{{ old('room_charge', $selectedRoom?->rate ?? 0) }}" min="0" max="100000000" required><button class="text-link" type="button" id="calculate-rate">{{ __('ညအရေအတွက်ဖြင့် တွက်ရန်') }}</button></label>
        <label>{{ __('ငွေပေးချေမှု') }}<select name="payment_term" required><option value="">{{ __('ရွေးပါ') }}</option><option value="immediate" @selected(old('payment_term') === 'immediate')>{{ __('လက်ငင်း') }}</option><option value="credit" @selected(old('payment_term') === 'credit')>{{ __('အကြွေး') }}</option></select><small class="muted">{{ __('လက်ငင်းဆို အခန်းခအပြည့်ပေးရမည်။ အကြွေးဖြစ်ပါက ကျန်ငွေရှိလည်း Check-out လုပ်နိုင်သည်။') }}</small></label>
        <label>{{ __('ယခု ပေးငွေ (MMK)') }}<input name="deposit" type="number" value="{{ old('deposit', 0) }}" min="0" max="100000000" required></label>
        <label>{{ __('ငွေပေးနည်း') }}<select name="method" data-payment-method><option value="cash" @selected(old('method') === 'cash')>{{ __('ငွေသား') }}</option><option value="transfer" @selected(old('method') === 'transfer')>{{ __('ငွေလွှဲ') }}</option><option value="card" @selected(old('method') === 'card')>{{ __('ကတ်') }}</option></select></label>
        <label class="receipt-field" data-receipt-field>{{ __('ငွေလွှဲပြေစာပုံ') }}<input name="payment_receipt" type="file" accept="image/jpeg,image/png,image/webp"><small class="muted">{{ __('ဓာတ်ပုံ 5 MB အထိ · ဝန်ထမ်းသာ ကြည့်နိုင်သည်') }}</small></label>
    </div>
    <label>{{ __('မှတ်ချက်') }}<textarea name="notes" maxlength="2000" rows="3">{{ old('notes') }}</textarea></label>
    <div class="form-footer"><span class="muted">{{ __('Check-in လုပ်သည်နှင့် အခန်းမှာ “ဧည့်သည်ရှိ” ဖြစ်ပါမည်။') }}</span><button class="btn">{{ __('Check-in လုပ်မည် →') }}</button></div>
</form>
@endif
@endsection
