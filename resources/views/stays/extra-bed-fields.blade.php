<section class="top-space">
    <h3>{{ __('Extra bed') }}</h3>
    <p class="muted" id="room-capacity-hint" data-template="{{ __('ပုံမှန် :capacity ဦး · Extra bed :beds လုံးအထိ', ['capacity' => '{capacity}', 'beds' => '{beds}']) }}">{{ __('အခန်းတစ်ခန်းစီ၏ လူဦးရေနှင့် Extra bed ကန့်သတ်ချက်အတိုင်း ထည့်ပါ။') }}</p>
    <div class="form-grid">
        <label>{{ __('Extra bed အရေအတွက်') }}<input id="extra-beds" name="extra_beds" type="number" min="0" max="10" value="{{ old('extra_beds', $reservation->extra_beds ?? 0) }}" required></label>
        <label>{{ __('Extra bed အသုံးပြုမည့် ညအရေအတွက်') }}<input id="extra-bed-nights" name="extra_bed_nights" data-manual="{{ old('extra_bed_nights') !== null || isset($reservation) ? 'true' : 'false' }}" type="number" min="1" max="3650" value="{{ old('extra_bed_nights', $reservation->extra_bed_nights ?? 1) }}" required></label>
        <label>{{ __('Extra bed တစ်လုံး တစ်ညနှုန်း (MMK)') }}<input id="extra-bed-rate" name="extra_bed_rate" data-manual="{{ old('extra_bed_rate') !== null || isset($reservation) ? 'true' : 'false' }}" type="number" min="0" max="100000000" value="{{ old('extra_bed_rate', $reservation->extra_bed_rate ?? ($selectedRoom->extra_bed_rate ?? 0)) }}" required></label>
    </div>
    <p class="muted">{{ __('Extra bed ခ = အရေအတွက် × ညအရေအတွက် × တစ်ညနှုန်း။ အခန်းခအပြင် ထပ်ပေါင်းမည်။ 0 MMK ဖြင့် အခမဲ့ထည့်နိုင်သည်။ ရက်တိုးလျှင် ကျသင့်ငွေကို သီးခြားစစ်ဆေးပါ။') }}</p>
    @foreach(['extra_beds', 'extra_bed_nights', 'extra_bed_rate'] as $field)@error($field)<p class="field-error" role="alert">{{ $message }}</p>@enderror @endforeach
</section>
