@extends('layouts.app')
@section('title', 'အနှစ်ချုပ်')
@section('content')
<div class="page-heading dashboard-heading">
    <div><span class="eyebrow">MOTEL / OVERVIEW</span><h1>မိုတယ် အနှစ်ချုပ်</h1><p class="muted">သင့်မိုတယ်ရဲ့ နေ့စဉ်လုပ်ငန်းခွင်၊ တစ်နေရာတည်းမှာ။</p></div>
    <span class="live-pill"><span class="dot"></span> {{ now()->format('d M Y') }} · လက်ရှိအခြေအနေ</span>
</div>
<div class="dashboard-top">
    <section class="glass-panel room-map-panel" aria-labelledby="room-map-heading">
        <div class="glass-inner">
            <div class="panel-toolbar"><div class="panel-title"><span class="title-icon">@include('icon', ['name'=>'grid'])</span><h2 id="room-map-heading">အခန်းအခြေအနေ</h2><span class="count-pill">{{ $rooms->count() }}</span></div>
                <div class="map-filter">@include('icon', ['name'=>'filter'])<select id="room-map-filter" aria-label="အခန်းအခြေအနေ စစ်ရန်"><option value="all">အားလုံး</option>@foreach(\App\Models\Room::STATUSES as $status=>$label)<option value="{{ $status }}">{{ $label }}</option>@endforeach</select></div>
            </div>
            <div class="floor-switch" role="group" aria-label="အထပ်ရွေးရန်">
                <button type="button" class="floor-choice active" data-floor-filter="all" aria-pressed="true">အထပ်အားလုံး</button>
                @foreach($floors as $floor=>$floorRooms)
                <button type="button" class="floor-choice" data-floor-filter="{{ $floor }}" aria-pressed="false">{{ $floor }} ထပ် <span>{{ $floorRooms->count() }}</span></button>
                @endforeach
            </div>
            <div class="room-map" id="room-map">
                @forelse($floors as $floor=>$floorRooms)
                <section class="floor-group" data-floor-group="{{ $floor }}" aria-label="{{ $floor }} ထပ်ရှိ အခန်းများ">
                    <div class="floor-heading"><h3>{{ $floor }} ထပ်</h3><span>{{ $floorRooms->count() }} ခန်း</span></div>
                    <div class="floor-room-grid">
                    @foreach($floorRooms as $room)
                        <a class="room-tile {{ $room->status }}" data-room-status="{{ $room->status }}"
                           href="{{ $room->activeStay ? route('stays.show', $room->activeStay) : ($room->status === 'available' ? route('stays.create', ['room'=>$room->id]) : route('rooms.index', ['status'=>$room->status])) }}"
                           title="{{ $floor }} ထပ် · {{ $room->number }} · {{ \App\Models\Room::STATUSES[$room->status] }}{{ $room->activeStay ? ' · '.$room->activeStay->guest_name : '' }}"
                           aria-label="{{ $floor }} ထပ်၊ အခန်း {{ $room->number }}၊ {{ \App\Models\Room::STATUSES[$room->status] }}">
                            <span class="tile-indicator"></span><strong>{{ $room->number }}</strong><small>{{ $room->roomType->name }}</small>
                        </a>
                    @endforeach
                    </div>
                </section>
                @empty<div class="map-empty">အခန်းမရှိသေးပါ။ <a href="{{ route('rooms.index') }}">အခန်းထည့်ရန် ↗</a></div>@endforelse
                <p class="map-empty" id="room-map-empty" hidden>ရွေးထားသော အထပ်နှင့်အခြေအနေအတွက် အခန်းမရှိပါ။</p>
            </div>
            <div class="map-legend">@foreach(\App\Models\Room::STATUSES as $status=>$label)<span><i class="legend-dot {{ $status }}"></i>{{ $label }} <b>{{ $counts[$status] ?? 0 }}</b></span>@endforeach</div>
            <div class="map-footnote"><span>အခန်းကိုနှိပ်ပြီး ဧည့်သည်ကြည့် / Check-in လုပ်နိုင်ပါတယ်။</span><a href="{{ route('rooms.index') }}">အခန်းများ @include('icon', ['name'=>'arrow'])</a></div>
        </div>
    </section>
    <section class="glass-panel summary-panel" aria-labelledby="summary-heading">
        <div class="glass-inner"><div class="panel-toolbar"><h2 id="summary-heading">ယနေ့အနှစ်ချုပ်</h2><span class="mini-date">@include('icon', ['name'=>'calendar']) {{ now()->format('d M') }}</span></div>
            <div class="summary-grid">
                <a class="summary-card mint" href="{{ route('rooms.index', ['status'=>'occupied']) }}"><span class="metric-icon">@include('icon', ['name'=>'home'])</span><strong>{{ $counts['occupied'] ?? 0 }}<small> / {{ $rooms->count() }}</small></strong><span>ဧည့်သည်ရှိသောအခန်း</span></a>
                <a class="summary-card lavender" href="{{ route('reports.daily') }}"><span class="metric-icon">@include('icon', ['name'=>'users'])</span><strong>{{ $checkIns }}<small> ဦး</small></strong><span>ယနေ့ Check-in</span></a>
                <a class="summary-card rose" href="{{ route('reports.daily') }}"><span class="metric-icon">@include('icon', ['name'=>'clock'])</span><strong>{{ $checkOuts }}<small> ဦး</small></strong><span>ယနေ့ Check-out</span></a>
                <a class="summary-card peach" href="{{ route('reports.daily') }}"><span class="metric-icon">@include('icon', ['name'=>'wallet'])</span><strong class="money-metric">{{ number_format($received) }}<small> MMK</small></strong><span>ယနေ့ လက်ခံရရှိငွေ</span></a>
            </div>
        </div>
    </section>
</div>
@if($rooms->where('status','dirty')->isNotEmpty())
<div class="cleaning-bar"><span>@include('icon', ['name'=>'check']) သန့်ရှင်းရေးပြီးသောအခန်း</span><div>@foreach($rooms->where('status','dirty') as $room)<form method="post" action="{{ route('rooms.status',$room) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="available"><button class="clean-room-button">{{ $room->number }} · အခန်းသန့်ပြီး ✓</button></form>@endforeach</div></div>
@endif
<section class="glass-panel booking-panel" aria-labelledby="guest-list-heading">
    <div class="glass-inner">
        <div class="booking-toolbar"><div class="panel-title"><h2 id="guest-list-heading">လက်ရှိ တည်းခိုသူများ</h2><span class="count-pill" id="guest-result-count" aria-live="polite">{{ $activeStays->count() }}</span></div>
            <label class="glass-search">@include('icon', ['name'=>'search'])<input id="guest-search" type="search" placeholder="အမည်၊ ဖုန်း၊ အခန်း ရှာရန်" aria-label="လက်ရှိဧည့်သည်များ ရှာရန်"></label>
            <select id="guest-sort" class="glass-select" aria-label="ဧည့်သည်စာရင်း စီရန်"><option value="checkout">ထွက်ရန်အချိန်အလိုက်</option><option value="room">အခန်းအလိုက်</option><option value="name">အမည်အလိုက်</option></select>
            <a class="btn coral" href="{{ route('stays.create') }}">@include('icon', ['name'=>'plus']) ဧည့်သည် Check-in</a>
        </div>
        <div class="glass-table-wrap"><table class="guest-table"><thead><tr><th>ဧည့်သည်အမည်</th><th>ကျား / မ</th><th>အခန်း</th><th>ဝင်ချိန်</th><th>ထွက်ရန်အချိန်</th><th>ဖုန်းနံပါတ်</th><th>ကျန်ငွေ</th><th></th></tr></thead><tbody id="guest-table-body">
            @foreach($activeStays as $stay)
            <tr data-guest-row data-name="{{ $stay->guest_name }}" data-room="{{ $stay->room->number }}" data-phone="{{ $stay->phone }}" data-checkout="{{ $stay->expected_check_out_at->timestamp }}">
                <td><a class="guest-name" href="{{ route('stays.show', $stay) }}"><span class="guest-avatar">{{ mb_substr($stay->guest_name,0,1) }}</span><strong>{{ $stay->guest_name }}</strong></a></td>
                <td>{{ \App\Models\Stay::GENDERS[$stay->gender] ?? '—' }}</td>
                <td><span class="room-pill">{{ $stay->room->number }}</span></td>
                <td><span class="date-cell">{{ $stay->check_in_at->format('d M Y') }}<small>{{ $stay->check_in_at->format('H:i') }}</small></span></td>
                <td><span class="date-cell">{{ $stay->expected_check_out_at->format('d M Y') }}<small>{{ $stay->expected_check_out_at->format('H:i') }}</small></span>@if($stay->expected_check_out_at->isPast())<span class="badge dirty">အချိန်ကျော်</span>@endif</td>
                <td>{{ $stay->phone }}</td><td><strong class="{{ $stay->balance > 0 ? 'balance-due' : '' }}">{{ number_format($stay->balance) }} <small class="inline-currency">K</small></strong></td>
                <td><a class="row-open" href="{{ route('stays.show',$stay) }}" aria-label="{{ $stay->guest_name }} အသေးစိတ်ကြည့်ရန်">@include('icon', ['name'=>'arrow'])</a></td>
            </tr>
            @endforeach
            <tr id="guest-empty-row" @if($activeStays->isNotEmpty()) hidden @endif><td colspan="8" class="empty">{{ $activeStays->isEmpty() ? 'လက်ရှိ တည်းခိုသူ မရှိသေးပါ။' : 'ရှာဖွေမှုနှင့်ကိုက်ညီသော ဧည့်သည် မရှိပါ။' }}</td></tr>
        </tbody></table></div>
        <div class="booking-footer"><span>{{ $activeStays->count() }} ဦး · လက်ရှိ တည်းခိုမှုမှတ်တမ်း</span><a href="{{ route('stays.index') }}">ဧည့်သည်စာရင်း အားလုံး @include('icon', ['name'=>'arrow'])</a></div>
    </div>
</section>
@endsection
