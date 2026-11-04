@php
    $companionRows = old('companions', $companionRows ?? []);
@endphp
<section class="top-space" data-companions>
    <h3>{{ __('အတူတည်းခိုသူများ') }}</h3>
    <p class="muted">{{ __('အပေါ်က အဓိကဆက်သွယ်သူအပြင် တည်းခိုမည့် လူကြီးနှင့်ကလေး တစ်ဦးချင်းစီကို ထည့်ပါ။') }}</p>
    <input type="hidden" name="companions_present" value="1">
    <div data-companion-rows>
        @foreach($companionRows as $index => $companion)
            @include('stays.companion-row', ['index' => $index, 'companion' => $companion])
        @endforeach
    </div>
    <template data-companion-template>@include('stays.companion-row', ['index' => '__INDEX__', 'companion' => []])</template>
    <button type="button" class="btn secondary" data-add-companion>{{ __('+ ဧည့်သည်ထပ်ထည့်ရန်') }}</button>
    <p aria-live="polite" data-guest-count></p>
    @foreach($errors->getMessages() as $field => $messages)
        @if(str_starts_with($field, 'companions'))<p class="field-error" role="alert">{{ $messages[0] }}</p>@endif
    @endforeach
    <noscript><p>{{ __('ဧည့်သည်ထပ်ထည့်ရန် JavaScript ဖွင့်ထားရန် လိုအပ်သည်။') }}</p></noscript>
</section>
