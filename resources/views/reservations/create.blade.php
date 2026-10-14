@extends('layouts.app')
@section('title', __('အကြို Booking'))
@section('content')
<div class="page-heading"><div><h1>{{ __('အကြို Booking') }}</h1><p class="muted">{{ __('ကြိုတင်ချိန်းထားပြီး ဧည့်သည်ရောက်မှ Check-in လုပ်ပါ။') }}</p></div><a href="{{ route('reservations.index') }}">{{ __('← Booking စာရင်း') }}</a></div>
@if($rooms->isEmpty())
<div class="alert error">{{ __('ကြိုတင်ချိန်းရန် အခန်းမရှိပါ။') }}</div>
@else
<form class="panel" method="post" action="{{ route('reservations.store') }}">
    @csrf
    <h2>{{ __('ဧည့်သည် အချက်အလက်') }}</h2>
    <div class="form-grid">
        <label>{{ __('အမည်') }}<input name="guest_name" value="{{ old('guest_name') }}" maxlength="150" required autofocus></label>
        <label>{{ __('ကျား / မ') }}<select name="gender" required><option value="">{{ __('ရွေးပါ') }}</option>@foreach(\App\Models\Stay::GENDERS as $value => $label)<option value="{{ $value }}" @selected(old('gender') === $value)>{{ __($label) }}</option>@endforeach</select></label>
        <label>{{ __('ဖုန်းနံပါတ်') }}<input name="phone" value="{{ old('phone') }}" type="tel" maxlength="40" required></label>
    </div>
    <h2 class="top-space">{{ __('အခန်းနဲ့ တည်းခိုချိန်') }}</h2>
    <div class="form-grid">
        <label>{{ __('အခန်း') }}<select name="room_id" id="room-select" required><option value="">{{ __('အခန်းရွေးပါ') }}</option>@foreach($rooms as $room)<option value="{{ $room->id }}" data-rate="{{ $room->rate }}" @selected(old('room_id', request('room')) == $room->id)>{{ $room->floor->number }} {{ __('ထပ်') }} · {{ $room->number }} · {{ $room->roomType->name }} · {{ number_format($room->rate) }} K / {{ __('ည') }}</option>@endforeach</select></label>
        <label>{{ __('ရောက်ရန်ချိန်') }}<input name="arrival_at" id="check-in" type="datetime-local" min="{{ now()->format('Y-m-d\TH:i') }}" value="{{ old('arrival_at', now()->addDay()->format('Y-m-d\TH:i')) }}" required></label>
        <label>{{ __('ထွက်ရန် သတ်မှတ်ချိန်') }}<input name="departure_at" id="check-out" type="datetime-local" value="{{ old('departure_at', now()->addDays(2)->setTime(12, 0)->format('Y-m-d\TH:i')) }}" required></label>
        <label>{{ __('အခန်းခ စုစုပေါင်း (MMK)') }}<input name="room_charge" id="room-charge" type="number" min="0" max="100000000" value="{{ old('room_charge', 0) }}" required><button class="text-link" type="button" id="calculate-rate">{{ __('ညအရေအတွက်ဖြင့် တွက်ရန်') }}</button></label>
    </div>
    <label>{{ __('မှတ်ချက်') }}<textarea name="notes" maxlength="2000" rows="3">{{ old('notes') }}</textarea></label>
    <div class="form-footer"><span class="muted">{{ __('Booking လုပ်ခြင်းက အခန်းအခြေအနေကို မပြောင်းပါ။ Check-in လုပ်ချိန်တွင်သာ ပြောင်းပါမည်။') }}</span><button class="btn">{{ __('အကြို Booking သိမ်းမည်') }}</button></div>
</form>
@endif
@endsection
