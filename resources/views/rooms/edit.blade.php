@extends('layouts.app')
@section('title', __('အခန်းပြင်ရန်'))
@section('content')
<div class="page-heading"><h1>အခန်း {{ $room->number }}</h1><a href="{{ route('rooms.index') }}">{{ __('← အခန်းများ') }}</a></div>
<section class="panel narrow"><form method="post" action="{{ route('rooms.update', $room) }}">@csrf @method('PUT')
    <label>{{ __('အခန်းနံပါတ်') }}<input name="number" value="{{ old('number', $room->number) }}" maxlength="20" pattern="[A-Za-z0-9-]+" title="{{ __('အင်္ဂလိပ်စာလုံး၊ နံပါတ်နှင့် - သာထည့်ပါ') }}" required></label>
    <label>{{ __('အထပ်') }}<select name="floor_id" required>@foreach($floors as $floor)<option value="{{ $floor->id }}" @selected(old('floor_id', $room->floor_id) == $floor->id)>{{ $floor->number }} {{ __('ထပ်') }}</option>@endforeach</select></label>
    <label>{{ __('အခန်းအမျိုးအစား') }}<select name="room_type_id" required>@foreach($roomTypes as $roomType)<option value="{{ $roomType->id }}" @selected(old('room_type_id', $room->room_type_id) == $roomType->id)>{{ $roomType->name }}</option>@endforeach</select></label>
    <label>{{ __('တစ်ညနှုန်း (MMK)') }}<input name="rate" value="{{ old('rate', $room->rate) }}" type="number" min="1" max="100000000" step="1" required></label>
    <button class="btn">{{ __('သိမ်းမည်') }}</button>
</form></section>
<div class="record-actions top-space"><form method="post" action="{{ route('rooms.destroy', $room) }}" data-confirm="{{ __('ဤအခန်းကို archive ထဲရွှေ့မည်။ ပြန်ယူနိုင်ပါသည်။ ဆက်လုပ်မလား။') }}">@csrf @method('DELETE')<button class="btn danger" @disabled($room->status === 'occupied')>{{ __('အခန်းဖျက်ရန်') }}</button></form><a href="{{ route('rooms.show', $room) }}">{{ __('အခန်းအသေးစိတ်') }}</a></div>
@if($room->status !== 'occupied')<section class="panel narrow top-space"><h2>{{ __('အခန်းအခြေအနေ') }}</h2><form method="post" action="{{ route('rooms.status', $room) }}">@csrf @method('PATCH')<label>{{ __('အခြေအနေ') }}<select name="status">@foreach(\App\Models\Room::STATUSES as $status => $label)@if($status !== 'occupied')<option value="{{ $status }}" @selected($status === $room->status)>{{ __($label) }}</option>@endif @endforeach</select></label><button class="btn secondary">{{ __('အခြေအနေပြောင်းမည်') }}</button></form></section>@endif
@endsection
