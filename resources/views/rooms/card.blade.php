<article data-room-status="{{ $room->status }}" class="room-status-card room-status-card--square {{ $room->trashed() ? 'archived' : $room->status }} {{ ! $room->trashed() && ! $room->activeStay && $room->nextReservation ? 'has-booking' : '' }}">
        <a class="card-hit" href="{{ ($dashboardCard ?? false) && $room->activeStay ? route('stays.show', $room->activeStay) : (($dashboardCard ?? false) && $room->status === 'available' ? route('stays.create', ['room' => $room->id]) : route('rooms.show', $room)) }}" aria-label="{{ __('အခန်း') }} {{ $room->number }} {{ __('အသေးစိတ်ကြည့်ရန်') }}"></a>
        <div class="room-status-header">
            <div class="room-status-identity"><span class="room-status-number">{{ $room->number }}</span><span class="room-status-floor">{{ $room->floor->number }} {{ __('ထပ်') }}</span></div>
            <span class="room-status-badge {{ $room->trashed() ? 'maintenance' : $room->status }}">{{ $room->trashed() ? 'Archive' : __(\App\Models\Room::STATUSES[$room->status]) }}</span>
        </div>
        <p class="room-status-details"><span>{{ $room->roomType->name }}</span><span class="room-status-rate">{{ number_format($room->rate) }} K / {{ __('ည') }}</span></p>
        <div class="room-status-guest">
            @if($room->trashed())<span class="muted">{{ __('ဤအခန်းကို archive ထဲသိမ်းထားသည်။') }}</span>
            @elseif($room->activeStay)<a href="{{ route('stays.show', $room->activeStay) }}" title="{{ $room->activeStay->guest_name }}">{{ $room->activeStay->guest_name }}</a><small>{{ __('ထွက်ရန်') }} {{ $room->activeStay->expected_check_out_at->format('d M · H:i') }}</small>
            @elseif($room->status === 'available')<span class="muted">{{ __('ဧည့်သည်အသစ် လက်ခံနိုင်သည်') }}</span>@if($room->nextReservation)<small><a href="{{ route('reservations.index') }}">{{ __('Booking') }} · {{ $room->nextReservation->arrival_at->format('d M H:i') }}</a></small>@endif
            @elseif($room->status === 'dirty')<span class="muted">{{ __('သန့်ရှင်းရေး ပြုလုပ်ရန်') }}</span>
            @else<span class="muted">{{ __('ပြင်ဆင်မှု ဆောင်ရွက်နေသည်') }}</span>@endif
        </div>
        @include('rooms.status-icons')
        <div class="room-status-actions">
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
