<div class="room-grid">
@forelse($rooms as $room)
    <article class="room-card {{ $room->trashed() ? 'archived' : $room->status }}">
        <a class="card-hit" href="{{ route('rooms.show', $room) }}" aria-label="{{ __('အခန်း') }} {{ $room->number }} {{ __('အသေးစိတ်ကြည့်ရန်') }}"></a>
        <div class="room-top">
            <span class="room-number">{{ $room->number }}</span>
            <span class="badge {{ $room->trashed() ? 'maintenance' : $room->status }}">{{ $room->trashed() ? 'Archive' : __(\App\Models\Room::STATUSES[$room->status]) }}</span>
        </div>
        <p class="muted">{{ $room->floor->number }} {{ __('ထပ်') }} · {{ $room->roomType->name }} <span class="room-rate">{{ number_format($room->rate) }} K / {{ __('ည') }}</span></p>
        <div class="room-guest">
            @if($room->trashed())<span class="muted">{{ __('ဤအခန်းကို archive ထဲသိမ်းထားသည်။') }}</span>
            @elseif($room->activeStay)<a href="{{ route('stays.show', $room->activeStay) }}">{{ $room->activeStay->guest_name }}</a><small>{{ __('ထွက်ရန်') }} {{ $room->activeStay->expected_check_out_at->format('d M · H:i') }}</small>
            @elseif($room->status === 'available')<span class="muted">{{ __('ဧည့်သည်အသစ် လက်ခံနိုင်သည်') }}</span>
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
@empty
    <div class="empty">{{ __('ဤအခြေအနေရှိသောအခန်း မရှိသေးပါ။') }}</div>
@endforelse
</div>
