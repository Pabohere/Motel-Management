@php
$next = ['dirty' => ['cleaning', 'Start cleaning'], 'cleaning' => ['cleaned', 'Finish cleaning'], 'cleaned' => ['inspected', 'Inspect room'], 'inspected' => ['available', 'Mark room ready']][$room->status] ?? null;
@endphp
@if($next && (in_array($room->status, ['dirty', 'cleaning']) || auth()->user()->isManager()))
<form method="post" action="{{ route('rooms.status', $room) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $next[0] }}"><button class="btn small">{{ __($next[1]) }}</button></form>
@elseif($room->status === 'cleaned')<span class="muted">{{ __('Waiting for manager inspection') }}</span>@endif
