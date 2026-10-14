@extends('layouts.app')
@section('title', __('အခန်းများ'))
@section('content')
<div class="page-heading"><div><h1>{{ __('အခန်းများ') }}</h1><p class="muted">{{ __('အထပ်၊ အမျိုးအစားနှင့် အခန်းအခြေအနေများ') }}</p></div>@if(auth()->user()->isManager())<a class="btn" href="#room-setup">{{ __('+ အခန်းစီမံရန်') }}</a>@endif</div>
<form class="filters room-filters" method="get" action="{{ route('rooms.index') }}">
    <label>{{ __('အထပ်') }}<select name="floor_id"><option value="">{{ __('အထပ်အားလုံး') }}</option>@foreach($floors as $floor)<option value="{{ $floor->id }}" @selected((string) request('floor_id') === (string) $floor->id)>{{ $floor->number }} {{ __('ထပ်') }}</option>@endforeach</select></label>
    <label>{{ __('အခြေအနေ') }}<select name="status"><option value="">{{ __('အားလုံး') }}</option>@foreach(\App\Models\Room::STATUSES as $status => $label)<option value="{{ $status }}" @selected(request('status') === $status)>{{ __($label) }}</option>@endforeach @if(auth()->user()->isManager())<option value="archived" @selected(request('status') === 'archived')>Archive</option>@endif</select></label>
    <button class="btn secondary" type="submit">{{ __('စစ်မည်') }}</button><a href="{{ route('rooms.index') }}">{{ __('ပြန်စမည်') }}</a>
</form>
@include('rooms.cards')
@if(auth()->user()->isManager() && request('status') !== 'archived')
<div class="room-setup-grid top-space" id="room-setup">
    <section class="panel setup-card"><h2>{{ __('အထပ် ဖန်တီးရန်') }}</h2><p class="muted setup-description">{{ __('အခန်းမထည့်မီ အထပ်ကို အရင်သတ်မှတ်ပါ။') }}</p>
        <form class="setup-form" method="post" action="{{ route('floors.store') }}">@csrf<label>{{ __('အထပ်နံပါတ်') }}<input name="number" type="number" min="1" max="99" step="1" value="{{ old('number') }}" required></label><button class="btn secondary">{{ __('+ အထပ်ထည့်မည်') }}</button></form>
        <p class="muted setup-summary">{{ __('ရှိပြီးသောအထပ်') }} — {{ $floors->pluck('number')->implode(', ') ?: __('မရှိသေးပါ') }}</p>
        <details class="catalog-editor"><summary>{{ __('အထပ်များ ပြင်ရန်') }}</summary>@foreach($floors as $floor)<form method="post" action="{{ route('floors.update', $floor) }}">@csrf @method('PUT')<label>{{ __('အထပ်') }} {{ $floor->number }}<input name="number" type="number" min="1" max="99" value="{{ $floor->number }}" required></label><button class="btn secondary small">{{ __('သိမ်းမည်') }}</button></form>@endforeach</details>
    </section>
    <section class="panel setup-card"><h2>{{ __('အခန်းအမျိုးအစား ဖန်တီးရန်') }}</h2><p class="muted setup-description">{{ __('အမျိုးအစားကို ဖန်တီးပြီးမှ အခန်းတွင် ရွေးနိုင်ပါမည်။') }}</p>
        <form class="setup-form" method="post" action="{{ route('room-types.store') }}">@csrf<label>{{ __('အမျိုးအစားအမည်') }}<input name="name" value="{{ old('name') }}" maxlength="80" required></label><button class="btn secondary">{{ __('+ အမျိုးအစားထည့်မည်') }}</button></form>
        <p class="muted setup-summary">{{ __('ရှိပြီးသောအမျိုးအစား') }} — {{ $roomTypes->pluck('name')->implode(', ') ?: __('မရှိသေးပါ') }}</p>
        <details class="catalog-editor"><summary>{{ __('အခန်းအမျိုးအစားများ ပြင်ရန်') }}</summary>@foreach($roomTypes as $roomType)<form method="post" action="{{ route('room-types.update', $roomType) }}">@csrf @method('PUT')<label>{{ $roomType->name }}<input name="name" maxlength="80" value="{{ $roomType->name }}" required></label><button class="btn secondary small">{{ __('သိမ်းမည်') }}</button></form>@endforeach</details>
    </section>
</div>
<section class="panel top-space"><h2>{{ __('အခန်းအသစ်ထည့်ရန်') }}</h2>
    @if($floors->isEmpty() || $roomTypes->isEmpty())<p class="alert error">{{ __('အခန်းထည့်ရန် အထပ်နှင့် အခန်းအမျိုးအစားကို အရင်ဖန်တီးပါ။') }}</p>@endif
    <form class="form-grid" method="post" action="{{ route('rooms.store') }}">@csrf
        <label>{{ __('အခန်းနံပါတ်') }}<input name="number" value="{{ old('number') }}" maxlength="20" pattern="[A-Za-z0-9-]+" title="{{ __('အင်္ဂလိပ်စာလုံး၊ နံပါတ်နှင့် - သာထည့်ပါ') }}" required></label>
        <label>{{ __('အထပ်') }}<select name="floor_id" required><option value="">{{ __('အထပ်ရွေးပါ') }}</option>@foreach($floors as $floor)<option value="{{ $floor->id }}" @selected(old('floor_id') == $floor->id)>{{ $floor->number }} {{ __('ထပ်') }}</option>@endforeach</select></label>
        <label>{{ __('အခန်းအမျိုးအစား') }}<select name="room_type_id" required><option value="">{{ __('အမျိုးအစားရွေးပါ') }}</option>@foreach($roomTypes as $roomType)<option value="{{ $roomType->id }}" @selected(old('room_type_id') == $roomType->id)>{{ $roomType->name }}</option>@endforeach</select></label>
        <label>{{ __('တစ်ညနှုန်း (MMK)') }}<input name="rate" type="number" min="1" max="100000000" step="1" value="{{ old('rate') }}" required></label>
        <div class="form-action"><button class="btn" @disabled($floors->isEmpty() || $roomTypes->isEmpty())>{{ __('+ အခန်းထည့်မည်') }}</button></div>
    </form>
</section>
@endif
@endsection
