@extends('layouts.app')
@section('title', __('ဧည့်သည်ပြင်ရန်'))
@section('content')
<div class="page-heading"><div><h1>{{ $stay->guest_name }} · {{ __('ပြင်ရန်') }}</h1><p class="muted">{{ __('အခန်း') }} {{ $stay->room->number }} · {{ $stay->check_out_at ? __('ထွက်ပြီး') : __('တည်းခိုနေ') }}</p></div><a href="{{ route('stays.show', $stay) }}">{{ __('← အသေးစိတ်') }}</a></div>
<section class="panel narrow">
    <form method="post" action="{{ route('stays.update', $stay) }}" enctype="multipart/form-data">@csrf @method('PUT')
        <div class="form-grid">
            <label>{{ __('အမည်') }}<input name="guest_name" value="{{ old('guest_name', $stay->guest_name) }}" maxlength="150" required autofocus></label>
            <label>{{ __('ကျား / မ') }}<select name="gender" required><option value="">{{ __('ရွေးပါ') }}</option>@foreach(\App\Models\Stay::GENDERS as $value => $label)<option value="{{ $value }}" @selected(old('gender', $stay->gender) === $value)>{{ __($label) }}</option>@endforeach</select></label>
            <label>{{ __('ဖုန်းနံပါတ်') }}<input name="phone" value="{{ old('phone', $stay->phone) }}" type="tel" maxlength="40" required></label>
            <label>{{ __('ထွက်ရန် သတ်မှတ်ချိန်') }}<input name="expected_check_out_at" type="datetime-local" value="{{ old('expected_check_out_at', $stay->expected_check_out_at->format('Y-m-d\TH:i')) }}" required></label>
            <label>{{ __('အထောက်အထားဖိုင် ထပ်တင်ရန်') }}<input name="identity_documents[]" type="file" multiple accept="image/jpeg,image/png,image/webp,application/pdf" data-max-files="{{ 5 - $stay->identityDocuments->count() }}" @disabled($stay->identityDocuments->count() >= 5)><small class="muted">နောက်ထပ် {{ 5 - $stay->identityDocuments->count() }} ဖိုင် · တစ်ဖိုင် 5 MB အထိ</small></label>
        </div>
        <label>{{ __('မှတ်ချက်') }}<textarea name="notes" maxlength="2000" rows="3">{{ old('notes', $stay->notes) }}</textarea></label>
        <button class="btn">{{ __('ပြောင်းလဲမှု သိမ်းမည်') }}</button>
    </form>
</section>
@endsection
