@forelse($rooms->groupBy(fn ($room) => $room->floor->number) as $floorNumber => $floorRooms)
<section class="room-floor {{ $roomDisplay === 'grid' ? 'room-floor--grid' : '' }}" aria-label="{{ __('အထပ်') }} {{ $floorNumber }}">
    <div class="room-floor-heading">
        <div><span class="room-floor-kicker">{{ __('အထပ်') }} {{ $floorNumber }}</span><h2>{{ $floorNumber }} {{ __('ထပ်') }} <span class="room-floor-count">{{ $floorRooms->count() }}@if($roomDisplay === 'rail' && ($floorRoomCounts[$floorNumber] ?? 0) > $floorRooms->count()) / {{ $floorRoomCounts[$floorNumber] }}@endif {{ __('ခန်း') }}</span></h2></div>
        @if($roomDisplay === 'rail')
        <div class="room-rail-controls">
            <button type="button" class="room-rail-button" data-rail-scroll="-1" aria-label="{{ __('အခန်းများကို ဘယ်ဘက်သို့ ရွှေ့ရန်') }}">←</button>
            <button type="button" class="room-rail-button" data-rail-scroll="1" aria-label="{{ __('အခန်းများကို ညာဘက်သို့ ရွှေ့ရန်') }}">→</button>
        </div>
        @endif
    </div>
    <div class="room-rail" @if($roomDisplay === 'rail') tabindex="0" @endif aria-label="{{ __('အထပ်') }} {{ $floorNumber }} {{ __('အခန်းများ') }}">
@foreach($floorRooms as $room)
    <article class="room-card room-rail-card {{ $room->trashed() ? 'archived' : $room->status }}">
        <a class="card-hit" href="{{ route('rooms.show', $room) }}" aria-label="{{ __('အခန်း') }} {{ $room->number }} {{ __('အသေးစိတ်ကြည့်ရန်') }}"></a>
        <div class="room-card-hero">
            <span class="room-card-floor">{{ $room->floor->number }} {{ __('ထပ်') }}</span>
            <span class="room-number">{{ $room->number }}</span>
            <span class="badge {{ $room->trashed() ? 'maintenance' : $room->status }}">{{ $room->trashed() ? 'Archive' : __(\App\Models\Room::STATUSES[$room->status]) }}</span>
        </div>
        <p class="room-card-meta"><span>{{ $room->roomType->name }}</span><span class="room-rate">{{ number_format($room->rate) }} K / {{ __('ည') }}</span></p>
        <div class="room-guest">
            @if($room->trashed())<span class="muted">{{ __('ဤအခန်းကို archive ထဲသိမ်းထားသည်။') }}</span>
            @elseif($room->activeStay)<a href="{{ route('stays.show', $room->activeStay) }}">{{ $room->activeStay->guest_name }}</a><small>{{ __('ထွက်ရန်') }} {{ $room->activeStay->expected_check_out_at->format('d M · H:i') }}</small>
            @elseif($room->status === 'available')<span class="muted">{{ __('ဧည့်သည်အသစ် လက်ခံနိုင်သည်') }}</span>@if($room->nextReservation)<small><a href="{{ route('reservations.index') }}">{{ __('Booking') }} · {{ $room->nextReservation->arrival_at->format('d M H:i') }}</a></small>@endif
            @elseif($room->status === 'dirty')<span class="muted">{{ __('သန့်ရှင်းရေး ပြုလုပ်ရန်') }}</span>
            @else<span class="muted">{{ __('ပြင်ဆင်မှု ဆောင်ရွက်နေသည်') }}</span>@endif
        </div>
        <div class="room-actions">
            @if($room->trashed())
                @if(auth()->user()->isManager())<form method="post" action="{{ route('rooms.restore', $room) }}">@csrf<button class="btn secondary small">{{ __('ပြန်ယူမည်') }}</button></form>@endif
            @else
                @if($room->status === 'available')<a class="btn secondary small" href="{{ route('stays.create', ['room' => $room->id]) }}">+ Check-in</a>
                @elseif($room->status === 'dirty')<form method="post" action="{{ route('rooms.status', $room) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="available"><button class="btn small">{{ __('✓ အခန်းသန့်ပြီး') }}</button></form>
                @endif
                @if(auth()->user()->isManager())<a class="text-link" href="{{ route('rooms.edit', $room) }}">{{ __('ပြင်ရန်') }}</a>@endif
            @endif
        </div>
    </article>
@endforeach
    </div>
</section>
@empty
<div class="panel empty">{{ __('ဤအခြေအနေရှိသောအခန်း မရှိသေးပါ။') }}</div>
@endforelse
