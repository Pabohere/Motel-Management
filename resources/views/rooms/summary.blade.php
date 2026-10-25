@php
    $summaryCounts = $summaryRooms->countBy('status');
    $total = $summaryRooms->count();
    $occupied = $summaryCounts['occupied'] ?? 0;
    $occupancy = $total ? round($occupied / $total * 100) : 0;
    $typeGroups = $summaryRooms->groupBy(fn ($room) => $room->roomType->name);
@endphp
<section class="room-summary" aria-label="{{ __('ယနေ့အနှစ်ချုပ်') }}">
    <div class="reference-metrics">
        @foreach([
            ['label' => __('အခန်းစုစုပေါင်း'), 'value' => $total, 'icon' => 'home', 'href' => route('rooms.all'), 'note' => __('အထပ်အားလုံး')],
            ['label' => __('ဧည့်သည်ရှိသောအခန်း'), 'value' => $occupied, 'icon' => 'users', 'href' => route('rooms.index', ['status' => 'occupied']), 'note' => $occupancy.'% · '.__('အခန်းအသုံးပြုမှု')],
            ['label' => __('အားလပ်သောအခန်း'), 'value' => $summaryCounts['available'] ?? 0, 'icon' => 'home', 'href' => route('rooms.index', ['status' => 'available']), 'note' => __('ဧည့်သည်အသစ် လက်ခံနိုင်သည်')],
            ['label' => __('သန့်ရှင်းရေးလိုသောအခန်း'), 'value' => $summaryCounts['dirty'] ?? 0, 'icon' => 'check', 'href' => route('rooms.index', ['status' => 'dirty']), 'note' => __('သန့်ရှင်းရေး ပြုလုပ်ရန်')],
        ] as $metric)
            <a class="reference-metric" href="{{ $metric['href'] }}">
                <span class="metric-label">{{ $metric['label'] }}</span>
                <div class="metric-value"><strong>{{ $metric['value'] }}</strong><span>@include('icon', ['name' => $metric['icon']])</span></div>
                <small>{{ $metric['note'] }}</small>
                <span class="metric-bars" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
            </a>
        @endforeach
    </div>
    <div class="type-overview">
        <h2>{{ __('အခန်းအသုံးပြုမှု') }}</h2>
        <div class="type-overview-body">
            <div class="occupancy-chart" style="--occupancy: {{ $occupancy }}"><span><strong>{{ $occupancy }}<small>%</small></strong><small>{{ __('ဧည့်သည်ရှိ') }}</small></span></div>
            <div class="type-percentages">
                @forelse($typeGroups as $typeName => $typeRooms)
                    <div><span>{{ $typeName }}</span><strong>{{ round($typeRooms->where('status', 'occupied')->count() / $typeRooms->count() * 100) }}%</strong></div>
                @empty
                    <p class="muted">{{ __('အခန်းမရှိသေးပါ။') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</section>
