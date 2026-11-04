<div class="room-state-legend" aria-label="{{ __('အခန်းအခြေအနေ') }}">
    @foreach(\App\Models\Room::STATUSES as $status => $label)
        <span class="room-legend-item {{ $status }}">@include('icon', ['name' => match ($status) { 'occupied' => 'bed', 'dirty' => 'broom', 'maintenance' => 'tool', default => 'check' }])<span>{{ __($label) }}</span></span>
    @endforeach
    <span class="room-legend-item booked">@include('icon', ['name' => 'calendar'])<span>{{ __('Booking') }}</span></span>
</div>
@forelse($rooms->groupBy(fn ($room) => $room->floor->number) as $floorNumber => $floorRooms)
<section class="room-floor {{ $roomDisplay === 'grid' ? 'room-floor--grid' : '' }}" aria-label="{{ __('အထပ်') }} {{ $floorNumber }}">
    <div class="room-floor-heading">
        <div><span class="room-floor-kicker">{{ __('အထပ်') }} {{ $floorNumber }}</span><h2>{{ $floorNumber }} {{ __('ထပ်') }} <span class="room-floor-count">{{ $floorRooms->count() }}@if(($floorRoomCounts[$floorNumber] ?? 0) > $floorRooms->count()) / {{ $floorRoomCounts[$floorNumber] }}@endif {{ __('ခန်း') }}</span></h2></div>
        @if($roomDisplay === 'rail')
        <div class="room-rail-controls">
            <button type="button" class="room-rail-button" data-rail-scroll="-1" aria-label="{{ __('အခန်းများကို ဘယ်ဘက်သို့ ရွှေ့ရန်') }}">←</button>
            <button type="button" class="room-rail-button" data-rail-scroll="1" aria-label="{{ __('အခန်းများကို ညာဘက်သို့ ရွှေ့ရန်') }}">→</button>
        </div>
        @endif
    </div>
    <div class="room-rail" @if($roomDisplay === 'rail') tabindex="0" @endif aria-label="{{ __('အထပ်') }} {{ $floorNumber }} {{ __('အခန်းများ') }}">
@foreach($floorRooms as $room)
    @include('rooms.card')
@endforeach
    </div>
</section>
@empty
<div class="panel empty">{{ __('ဤအခြေအနေရှိသောအခန်း မရှိသေးပါ။') }}</div>
@endforelse
