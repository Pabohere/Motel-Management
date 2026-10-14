@extends('layouts.app')
@section('title', __('ဧည့်သည်စာရင်း'))
@section('content')
<div class="page-heading"><div><h1>{{ __('ဧည့်သည်စာရင်း') }}</h1><p class="muted">{{ __('တည်းခိုမှုနဲ့ ငွေစာရင်း မှတ်တမ်းများ') }}</p></div><a class="btn" href="{{ route('stays.create') }}">{{ __('+ ဧည့်သည် Check-in') }}</a></div>
<form class="filters" method="get">
    <input name="q" value="{{ request('q') }}" maxlength="100" placeholder="{{ __('အမည် / ဖုန်းနံပါတ်ဖြင့် ရှာရန်') }}" aria-label="{{ __('ဧည့်သည်ရှာရန်') }}">
    <select name="status" aria-label="{{ __('တည်းခိုမှုအခြေအနေ') }}">
        <option value="">{{ __('အားလုံး') }}</option>
        <option value="active" @selected(request('status') === 'active')>{{ __('လက်ရှိတည်းခိုသူ') }}</option>
        <option value="completed" @selected(request('status') === 'completed')>{{ __('ထွက်ပြီးသူ') }}</option>
        @if(auth()->user()->isManager())<option value="archived" @selected(request('status') === 'archived')>Archive</option>@endif
    </select>
    <select name="source" aria-label="{{ __('Walk-in / Booking') }}"><option value="">{{ __('Walk-in / Booking အားလုံး') }}</option><option value="walk_in" @selected(request('source') === 'walk_in')>Walk-in</option><option value="booking" @selected(request('source') === 'booking')>Booking</option></select>
    <button class="btn secondary">{{ __('ရှာမည်') }}</button><a href="{{ route('stays.index') }}">{{ __('ပြန်စမည်') }}</a>
</form>
<div class="panel table-panel"><div class="monthly-table-scroll"><table>
    <thead><tr><th>{{ __('ဧည့်သည်') }}</th><th>{{ __('အရင်းအမြစ်') }}</th><th>{{ __('ကျား / မ') }}</th><th>{{ __('အခန်း') }}</th><th>{{ __('အထောက်အထား') }}</th><th>{{ __('ဝင်ချိန်') }}</th><th>{{ __('ထွက်ချိန် / ထွက်ရန်') }}</th><th>{{ __('အခြေအနေ') }}</th><th>{{ __('ကျန်ငွေ') }}</th></tr></thead>
    <tbody>
    @forelse($stays as $stay)
        <tr data-row-link="{{ route('stays.show', $stay) }}" tabindex="0" role="link" aria-label="{{ $stay->guest_name }} {{ __('အသေးစိတ်ကြည့်ရန်') }}">
            <td><strong>{{ $stay->guest_name }}</strong><small>{{ $stay->phone }}</small><div class="record-actions compact">
                @if($stay->trashed())
                    <form method="post" action="{{ route('stays.restore', $stay) }}">@csrf<button class="text-link">{{ __('ပြန်ယူရန်') }}</button></form>
                @else
                    @if(!$stay->check_out_at || auth()->user()->isManager())<a href="{{ route('stays.edit', $stay) }}">{{ __('ပြင်ရန်') }}</a>@endif
                    @if($stay->check_out_at && auth()->user()->isManager())<form method="post" action="{{ route('stays.destroy', $stay) }}" data-confirm="{{ __('ဤဧည့်သည်မှတ်တမ်းကို archive ထဲရွှေ့မည်။ ငွေစာရင်းမပျောက်ပါ။ ဆက်လုပ်မလား။') }}">@csrf @method('DELETE')<button class="text-link destructive">{{ __('ဖျက်ရန်') }}</button></form>@endif
                @endif
            </div></td>
            <td>{{ __($stay->source === 'booking' ? 'Booking' : 'Walk-in') }}</td>
            <td>{{ isset(\App\Models\Stay::GENDERS[$stay->gender]) ? __(\App\Models\Stay::GENDERS[$stay->gender]) : '—' }}</td>
            <td><a href="{{ route('rooms.show', $stay->room) }}">{{ $stay->room->number }}</a></td>
            <td>@if($stay->identityDocuments->isNotEmpty())<a class="identity-list-link" href="{{ route('stays.show', $stay) }}#identity-gallery">@if($stay->identityDocuments->first()->isImage())<img src="{{ route('stays.identity.show', [$stay, $stay->identityDocuments->first()]) }}" alt="{{ $stay->guest_name }} ၏ အထောက်အထားပုံ" loading="lazy">@else<span class="identity-list-pdf">PDF</span>@endif<span>{{ $stay->identityDocuments->count() }} {{ __('ဖိုင် · ကြည့်ရန်') }}</span></a>@else<span class="muted">—</span>@endif</td>
            <td>{{ $stay->check_in_at->format('d M Y H:i') }}</td>
            <td>{{ ($stay->check_out_at ?? $stay->expected_check_out_at)->format('d M Y H:i') }}</td>
            <td><span class="badge {{ $stay->trashed() ? 'maintenance' : ($stay->check_out_at ? 'available' : 'occupied') }}">{{ $stay->trashed() ? 'Archive' : ($stay->check_out_at ? __('ထွက်ပြီး') : __('တည်းခိုနေ')) }}</span></td>
            <td>{{ number_format($stay->balance) }} K</td>
        </tr>
    @empty<tr><td colspan="9" class="empty">{{ __('ဧည့်သည်မှတ်တမ်း မတွေ့ပါ။') }}</td></tr>@endforelse
    </tbody>
</table></div></div>
<div class="pagination">{{ $stays->links('pagination') }}</div>
@endsection
