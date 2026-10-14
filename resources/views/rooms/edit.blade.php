@extends('layouts.app')
@section('title', 'အခန်းပြင်ရန်')
@section('content')
<div class="page-heading"><h1>အခန်း {{ $room->number }}</h1><a href="{{ route('rooms.index') }}">← အခန်းများ</a></div>
<section class="panel narrow"><form method="post" action="{{ route('rooms.update', $room) }}">@csrf @method('PUT')
    <label>အခန်းနံပါတ်<input name="number" value="{{ old('number', $room->number) }}" maxlength="20" pattern="[A-Za-z0-9-]+" title="အင်္ဂလိပ်စာလုံး၊ နံပါတ်နှင့် - သာထည့်ပါ" required></label>
    <label>အထပ်<select name="floor_id" required>@foreach($floors as $floor)<option value="{{ $floor->id }}" @selected(old('floor_id', $room->floor_id) == $floor->id)>{{ $floor->number }} ထပ်</option>@endforeach</select></label>
    <label>အခန်းအမျိုးအစား<select name="room_type_id" required>@foreach($roomTypes as $roomType)<option value="{{ $roomType->id }}" @selected(old('room_type_id', $room->room_type_id) == $roomType->id)>{{ $roomType->name }}</option>@endforeach</select></label>
    <label>တစ်ညနှုန်း (MMK)<input name="rate" value="{{ old('rate', $room->rate) }}" type="number" min="1" max="100000000" step="1" required></label>
    <button class="btn">သိမ်းမည်</button>
</form></section>
@if($room->status !== 'occupied')<section class="panel narrow top-space"><h2>အခန်းအခြေအနေ</h2><form method="post" action="{{ route('rooms.status', $room) }}">@csrf @method('PATCH')<label>အခြေအနေ<select name="status">@foreach(\App\Models\Room::STATUSES as $status => $label)@if($status !== 'occupied')<option value="{{ $status }}" @selected($status === $room->status)>{{ $label }}</option>@endif @endforeach</select></label><button class="btn secondary">အခြေအနေပြောင်းမည်</button></form></section>@endif
@endsection
