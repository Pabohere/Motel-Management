@extends('layouts.app')
@section('title', __('ဝန်ထမ်းများ'))
@section('content')
<div class="staff-page">
    <div class="page-heading"><div><h1>{{ __('ဝန်ထမ်းစာရင်း') }}</h1><p class="muted">{{ __('ဝန်ထမ်းကိုယ်ရေးအကျဉ်း၊ ရာထူးနှင့် လုပ်ပိုင်ခွင့်များ') }}</p></div><a class="btn" href="#add-staff">{{ __('+ ဝန်ထမ်းထည့်မည်') }}</a></div>
    <section class="staff-directory" aria-label="{{ __('ဝန်ထမ်းစာရင်း') }}">
        @forelse($users as $user)
        <article class="panel staff-profile">
            <div class="staff-profile-heading">@if($user->photo_path)<img class="guest-avatar staff-photo" src="{{ route('users.photo', $user) }}" alt="{{ $user->name }}" width="64" height="64" loading="lazy">@else<span class="guest-avatar">{{ mb_substr($user->name, 0, 1) }}</span>@endif<div><h2>{{ $user->name }}</h2><p>{{ $user->position ?: __('ရာထူး မသတ်မှတ်ရသေးပါ။') }}</p><small class="muted">{{ $user->email }}</small></div><span class="badge {{ $user->active ? 'available' : 'maintenance' }}">{{ $user->active ? __('အသုံးပြုနိုင်') : __('ပိတ်ထား') }}</span></div>
            <div class="staff-biography"><h3>{{ __('ကိုယ်ရေးအကျဉ်း') }}</h3><p>{{ $user->biography ?: __('ကိုယ်ရေးအကျဉ်း မထည့်ရသေးပါ။') }}</p></div>
            <p class="staff-permission">{{ __('လုပ်ပိုင်ခွင့်') }}: {{ $user->isManager() ? __('မန်နေဂျာ') : __('ဝန်ထမ်း') }}</p>
            <details class="staff-editor"><summary>{{ __('ဝန်ထမ်းအချက်အလက် ပြင်ရန်') }}</summary>
                <form method="post" enctype="multipart/form-data" action="{{ route('users.update', $user) }}">@csrf @method('PUT')
                    <div class="form-grid">
                        <label>{{ __('အမည်') }}<input name="name" value="{{ $user->name }}" maxlength="150" required></label>
                        <label>{{ __('ရာထူး') }}<input name="position" value="{{ $user->position }}" maxlength="120" placeholder="{{ __('ဥပမာ — Receptionist၊ Housekeeping') }}"></label>
                        <label>{{ __('လုပ်ပိုင်ခွင့်') }}<select name="role"><option value="staff" @selected($user->role === 'staff')>{{ __('ဝန်ထမ်း') }}</option><option value="manager" @selected($user->role === 'manager')>{{ __('မန်နေဂျာ') }}</option></select></label>
                        <label>{{ __('အကောင့်အခြေအနေ') }}<select name="active"><option value="1" @selected($user->active)>{{ __('အသုံးပြုနိုင်') }}</option><option value="0" @selected(! $user->active)>{{ __('ပိတ်ထား') }}</option></select></label>
                        <label class="staff-bio-field">{{ __('ဝန်ထမ်းဓာတ်ပုံ') }}<input name="photo" type="file" accept="image/jpeg,image/png,image/webp"><small>{{ __('JPG၊ PNG၊ WebP · အများဆုံး 2 MB · 4096 × 4096 px') }}</small></label>
                        @if($user->photo_path)<label class="staff-photo-remove"><input name="remove_photo" type="checkbox" value="1">{{ __('လက်ရှိဓာတ်ပုံ ဖယ်ရှားရန်') }}</label>@endif
                        <label class="staff-bio-field">{{ __('ကိုယ်ရေးအကျဉ်း') }}<textarea name="biography" rows="5" maxlength="4000">{{ $user->biography }}</textarea></label>
                    </div>
                    <button class="btn secondary">{{ __('သိမ်းမည်') }}</button>
                </form>
            </details>
        </article>
        @empty<div class="panel empty">{{ __('ဝန်ထမ်း မရှိသေးပါ။') }}</div>@endforelse
    </section>
    <section class="panel top-space" id="add-staff"><h2>{{ __('ဝန်ထမ်းအသစ် ထည့်ရန်') }}</h2><form method="post" enctype="multipart/form-data" action="{{ route('users.store') }}">@csrf
        <div class="form-grid">
            <label>{{ __('အမည်') }}<input name="name" value="{{ old('name') }}" maxlength="150" required></label>
            <label>{{ __('ရာထူး') }}<input name="position" value="{{ old('position') }}" maxlength="120" placeholder="{{ __('ဥပမာ — Receptionist၊ Housekeeping') }}"></label>
            <label>{{ __('အီးမေးလ်') }}<input name="email" value="{{ old('email') }}" type="email" maxlength="255" required></label>
            <label>{{ __('လုပ်ပိုင်ခွင့်') }}<select name="role"><option value="staff" @selected(old('role') === 'staff')>{{ __('ဝန်ထမ်း') }}</option><option value="manager" @selected(old('role') === 'manager')>{{ __('မန်နေဂျာ') }}</option></select></label>
            <label>{{ __('စကားဝှက်') }}<input name="password" type="password" minlength="12" maxlength="128" autocomplete="new-password" required></label>
            <label>{{ __('စကားဝှက် ထပ်ရိုက်ပါ') }}<input name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required></label>
            <label class="staff-bio-field">{{ __('ဝန်ထမ်းဓာတ်ပုံ') }}<input name="photo" type="file" accept="image/jpeg,image/png,image/webp"><small>{{ __('JPG၊ PNG၊ WebP · အများဆုံး 2 MB · 4096 × 4096 px') }}</small></label>
            <label class="staff-bio-field">{{ __('ကိုယ်ရေးအကျဉ်း') }}<textarea name="biography" rows="5" maxlength="4000">{{ old('biography') }}</textarea></label>
        </div>
        <p class="muted staff-role-note">{{ __('ရာထူးသည် လုပ်ငန်းတာဝန်အမည်ဖြစ်ပြီး စနစ်လုပ်ပိုင်ခွင့်နှင့် သီးခြားဖြစ်သည်။') }}</p>
        <p class="muted">{{ __('ဝန်ထမ်း — ဧည့်သည်ဝင်ထွက်၊ ငွေစာရင်း၊ သန့်ရှင်းရေးပြီးမှတ်ခြင်းနဲ့ နေ့စဉ်စာရင်း။') }}<br>{{ __('မန်နေဂျာ — ဝန်ထမ်းလုပ်ဆောင်ချက်အားလုံး၊ အခန်း/နှုန်းပြင်ခြင်း၊ ပြင်ဆင်နေသတ်မှတ်ခြင်း၊ ဝန်ထမ်းစီမံခြင်းနဲ့ ပြောင်းလဲမှုမှတ်တမ်း။') }}</p>
        <button class="btn">{{ __('+ ဝန်ထမ်းထည့်မည်') }}</button>
    </form></section>
</div>
@endsection
