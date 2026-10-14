@extends('layouts.app')
@section('title', __('အခန်းအသေးစိတ်'))
@section('content')
<div class="page-heading">
    <div><span class="eyebrow">{{ $room->floor->number }} {{ __('ထပ်') }} · {{ $room->roomType->name }}</span><h1>{{ __('အခန်း') }} {{ $room->number }}</h1><p class="muted">{{ number_format($room->rate) }} MMK / {{ __('ည') }} · {{ $room->trashed() ? __('Archive ထဲတွင်ရှိ') : __(\App\Models\Room::STATUSES[$room->status]) }}</p></div>
    <a href="{{ route('rooms.index') }}">{{ __('← အခန်းများ') }}</a>
</div>
<div class="record-actions">
    @if(auth()->user()->isManager())
        @if($room->trashed())
            <form method="post" action="{{ route('rooms.restore', $room) }}">@csrf<button class="btn">{{ __('အခန်းပြန်ယူမည်') }}</button></form>
        @else
            <a class="btn secondary" href="{{ route('rooms.edit', $room) }}">{{ __('အခန်းပြင်ရန်') }}</a>
            <form method="post" action="{{ route('rooms.destroy', $room) }}" data-confirm="{{ __('ဤအခန်းကို archive ထဲရွှေ့မည်။ ပြန်ယူနိုင်ပါသည်။ ဆက်လုပ်မလား။') }}">@csrf @method('DELETE')<button class="btn danger" @disabled($room->status === 'occupied')>{{ __('အခန်းဖျက်ရန်') }}</button></form>
        @endif
    @endif
    @if(!$room->trashed() && $room->status === 'available')<a class="btn" href="{{ route('stays.create', ['room' => $room->id]) }}">+ Check-in</a>@endif
</div>
@if(!$room->trashed() && $room->status === 'occupied')<p class="muted">{{ __('ဧည့်သည်ရှိနေသောအခန်းကို Check-out မလုပ်မီ ဖျက်၍မရပါ။') }}</p>@endif
<section class="panel top-space">
    <h2>{{ __('အခန်းတည်းခိုမှုမှတ်တမ်း') }}</h2>
    <div class="monthly-table-scroll"><table><thead><tr><th>{{ __('ဧည့်သည်') }}</th><th>{{ __('ဝင်ချိန်') }}</th><th>{{ __('ထွက်ချိန်') }}</th><th>{{ __('အခြေအနေ') }}</th><th></th></tr></thead><tbody>
        @forelse($stays as $stay)
            <tr data-row-link="{{ route('stays.show', $stay) }}" tabindex="0" role="link" aria-label="{{ $stay->guest_name }} {{ __('အသေးစိတ်ကြည့်ရန်') }}"><td>{{ $stay->guest_name }}</td><td>{{ $stay->check_in_at->format('d M Y H:i') }}</td><td>{{ $stay->check_out_at?->format('d M Y H:i') ?? __('တည်းခိုနေ') }}</td><td>{{ $stay->trashed() ? 'Archive' : ($stay->check_out_at ? __('ထွက်ပြီး') : __('တည်းခိုနေ')) }}</td><td>{{ __('အသေးစိတ်ကြည့်ရန်') }}</td></tr>
        @empty<tr><td colspan="5" class="empty">{{ __('တည်းခိုမှုမှတ်တမ်း မရှိသေးပါ။') }}</td></tr>@endforelse
    </tbody></table></div>
</section>
@endsection
