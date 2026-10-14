<form class="filters room-filters" method="get" action="{{ $action }}" data-auto-filter>
    <label>{{ __('အထပ်') }}<select name="floor_id"><option value="">{{ __('အထပ်အားလုံး') }}</option>@foreach($floors as $floor)<option value="{{ $floor->id }}" @selected((string) request('floor_id') === (string) $floor->id)>{{ $floor->number }} {{ __('ထပ်') }}</option>@endforeach</select></label>
    <label>{{ __('အခြေအနေ') }}<select name="status"><option value="">{{ __('အားလုံး') }}</option>@foreach(\App\Models\Room::STATUSES as $status => $label)<option value="{{ $status }}" @selected(request('status') === $status)>{{ __($label) }}</option>@endforeach @if(auth()->user()->isManager())<option value="archived" @selected(request('status') === 'archived')>Archive</option>@endif</select></label>
    <div class="filter-actions"><button class="btn secondary" type="submit">{{ __('စစ်မည်') }}</button><a class="filter-reset" href="{{ $action }}">{{ __('ပြန်စမည်') }}</a></div>
</form>
