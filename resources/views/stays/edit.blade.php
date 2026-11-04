@extends('layouts.app')
@section('title', __('ဧည့်သည်ပြင်ရန်'))
@section('content')
<div class="page-heading"><div><h1>{{ $stay->guest_name }} · {{ __('ပြင်ရန်') }}</h1><p class="muted">{{ __('အခန်း') }} {{ $stay->room->number }} · {{ $stay->check_out_at ? __('ထွက်ပြီး') : __('တည်းခိုနေ') }}</p></div><a href="{{ route('stays.show', $stay) }}">{{ __('← အသေးစိတ်') }}</a></div>
<section class="panel narrow">
    <form method="post" action="{{ route('stays.update', $stay) }}" enctype="multipart/form-data">@csrf @method('PUT')
        <div class="form-grid">
            <label>{{ __('အမည်') }}<input name="guest_name" value="{{ old('guest_name', $stay->guest_name) }}" maxlength="150" required autofocus>@if($errors->has('guest_name') || $errors->has('guest_name.*'))<small class="field-error" role="alert">{{ $errors->first('guest_name') ?: $errors->first('guest_name.*') }}</small>@endif</label>
            <label>{{ __('ကျား / မ') }}<select name="gender" required><option value="">{{ __('ရွေးပါ') }}</option>@foreach(\App\Models\Stay::GENDERS as $value => $label)<option value="{{ $value }}" @selected(old('gender', $stay->gender) === $value)>{{ __($label) }}</option>@endforeach</select>@if($errors->has('gender') || $errors->has('gender.*'))<small class="field-error" role="alert">{{ $errors->first('gender') ?: $errors->first('gender.*') }}</small>@endif</label>
            <label>{{ __('ဖုန်းနံပါတ်') }}<input name="phone" value="{{ old('phone', $stay->phone) }}" type="tel" maxlength="40" required>@if($errors->has('phone') || $errors->has('phone.*'))<small class="field-error" role="alert">{{ $errors->first('phone') ?: $errors->first('phone.*') }}</small>@endif</label>
            <label>{{ __('ထွက်ရန် သတ်မှတ်ချိန်') }}<input name="expected_check_out_at" type="datetime-local" value="{{ old('expected_check_out_at', $stay->expected_check_out_at->format('Y-m-d\TH:i')) }}" required>@if($errors->has('expected_check_out_at') || $errors->has('expected_check_out_at.*'))<small class="field-error" role="alert">{{ $errors->first('expected_check_out_at') ?: $errors->first('expected_check_out_at.*') }}</small>@endif</label>
@if(auth()->user()->canUploadDocuments())
            <label>{{ __('အထောက်အထားပိုင်ရှင်') }}<select name="identity_guest_id"><option value="">{{ __('အဓိကဆက်သွယ်သူ') }}</option>@foreach($stay->guests as $guest)<option value="{{ $guest->id }}" @selected(old('identity_guest_id') == $guest->id)>{{ $guest->name }} · {{ $guest->identity_documents_count }} / 5</option>@endforeach</select><small>{{ __('ဧည့်သည်အသစ်အတွက် ဦးစွာ သိမ်းပြီးနောက် အထောက်အထားတင်ပါ။ တစ်ဦးလျှင် ၅ ဖိုင်အထိ။') }}</small></label>
            <label>{{ __('အထောက်အထားဖိုင် ထပ်တင်ရန်') }}<input name="identity_documents[]" type="file" multiple accept="image/jpeg,image/png,image/webp,application/pdf" data-max-files="5"><small class="muted">{{ __('တစ်ဦးလျှင် ၅ ဖိုင်အထိ · တစ်ဖိုင် 5 MB အထိ') }}</small>@if($errors->has('identity_documents') || $errors->has('identity_documents.*'))<small class="field-error" role="alert">{{ $errors->first('identity_documents') ?: $errors->first('identity_documents.*') }}</small>@endif</label>
@endif
        </div>
        @include('stays.companions', ['companionRows' => $stay->guests->where('is_primary', false)->values()->toArray()])
        <label>{{ __('မှတ်ချက်') }}<textarea name="notes" maxlength="2000" rows="3">{{ old('notes', $stay->notes) }}</textarea>@if($errors->has('notes') || $errors->has('notes.*'))<small class="field-error" role="alert">{{ $errors->first('notes') ?: $errors->first('notes.*') }}</small>@endif</label>
        <button class="btn">{{ __('ပြောင်းလဲမှု သိမ်းမည်') }}</button>
    </form>
</section>
@endsection
