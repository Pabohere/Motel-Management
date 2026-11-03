<article data-room-status="{{ $room->status }}" data-room-number="{{ $room->number }}" class="room-status-card room-status-card--compact {{ $room->trashed() ? 'archived' : $room->status }} {{ ! $room->trashed() && ! $room->activeStay && $room->nextReservation ? 'has-booking' : '' }}">
    <a class="card-hit" href="{{ route('rooms.show', $room) }}" aria-label="{{ __('အခန်း') }} {{ $room->number }} {{ __('အသေးစိတ်ကြည့်ရန်') }}"></a>
    <div class="room-status-header">
        <div class="room-status-identity"><span class="room-status-number">{{ $room->number }}</span><span class="room-status-floor">{{ $room->floor->number }} {{ __('ထပ်') }}</span></div>
        <span class="room-status-badge">{{ $room->trashed() ? __('Archive') : __(\App\Models\Room::STATUSES[$room->status]) }}</span>
    </div>
    <p class="room-status-details"><span>{{ __($room->roomType->name) }}</span><span class="room-status-rate">{{ number_format($room->rate) }} {{ __('MMK / night') }}</span></p>
    @if($room->activeStay)
        <div class="room-status-guest"><a href="{{ route('stays.show', $room->activeStay) }}">{{ $room->activeStay->guest_name }}</a><small>{{ __('ထွက်ရန်') }} {{ $room->activeStay->expected_check_out_at->format('d M · H:i') }}</small></div>
    @elseif(! $room->trashed() && $room->nextReservation)
        <div class="room-status-guest"><small>{{ __('Booking') }} · {{ $room->nextReservation->arrival_at->format('d M H:i') }}</small></div>
    @endif
    <div class="room-status-actions">
        @if($room->trashed())
            @if(auth()->user()->isManager())<form method="post" action="{{ route('rooms.restore', $room) }}">@csrf<button class="btn secondary small">{{ __('ပြန်ယူမည်') }}</button></form>@endif
        @elseif($room->activeStay)
            <a class="btn secondary small" href="{{ route('stays.show', $room->activeStay) }}">{{ __('View guest') }}</a>
        @elseif($room->status === 'available')
            <a class="btn secondary small" href="{{ route('stays.create', ['room' => $room->id]) }}">+ {{ __('Check-in') }}</a>
        @elseif($room->status === 'dirty')
            <form method="post" action="{{ route('rooms.status', $room) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="available"><button class="btn small">{{ __('✓ အခန်းသန့်ပြီး') }}</button></form>
        @endif
        <details class="room-actions-menu">
            <summary aria-label="{{ __('More actions for room') }} {{ $room->number }}">{{ __('ပိုမို') }}</summary>
            <div><a href="{{ route('rooms.show', $room) }}">{{ __('အသေးစိတ်ကြည့်ရန်') }}</a>@if(auth()->user()->isManager() && ! $room->trashed())<a href="{{ route('rooms.edit', $room) }}">{{ __('ပြင်ရန်') }}</a>@endif</div>
        </details>
    </div>
</article>
