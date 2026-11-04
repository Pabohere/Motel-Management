<fieldset class="panel top-space" data-companion-row>
    <legend>{{ __('အတူတည်းခိုသူ') }}</legend>
    @if(!empty($companion['id']))<input type="hidden" name="companions[{{ $index }}][id]" value="{{ $companion['id'] }}">@endif
    <div class="form-grid">
        <label>{{ __('အမည်') }}<input name="companions[{{ $index }}][name]" value="{{ $companion['name'] ?? '' }}" maxlength="150" required></label>
        <label>{{ __('လူကြီး / ကလေး') }}<select name="companions[{{ $index }}][type]" required><option value="adult" @selected(($companion['type'] ?? 'adult') === 'adult')>{{ __('လူကြီး') }}</option><option value="child" @selected(($companion['type'] ?? '') === 'child')>{{ __('ကလေး') }}</option></select></label>
        <label>{{ __('ကျား / မ') }}<select name="companions[{{ $index }}][gender]"><option value="">{{ __('မသတ်မှတ်ရသေး') }}</option>@foreach(\App\Models\Stay::GENDERS as $value => $label)<option value="{{ $value }}" @selected(($companion['gender'] ?? '') === $value)>{{ __($label) }}</option>@endforeach</select></label>
        <label>{{ __('အသက် (မဖြည့်လည်းရသည်)') }}<input name="companions[{{ $index }}][age]" type="number" min="0" max="120" value="{{ $companion['age'] ?? '' }}"></label>
        <label>{{ __('ဖုန်းနံပါတ် (မဖြည့်လည်းရသည်)') }}<input name="companions[{{ $index }}][phone]" type="tel" maxlength="40" value="{{ $companion['phone'] ?? '' }}"></label>
    </div>
    <button type="button" class="btn secondary" data-remove-companion>{{ __('ဤဧည့်သည်ကို စာရင်းမှဖယ်ရန်') }}</button>
</fieldset>
