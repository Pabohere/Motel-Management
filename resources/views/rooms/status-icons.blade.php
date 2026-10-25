@php
    $statusIcon = $room->trashed() ? 'archive' : match ($room->status) {
        'occupied' => 'bed', 'dirty' => 'broom', 'maintenance' => 'tool', default => 'check',
    };
    $statusLabel = $room->trashed() ? 'Archive' : __(\App\Models\Room::STATUSES[$room->status]);
@endphp
<span class="room-status-icons">
    <span class="room-signal room-signal--status" role="img" aria-label="{{ $statusLabel }}" title="{{ $statusLabel }}">@include('icon', ['name' => $statusIcon])</span>
    @if($compact ?? false)
        @if($room->nextReservation && ! $room->activeStay)<span class="room-signal" role="img" aria-label="{{ __('Booking') }}" title="{{ __('Booking') }}">@include('icon', ['name' => 'calendar'])</span>@endif
    @else
        <a class="room-signal" href="{{ route('rooms.show', $room) }}" aria-label="{{ __('အခန်း') }} {{ $room->number }} · {{ __('အသေးစိတ်ကြည့်ရန်') }}" title="{{ __('အသေးစိတ်ကြည့်ရန်') }}">@include('icon', ['name' => 'door'])</a>
        @if(! $room->trashed())
            @if($room->activeStay)
                <a class="room-signal" href="{{ route('stays.show', $room->activeStay) }}" aria-label="{{ $room->activeStay->guest_name }} · {{ __('အသေးစိတ်ကြည့်ရန်') }}" title="{{ $room->activeStay->guest_name }}">@include('icon', ['name' => 'person'])</a>
            @elseif($room->nextReservation)
                <a class="room-signal" href="{{ route('reservations.index') }}" aria-label="{{ __('Booking') }} · {{ $room->nextReservation->arrival_at->format('d M H:i') }}" title="{{ __('Booking စာရင်း') }}">@include('icon', ['name' => 'calendar'])</a>
            @elseif($room->status === 'available')
                <a class="room-signal" href="{{ route('stays.create', ['room' => $room->id]) }}" aria-label="{{ __('အခန်း') }} {{ $room->number }} · Check-in" title="Check-in">@include('icon', ['name' => 'person-plus'])</a>
            @endif
        @endif
    @endif
</span>
