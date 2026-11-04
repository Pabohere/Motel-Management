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
        <label>{{ __('အမည်') }}<input name="guest_name" value="{{ old('guest_name') }}" maxlength="150" required autofocus>@if($errors->has('guest_name') || $errors->has('guest_name.*'))<small class="field-error" role="alert">{{ $errors->first('guest_name') ?: $errors->first('guest_name.*') }}</small>@endif</label>
        <label>{{ __('ကျား / မ') }}<select name="gender" required><option value="">{{ __('ရွေးပါ') }}</option>@foreach(\App\Models\Stay::GENDERS as $value => $label)<option value="{{ $value }}" @selected(old('gender') === $value)>{{ __($label) }}</option>@endforeach</select>@if($errors->has('gender') || $errors->has('gender.*'))<small class="field-error" role="alert">{{ $errors->first('gender') ?: $errors->first('gender.*') }}</small>@endif</label>
        <label>{{ __('ဖုန်းနံပါတ်') }}<input name="phone" value="{{ old('phone') }}" type="tel" maxlength="40" required>@if($errors->has('phone') || $errors->has('phone.*'))<small class="field-error" role="alert">{{ $errors->first('phone') ?: $errors->first('phone.*') }}</small>@endif</label>
    </div>
    @include('stays.companions')
    <h2 class="top-space">{{ __('အခန်းနဲ့ တည်းခိုချိန်') }}</h2>
    <div class="form-grid">
        <label>{{ __('အခန်း') }}<select name="room_id" id="room-select" required><option value="">{{ __('အခန်းရွေးပါ') }}</option>@foreach($rooms as $room)<option value="{{ $room->id }}" data-rate="{{ $room->rate }}" data-capacity="{{ $room->capacity }}" data-max-beds="{{ $room->max_extra_beds }}" data-bed-rate="{{ $room->extra_bed_rate }}" @selected(old('room_id', request('room')) == $room->id)>{{ $room->floor->number }} {{ __('ထပ်') }} · {{ $room->number }} · {{ __($room->roomType->name) }} · {{ number_format($room->rate) }} K / {{ __('ည') }}</option>@endforeach</select>@if($errors->has('room_id') || $errors->has('room_id.*'))<small class="field-error" role="alert">{{ $errors->first('room_id') ?: $errors->first('room_id.*') }}</small>@endif</label>
        <label>{{ __('ရောက်ရန်ချိန်') }}<input name="arrival_at" id="check-in" type="datetime-local" min="{{ now()->format('Y-m-d\TH:i') }}" value="{{ old('arrival_at', now()->addDay()->format('Y-m-d\TH:i')) }}" required>@if($errors->has('arrival_at') || $errors->has('arrival_at.*'))<small class="field-error" role="alert">{{ $errors->first('arrival_at') ?: $errors->first('arrival_at.*') }}</small>@endif</label>
        <label>{{ __('ထွက်ရန် သတ်မှတ်ချိန်') }}<input name="departure_at" id="check-out" type="datetime-local" min="{{ now()->addDay()->addMinute()->format('Y-m-d\TH:i') }}" value="{{ old('departure_at', now()->addDays(2)->setTime(12, 0)->format('Y-m-d\TH:i')) }}" required>@if($errors->has('departure_at') || $errors->has('departure_at.*'))<small class="field-error" role="alert">{{ $errors->first('departure_at') ?: $errors->first('departure_at.*') }}</small>@endif</label>
        <label>{{ __('အခန်းခ စုစုပေါင်း (MMK)') }}<input name="room_charge" id="room-charge" data-manual="{{ old('room_charge') !== null ? 'true' : 'false' }}" type="number" min="0" max="100000000" value="{{ old('room_charge', 0) }}" required><button class="text-link" type="button" id="calculate-rate">{{ __('Use nightly rate') }}</button>@if($errors->has('room_charge') || $errors->has('room_charge.*'))<small class="field-error" role="alert">{{ $errors->first('room_charge') ?: $errors->first('room_charge.*') }}</small>@endif</label>
    </div>
    @include('stays.extra-bed-fields')
    @include('stays.price-summary', ['showPayment' => false])
    <label>{{ __('မှတ်ချက်') }}<textarea name="notes" maxlength="2000" rows="3">{{ old('notes') }}</textarea>@if($errors->has('notes') || $errors->has('notes.*'))<small class="field-error" role="alert">{{ $errors->first('notes') ?: $errors->first('notes.*') }}</small>@endif</label>
    <div class="form-footer"><span class="muted">{{ __('Booking လုပ်ခြင်းက အခန်းအခြေအနေကို မပြောင်းပါ။ Check-in လုပ်ချိန်တွင်သာ ပြောင်းပါမည်။') }}</span><button class="btn">{{ __('အကြို Booking သိမ်းမည်') }}</button></div>
</form>
@endif
@endsection
