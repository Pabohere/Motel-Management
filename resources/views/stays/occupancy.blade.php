<section class="panel top-space">
    <h2>{{ __('တည်းခိုသူစာရင်း') }} · {{ $stay->guest_count }} {{ __('ဦး') }}</h2>
    <p>{{ __('လူကြီး') }} {{ $stay->guests->isEmpty() ? 1 : $stay->guests->where('type', 'adult')->count() }} · {{ __('ကလေး') }} {{ $stay->guests->where('type', 'child')->count() }}</p>
    @forelse($stay->guests as $guest)
        <div class="ledger-line"><div><strong>{{ $guest->name }}</strong><small>{{ __($guest->type === 'child' ? 'ကလေး' : 'လူကြီး') }} @if($guest->is_primary) · {{ __('အဓိကဆက်သွယ်သူ') }} @endif @if($guest->age !== null) · {{ __('အသက်') }} {{ $guest->age }} @endif · {{ $guest->phone }}</small></div><span>{{ isset(\App\Models\Stay::GENDERS[$guest->gender]) ? __(\App\Models\Stay::GENDERS[$guest->gender]) : '—' }}</span></div>
    @empty<p>{{ $stay->guest_name }}</p>@endforelse
</section>
<section class="panel top-space">
    <h2>{{ __('Extra bed မှတ်တမ်း') }}</h2>
    <p>{{ __('လက်ရှိထိုးထားသော Extra bed') }}: {{ $stay->extraBeds->whereNull('returned_at')->sum('quantity') }}</p>
    @foreach($stay->extraBeds as $bed)
        <div class="ledger-line"><div>{{ $bed->quantity }} × {{ $bed->nights }} {{ __('ည') }} × {{ number_format($bed->rate) }} MMK = {{ number_format($bed->quantity * $bed->nights * $bed->rate) }} MMK<small>{{ $bed->created_at->format('d M Y H:i') }} · {{ $bed->returned_at ? __('ပြန်သိမ်းပြီး') : __('အသုံးပြုနေ') }}</small></div>
        @if(!$stay->trashed() && !$stay->check_out_at && !$bed->returned_at)<form method="post" action="{{ route('stays.extra-beds.return', [$stay, $bed]) }}" data-confirm="{{ __('Bed ပြန်သိမ်းမည်။ သဘောတူထားသော ကျသင့်ငွေကို ဆက်ထိန်းထားမည်။') }}">@csrf @method('PATCH')<button class="btn secondary">{{ __('Bed ပြန်သိမ်းရန်') }}</button></form>@endif</div>
    @endforeach
    @if(!$stay->trashed() && !$stay->check_out_at)
        <form method="post" action="{{ route('stays.extra-beds.store', $stay) }}">
            @csrf<input name="request_key" type="hidden" value="{{ Str::uuid() }}">
            <div class="form-grid">
                <label>{{ __('ထပ်ထည့်မည့် Bed အရေအတွက်') }}<input name="quantity" type="number" min="1" max="{{ $stay->room->max_extra_beds }}" value="{{ old('quantity', 1) }}" required></label>
                <label>{{ __('ကျသင့်မည့် ညအရေအတွက်') }}<input name="nights" type="number" min="1" max="3650" value="{{ old('nights', 1) }}" required></label>
                <label>{{ __('တစ်လုံး တစ်ညနှုန်း (MMK)') }}<input name="rate" type="number" min="0" max="100000000" value="{{ old('rate', $stay->room->extra_bed_rate) }}" required></label>
            </div>
            <p class="muted">{{ __('ညအရေအတွက်ကို သေချာစစ်ပါ။ ရက်တိုးလျှင် အပိုကျသင့်ငွေကို သီးခြားမှတ်တမ်းတင်ပါ။ Bed ပြန်သိမ်းခြင်းက ကျသင့်ငွေကို အလိုအလျောက် မလျှော့ပါ။') }}</p>
            <button class="btn secondary" @disabled($stay->room->max_extra_beds <= $stay->extraBeds->whereNull('returned_at')->sum('quantity'))>{{ __('Extra bed နှင့် ကျသင့်ငွေ ထည့်ရန်') }}</button>
        </form>
    @endif
</section>
