@extends('layouts.app')
@section('title', __('အနှစ်ချုပ်'))
@section('content')
<h1 class="sr-only">{{ __('အနှစ်ချုပ်') }}</h1>
<section class="frontdesk-tools" aria-label="{{ __('Front desk') }}">
    <div class="quick-actions"><a class="btn secondary" href="{{ route('stays.index', ['status' => 'active']) }}">{{ __('Check-out') }}</a><a class="btn" href="{{ route('stays.create') }}">+ {{ __('Check-in') }}</a><a class="btn secondary" href="{{ route('reservations.create') }}">{{ __('New reservation') }}</a></div>
    <form class="quick-search" method="get" action="{{ route('stays.index') }}"><label class="sr-only" for="quick-guest-search">{{ __('Find guest or room') }}</label><input id="quick-guest-search" name="q" type="search" maxlength="100" placeholder="{{ __('Name, phone or room number') }}"><button class="btn secondary">{{ __('ရှာမည်') }}</button></form>
</section>
<section class="attention-panel" aria-labelledby="attention-heading">
    <h2 id="attention-heading">{{ __('Needs attention') }}</h2>
    <div class="attention-grid">
        <a href="{{ route('reservations.index', ['status' => 'booked', 'today' => 1]) }}"><strong>{{ $arrivals }}</strong><span>{{ __('Arrivals today') }}</span></a>
        <a href="{{ route('stays.index', ['status' => 'active', 'attention' => 'departures']) }}"><strong>{{ $departures }}</strong><span>{{ __('Departures remaining today') }}</span></a>
        <a href="{{ route('stays.index', ['status' => 'active', 'attention' => 'overdue']) }}" @if($overdue) class="attention-warning" @endif><strong>{{ $overdue }}</strong><span>{{ __('Overdue checkouts') }}</span></a>
        <a href="{{ route('rooms.index', ['status' => 'dirty']) }}"><strong>{{ $counts['dirty'] ?? 0 }}</strong><span>{{ __('Rooms to clean') }}</span></a>
        <a href="{{ route('stays.index') }}"><strong>{{ number_format($outstanding) }} K</strong><span>{{ __('Outstanding guest balances') }}</span></a>
        <a href="{{ route('rooms.index', ['status' => 'cleaned']) }}"><strong>{{ $counts['cleaned'] ?? 0 }}</strong><span>{{ __('Waiting for inspection') }}</span></a>
    </div>
</section>
@include('rooms.summary', ['summaryRooms' => $rooms])
<div class="dashboard-top">
    <section class="glass-panel room-map-panel" aria-labelledby="room-map-heading">
        <div class="glass-inner">
            <div class="panel-toolbar"><div class="panel-title"><span class="title-icon">@include('icon', ['name'=>'grid'])</span><h2 id="room-map-heading">{{ __('အခန်းအခြေအနေ') }}</h2><span class="count-pill">{{ $rooms->count() }}</span></div>
                <label class="room-number-search"><span class="sr-only">{{ __('Find room') }}</span><input id="room-number-search" type="search" placeholder="{{ __('Room number') }}" maxlength="30"></label><div class="map-filter">@include('icon', ['name'=>'filter'])<select id="room-map-filter" aria-label="{{ __('အခန်းအခြေအနေ စစ်ရန်') }}"><option value="all">{{ __('အားလုံး') }}</option>@foreach(\App\Models\Room::STATUSES as $status=>$label)<option value="{{ $status }}">{{ __($label) }}</option>@endforeach</select></div>
            </div>
            <div class="floor-switch" role="group" aria-label="{{ __('အထပ်ရွေးရန်') }}">
                <button type="button" class="floor-choice active" data-floor-filter="all" aria-pressed="true">{{ __('အထပ်အားလုံး') }}</button>
                @foreach($floors as $floor=>$floorRooms)
                <button type="button" class="floor-choice" data-floor-filter="{{ $floor }}" aria-pressed="false">{{ $floor }} {{ __('ထပ်') }} <span>{{ $floorRooms->count() }}</span></button>
                @endforeach
            </div>
            <div class="room-map" id="room-map">
                @forelse($floors as $floor=>$floorRooms)
                <section class="floor-group room-floor room-floor--swipe" data-floor-group="{{ $floor }}" aria-label="{{ __('အထပ်') }} {{ $floor }} {{ __('အခန်းများ') }}">
                    <div class="floor-heading"><h3>{{ $floor }} {{ __('ထပ်') }} <span>{{ $floorRooms->count() }} {{ __('ခန်း') }}</span></h3>
                        <div class="room-rail-controls">
                            <button type="button" class="room-rail-button" data-rail-scroll="-1" aria-controls="floor-rail-{{ $floor }}" aria-label="{{ __('အခန်းများကို ဘယ်ဘက်သို့ ရွှေ့ရန်') }}">←</button>
                            <button type="button" class="room-rail-button" data-rail-scroll="1" aria-controls="floor-rail-{{ $floor }}" aria-label="{{ __('အခန်းများကို ညာဘက်သို့ ရွှေ့ရန်') }}">→</button>
                        </div>
                    </div>
                    <div class="room-rail" id="floor-rail-{{ $floor }}" tabindex="0" aria-label="{{ __('အထပ်') }} {{ $floor }} {{ __('အခန်းများ') }}">
                    @foreach($floorRooms as $room)
                        @include('rooms.card')
                    @endforeach
                    </div>
                </section>
                @empty<div class="map-empty">{{ __('အခန်းမရှိသေးပါ။') }} <a href="{{ route('rooms.index') }}">{{ __('အခန်းထည့်ရန်') }}</a></div>@endforelse
                <p class="map-empty" id="room-map-empty" hidden>{{ __('ရွေးထားသော အထပ်နှင့်အခြေအနေအတွက် အခန်းမရှိပါ။') }}</p>
            </div>
            <div class="map-legend">@foreach(\App\Models\Room::STATUSES as $status=>$label)<span><i class="legend-dot {{ $status }}"></i>{{ __($label) }} <b>{{ $counts[$status] ?? 0 }}</b></span>@endforeach<span><i class="legend-dot booked"></i>{{ __('Booking') }} <b>{{ $rooms->filter(fn ($room) => $room->nextReservation && ! $room->activeStay)->count() }}</b></span></div>
            <div class="map-footnote"><span>{{ __('Select a room for details. Use Check in to add a guest.') }}</span><div class="record-actions"><a href="{{ route('reservations.index') }}">{{ __('Booking စာရင်း') }}</a><a href="{{ route('rooms.index') }}">{{ __('အခန်းများ') }}</a></div></div>
        </div>
    </section>

</div>
<div class="daily-strip">
    <div><span>{{ __('ယနေ့ Check-in') }}</span><strong>{{ $checkIns }}</strong></div>
    <div><span>{{ __('ယနေ့ Check-out') }}</span><strong>{{ $checkOuts }}</strong></div>
    <div><span>{{ __('ယနေ့ လက်ခံရရှိငွေ') }}</span><strong>{{ number_format($received) }} <small>MMK</small></strong></div>
    <a href="{{ route('reports.daily') }}">{{ __('နေ့စဉ်စာရင်း') }} →</a>
</div>
@if($rooms->whereIn('status', ['dirty', 'cleaning', 'cleaned', 'inspected'])->isNotEmpty())
<div class="cleaning-bar"><span>{{ __('Housekeeping') }}</span><div>@foreach($rooms->whereIn('status', ['dirty', 'cleaning', 'cleaned', 'inspected']) as $room)<div><strong>{{ $room->number }}</strong> @include('rooms.housekeeping-action')</div>@endforeach</div></div>
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
                <td class="guest-checkout"><div class="checkout-stack"><span class="date-cell">{{ $stay->expected_check_out_at->format('d M Y') }}<small>{{ $stay->expected_check_out_at->format('H:i') }}</small></span>@if($stay->expected_check_out_at->isPast())<span class="badge dirty" title="{{ __('ထွက်ရန်သတ်မှတ်ချိန် ကျော်လွန်ပြီးဖြစ်သည်။') }}">{{ __('ထွက်ရန်ချိန်ကျော်') }}</span>@endif</div></td>
                <td>{{ $stay->phone }}</td><td><strong class="{{ $stay->balance > 0 ? 'balance-due' : '' }}">{{ number_format($stay->balance) }} <small class="inline-currency">K</small></strong></td>
            </tr>
            @endforeach
            <tr id="guest-empty-row" @if($activeStays->isNotEmpty()) hidden @endif><td colspan="7" class="empty">{{ $activeStays->isEmpty() ? __('လက်ရှိ တည်းခိုသူ မရှိသေးပါ။') : __('ရှာဖွေမှုနှင့်ကိုက်ညီသော ဧည့်သည် မရှိပါ။') }}</td></tr>
        </tbody></table></div>
        <div class="booking-footer"><span>{{ $activeStays->count() }} {{ __('ဦး') }} · {{ __('လက်ရှိ တည်းခိုမှုမှတ်တမ်း') }}</span><a href="{{ route('stays.index') }}">{{ __('ဧည့်သည်စာရင်း အားလုံး') }}</a></div>
    </div>
</section>
@endsection
