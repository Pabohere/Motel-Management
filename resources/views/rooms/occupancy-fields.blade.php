<div class="form-grid">
    <label>{{ __('ပုံမှန်လက်ခံနိုင်သည့် လူဦးရေ') }}<input name="capacity" type="number" min="1" max="50" value="{{ old('capacity', $room->capacity ?? 2) }}" required></label>
    <label>{{ __('ထပ်ထိုးနိုင်သည့် Extra bed အများဆုံး') }}<input name="max_extra_beds" type="number" min="0" max="10" value="{{ old('max_extra_beds', $room->max_extra_beds ?? 0) }}" required></label>
    <label>{{ __('Extra bed တစ်လုံး တစ်ညနှုန်း (MMK)') }}<input name="extra_bed_rate" type="number" min="0" max="100000000" value="{{ old('extra_bed_rate', $room->extra_bed_rate ?? 0) }}" required></label>
</div>
<p class="muted">{{ __('လူကြီးနှင့် ကလေးအားလုံးကို လူဦးရေတွင် ထည့်တွက်သည်။ Extra bed တစ်လုံးလျှင် လူတစ်ဦး ထပ်လက်ခံနိုင်သည်။') }}</p>
