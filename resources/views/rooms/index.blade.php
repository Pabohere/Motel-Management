@extends('layouts.app')
@section('title', 'အခန်းများ')
@section('content')
<div class="page-heading"><div><h1>အခန်းများ</h1><p class="muted">အထပ်၊ အမျိုးအစားနှင့် အခန်းအခြေအနေများ</p></div>@if(auth()->user()->isManager())<a class="btn" href="#room-setup">+ အခန်းစီမံရန်</a>@endif</div>
<div class="tabs"><a class="{{ !request('status') ? 'selected' : '' }}" href="{{ route('rooms.index') }}">အားလုံး</a>@foreach(\App\Models\Room::STATUSES as $status => $label)<a class="{{ request('status') === $status ? 'selected' : '' }}" href="{{ route('rooms.index', ['status' => $status]) }}">{{ $label }}</a>@endforeach</div>
@include('rooms.cards')
@if(auth()->user()->isManager())
<div class="room-setup-grid top-space" id="room-setup">
    <section class="panel"><h2>အထပ် ဖန်တီးရန်</h2><p class="muted">အခန်းမထည့်မီ အထပ်ကို အရင်သတ်မှတ်ပါ။</p>
        <form method="post" action="{{ route('floors.store') }}">@csrf<label>အထပ်နံပါတ်<input name="number" type="number" min="1" max="99" step="1" value="{{ old('number') }}" required></label><button class="btn secondary">+ အထပ်ထည့်မည်</button></form>
        <p class="muted">ရှိပြီးသောအထပ် — {{ $floors->pluck('number')->implode(', ') ?: 'မရှိသေးပါ' }}</p>
    </section>
    <section class="panel"><h2>အခန်းအမျိုးအစား ဖန်တီးရန်</h2><p class="muted">အမျိုးအစားကို ဖန်တီးပြီးမှ အခန်းတွင် ရွေးနိုင်ပါမည်။</p>
        <form method="post" action="{{ route('room-types.store') }}">@csrf<label>အမျိုးအစားအမည်<input name="name" value="{{ old('name') }}" maxlength="80" required></label><button class="btn secondary">+ အမျိုးအစားထည့်မည်</button></form>
        <p class="muted">ရှိပြီးသောအမျိုးအစား — {{ $roomTypes->pluck('name')->implode(', ') ?: 'မရှိသေးပါ' }}</p>
    </section>
</div>
<section class="panel top-space"><h2>အခန်းအသစ်ထည့်ရန်</h2>
    @if($floors->isEmpty() || $roomTypes->isEmpty())<p class="alert error">အခန်းထည့်ရန် အထပ်နှင့် အခန်းအမျိုးအစားကို အရင်ဖန်တီးပါ။</p>@endif
    <form class="form-grid" method="post" action="{{ route('rooms.store') }}">@csrf
        <label>အခန်းနံပါတ်<input name="number" value="{{ old('number') }}" maxlength="20" pattern="[A-Za-z0-9-]+" title="အင်္ဂလိပ်စာလုံး၊ နံပါတ်နှင့် - သာထည့်ပါ" required></label>
        <label>အထပ်<select name="floor_id" required><option value="">အထပ်ရွေးပါ</option>@foreach($floors as $floor)<option value="{{ $floor->id }}" @selected(old('floor_id') == $floor->id)>{{ $floor->number }} ထပ်</option>@endforeach</select></label>
        <label>အခန်းအမျိုးအစား<select name="room_type_id" required><option value="">အမျိုးအစားရွေးပါ</option>@foreach($roomTypes as $roomType)<option value="{{ $roomType->id }}" @selected(old('room_type_id') == $roomType->id)>{{ $roomType->name }}</option>@endforeach</select></label>
        <label>တစ်ညနှုန်း (MMK)<input name="rate" type="number" min="1" max="100000000" step="1" value="{{ old('rate') }}" required></label>
        <div class="form-action"><button class="btn" @disabled($floors->isEmpty() || $roomTypes->isEmpty())>+ အခန်းထည့်မည်</button></div>
    </form>
</section>
@endif
@endsection
