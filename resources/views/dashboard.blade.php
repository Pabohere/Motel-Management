@extends('layouts.app')
@section('title', __('အနှစ်ချုပ်'))
@section('content')
<div class="page-heading dashboard-heading">
    <div><span class="eyebrow">MOTEL / OVERVIEW</span><h1>{{ __('မိုတယ် အနှစ်ချုပ်') }}</h1><p class="muted">{{ __('သင့်မိုတယ်ရဲ့ နေ့စဉ်လုပ်ငန်းခွင်၊ တစ်နေရာတည်းမှာ။') }}</p></div>
    <span class="live-pill"><span class="dot"></span> {{ now()->format('d M Y') }} · {{ __('လက်ရှိအခြေအနေ') }}</span>
</div>
<div class="dashboard-top">
    <section class="glass-panel room-map-panel" aria-labelledby="room-map-heading">
        <div class="glass-inner">
            <div class="panel-toolbar"><div class="panel-title"><span class="title-icon">@include('icon', ['name'=>'grid'])</span><h2 id="room-map-heading">{{ __('အခန်းအခြေအနေ') }}</h2><span class="count-pill">{{ $rooms->count() }}</span></div>
                <div class="map-filter">@include('icon', ['name'=>'filter'])<select id="room-map-filter" aria-label="{{ __('အခန်းအခြေအနေ စစ်ရန်') }}"><option value="all">{{ __('အားလုံး') }}</option>@foreach(\App\Models\Room::STATUSES as $status=>$label)<option value="{{ $status }}">{{ __($label) }}</option>@endforeach</select></div>
            </div>
            <div class="floor-switch" role="group" aria-label="{{ __('အထပ်ရွေးရန်') }}">
                <button type="button" class="floor-choice active" data-floor-filter="all" aria-pressed="true">{{ __('အထပ်အားလုံး') }}</button>
                @foreach($floors as $floor=>$floorRooms)
                <button type="button" class="floor-choice" data-floor-filter="{{ $floor }}" aria-pressed="false">{{ $floor }} {{ __('ထပ်') }} <span>{{ $floorRooms->count() }}</span></button>
                @endforeach
            </div>
            <div class="room-map" id="room-map">
                @forelse($floors as $floor=>$floorRooms)
                <section class="floor-group" data-floor-group="{{ $floor }}" aria-label="{{ __('အထပ်') }} {{ $floor }} {{ __('အခန်းများ') }}">
                    <div class="floor-heading"><h3>{{ $floor }} {{ __('ထပ်') }}</h3><span>{{ $floorRooms->count() }} {{ __('ခန်း') }}</span></div>
                    <div class="floor-room-grid">
                    @foreach($floorRooms as $room)
                        <a class="room-tile {{ $room->status }}" data-room-status="{{ $room->status }}"
                           href="{{ $room->activeStay ? route('stays.show', $room->activeStay) : ($room->status === 'available' ? route('stays.create', ['room'=>$room->id]) : route('rooms.index', ['status'=>$room->status])) }}"
                           title="{{ __('အထပ်') }} {{ $floor }} · {{ __('အခန်း') }} {{ $room->number }} · {{ __(\App\Models\Room::STATUSES[$room->status]) }}{{ $room->activeStay ? ' · '.$room->activeStay->guest_name : '' }}{{ $room->nextReservation ? ' · Booking '.$room->nextReservation->arrival_at->format('d M H:i') : '' }}"
                           aria-label="{{ __('အထပ်') }} {{ $floor }}၊ {{ __('အခန်း') }} {{ $room->number }}၊ {{ __(\App\Models\Room::STATUSES[$room->status]) }}">
                            <span class="tile-indicator"></span><strong>{{ $room->number }}</strong><small>{{ $room->roomType->name }}</small>@if($room->nextReservation)<small class="tile-booked">{{ __('Booking') }} · {{ $room->nextReservation->arrival_at->format('d M') }}</small>@endif
                        </a>
                    @endforeach
                    </div>
                </section>
                @empty<div class="map-empty">{{ __('အခန်းမရှိသေးပါ။') }} <a href="{{ route('rooms.index') }}">{{ __('အခန်းထည့်ရန်') }}</a></div>@endforelse
                <p class="map-empty" id="room-map-empty" hidden>{{ __('ရွေးထားသော အထပ်နှင့်အခြေအနေအတွက် အခန်းမရှိပါ။') }}</p>
            </div>
            <div class="map-legend">@foreach(\App\Models\Room::STATUSES as $status=>$label)<span><i class="legend-dot {{ $status }}"></i>{{ __($label) }} <b>{{ $counts[$status] ?? 0 }}</b></span>@endforeach</div>
            <div class="map-footnote"><span>{{ __('အခန်းကိုနှိပ်ပြီး ဧည့်သည်ကြည့် / Check-in လုပ်နိုင်ပါတယ်။') }}</span><div class="record-actions"><a href="{{ route('reservations.index') }}">{{ __('Booking စာရင်း') }}</a><a href="{{ route('rooms.index') }}">{{ __('အခန်းများ') }}</a></div></div>
        </div>
    </section>
    <section class="glass-panel summary-panel" aria-labelledby="summary-heading">
        <div class="glass-inner"><div class="panel-toolbar"><h2 id="summary-heading">{{ __('ယနေ့အနှစ်ချုပ်') }}</h2><span class="mini-date">@include('icon', ['name'=>'calendar']) {{ now()->format('d M') }}</span></div>
            <div class="summary-grid">
                <a class="summary-card mint" href="{{ route('rooms.index', ['status'=>'occupied']) }}"><span class="metric-icon">@include('icon', ['name'=>'home'])</span><strong>{{ $counts['occupied'] ?? 0 }}<small> / {{ $rooms->count() }}</small></strong><span>{{ __('ဧည့်သည်ရှိသောအခန်း') }}</span></a>
                <a class="summary-card lavender" href="{{ route('reports.daily') }}"><span class="metric-icon">@include('icon', ['name'=>'users'])</span><strong>{{ $checkIns }}<small> {{ __('ဦး') }}</small></strong><span>{{ __('ယနေ့ Check-in') }}</span></a>
                <a class="summary-card rose" href="{{ route('reports.daily') }}"><span class="metric-icon">@include('icon', ['name'=>'clock'])</span><strong>{{ $checkOuts }}<small> {{ __('ဦး') }}</small></strong><span>{{ __('ယနေ့ Check-out') }}</span></a>
                <a class="summary-card peach" href="{{ route('reports.daily') }}"><span class="metric-icon">@include('icon', ['name'=>'wallet'])</span><strong class="money-metric">{{ number_format($received) }}<small> MMK</small></strong><span>{{ __('ယနေ့ လက်ခံရရှိငွေ') }}</span></a>
            </div>
        </div>
    </section>
</div>
@if($rooms->where('status','dirty')->isNotEmpty())
<div class="cleaning-bar"><span>@include('icon', ['name'=>'check']) {{ __('သန့်ရှင်းရေးပြီးသောအခန်း') }}</span><div>@foreach($rooms->where('status','dirty') as $room)<form method="post" action="{{ route('rooms.status',$room) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="available"><button class="clean-room-button">{{ $room->number }} · {{ __('အခန်းသန့်ပြီး') }} ✓</button></form>@endforeach</div></div>
@endif
<section class="glass-panel booking-panel" aria-labelledby="guest-list-heading">
    <div class="glass-inner">
        <div class="booking-toolbar"><div class="panel-title"><h2 id="guest-list-heading">{{ __('လက်ရှိ တည်းခိုသူများ') }}</h2><span class="count-pill" id="guest-result-count" aria-live="polite">{{ $activeStays->count() }}</span></div>
            <label class="glass-search">@include('icon', ['name'=>'search'])<input id="guest-search" type="search" placeholder="{{ __('အမည်၊ ဖုန်း၊ အခန်း ရှာရန်') }}" aria-label="{{ __('လက်ရှိဧည့်သည်များ ရှာရန်') }}"></label>
            <select id="guest-sort" class="glass-select" aria-label="{{ __('ဧည့်သည်စာရင်း စီရန်') }}"><option value="latest">{{ __('ဝင်ချိန်အသစ်ဆုံး') }}</option><option value="checkout">{{ __('ထွက်ရန်အချိန်အလိုက်') }}</option><option value="room">{{ __('အခန်းအလိုက်') }}</option><option value="name">{{ __('အမည်အလိုက်') }}</option></select>
            <a class="btn coral" href="{{ route('stays.create') }}">@include('icon', ['name'=>'plus']) {{ __('ဧည့်သည် Check-in') }}</a>
        </div>
        <div class="glass-table-wrap"><table class="guest-table"><thead><tr><th>{{ __('ဧည့်သည်အမည်') }}</th><th>{{ __('ကျား / မ') }}</th><th>{{ __('အခန်း') }}</th><th>{{ __('ဝင်ချိန်') }}</th><th>{{ __('ထွက်ရန်အချိန်') }}</th><th>{{ __('ဖုန်းနံပါတ်') }}</th><th>{{ __('ကျန်ငွေ') }}</th></tr></thead><tbody id="guest-table-body">
            @foreach($activeStays as $stay)
            <tr data-guest-row data-row-link="{{ route('stays.show', $stay) }}" tabindex="0" role="link" aria-label="{{ $stay->guest_name }} {{ __('အသေးစိတ်ကြည့်ရန်') }}" data-name="{{ $stay->guest_name }}" data-room="{{ $stay->room->number }}" data-phone="{{ $stay->phone }}" data-checkin="{{ $stay->check_in_at->timestamp }}" data-checkout="{{ $stay->expected_check_out_at->timestamp }}">
                <td><a class="guest-name" href="{{ route('stays.show', $stay) }}"><span class="guest-avatar">{{ mb_substr($stay->guest_name,0,1) }}</span><strong>{{ $stay->guest_name }}</strong></a></td>
                <td>{{ isset(\App\Models\Stay::GENDERS[$stay->gender]) ? __(\App\Models\Stay::GENDERS[$stay->gender]) : '—' }}</td>
                <td><span class="room-pill">{{ $stay->room->number }}</span></td>
                <td><span class="date-cell">{{ $stay->check_in_at->format('d M Y') }}<small>{{ $stay->check_in_at->format('H:i') }}</small></span></td>
                <td><span class="date-cell">{{ $stay->expected_check_out_at->format('d M Y') }}<small>{{ $stay->expected_check_out_at->format('H:i') }}</small></span>@if($stay->expected_check_out_at->isPast())<span class="badge dirty">{{ __('အချိန်ကျော်') }}</span>@endif</td>
                <td>{{ $stay->phone }}</td><td><strong class="{{ $stay->balance > 0 ? 'balance-due' : '' }}">{{ number_format($stay->balance) }} <small class="inline-currency">K</small></strong></td>
            </tr>
            @endforeach
            <tr id="guest-empty-row" @if($activeStays->isNotEmpty()) hidden @endif><td colspan="7" class="empty">{{ $activeStays->isEmpty() ? __('လက်ရှိ တည်းခိုသူ မရှိသေးပါ။') : __('ရှာဖွေမှုနှင့်ကိုက်ညီသော ဧည့်သည် မရှိပါ။') }}</td></tr>
        </tbody></table></div>
        <div class="booking-footer"><span>{{ $activeStays->count() }} {{ __('ဦး') }} · {{ __('လက်ရှိ တည်းခိုမှုမှတ်တမ်း') }}</span><a href="{{ route('stays.index') }}">{{ __('ဧည့်သည်စာရင်း အားလုံး') }}</a></div>
    </div>
</section>
@endsection
