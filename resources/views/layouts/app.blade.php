<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <script>try { document.documentElement.dataset.theme = localStorage.getItem('motel-theme') === 'dark' ? 'dark' : 'light'; } catch (_) { document.documentElement.dataset.theme = 'light'; }</script>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preload" href="{{ asset('fonts/noto-sans-myanmar-variable.ttf') }}" as="font" type="font/ttf" crossorigin>
    <title>@yield('title') · Motel</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}">
    <link rel="stylesheet" href="{{ asset('glass.css') }}?v={{ filemtime(public_path('glass.css')) }}">
</head>
<body><div class="shell">
    <aside class="sidebar">
        <a href="{{ route('dashboard') }}" class="brand"><span class="brand-icon">M</span><span>MOTEL<small>{{ __('ဝန်ထမ်းသုံး စီမံခန့်ခွဲမှု') }}</small></span></a>
        <p class="nav-label">{{ __('လုပ်ငန်းဆောင်ရွက်မှု') }}</p>
        <nav>
            @foreach(['dashboard'=>['အနှစ်ချုပ်','grid'],'rooms.index'=>['အခန်းများ','home'],'stays.index'=>['ဧည့်သည်စာရင်း','users'],'reservations.index'=>['Booking စာရင်း','calendar'],'reports.daily'=>['နေ့စဉ်စာရင်း','calendar']] as $route => $item)
                <a class="{{ request()->routeIs($route) ? 'active' : '' }}" href="{{ route($route) }}">@include('icon', ['name'=>$item[1]]){{ __($item[0]) }}</a>
            @endforeach
            @if(auth()->user()->isManager())
                <p class="nav-label">{{ __('စီမံခန့်ခွဲမှု') }}</p>
                <a href="{{ route('reports.monthly') }}" class="{{ request()->routeIs('reports.monthly*') ? 'active' : '' }}">@include('icon', ['name'=>'calendar']){{ __('လစဉ်စာရင်း') }}</a>
                <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.*') ? 'active' : '' }}">@include('icon', ['name'=>'users']){{ __('ဝန်ထမ်းများ') }}</a>
                <a href="{{ route('audit.index') }}" class="{{ request()->routeIs('audit.*') ? 'active' : '' }}">@include('icon', ['name'=>'clock']){{ __('ပြောင်းလဲမှုမှတ်တမ်း') }}</a>
            @endif
        </nav>
        <div class="sidebar-bottom"><span class="avatar">{{ mb_substr(auth()->user()->name,0,1) }}</span><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->isManager() ? __('မန်နေဂျာ') : __('ဝန်ထမ်း') }}</small><a href="{{ route('password.edit') }}">{{ __('စကားဝှက်ပြောင်းရန်') }}</a><form method="post" action="{{ route('logout') }}">@csrf<button class="logout">{{ __('ထွက်ရန်') }}</button></form></div>
    </aside>
    <main>
        <header class="topbar"><span>{{ __('လုပ်ငန်းခွင်') }} <span class="muted">/ @yield('title')</span></span><div class="topbar-tools">
            <button type="button" class="theme-toggle" data-theme-toggle data-label-dark="{{ __('Dark mode') }}" data-label-light="{{ __('Light mode') }}" aria-label="{{ __('အမှောင် / အလင်း ပြောင်းရန်') }}" aria-pressed="false">☾ <span>{{ __('Dark mode') }}</span></button>
            <form class="language-switch" method="post" action="{{ route('locale.update') }}" aria-label="{{ __('ဘာသာစကား') }}">@csrf
                <button type="submit" name="locale" value="my" class="{{ app()->getLocale() === 'my' ? 'active' : '' }}" aria-pressed="{{ app()->getLocale() === 'my' ? 'true' : 'false' }}">မြန်မာ</button>
                <button type="submit" name="locale" value="en" class="{{ app()->getLocale() === 'en' ? 'active' : '' }}" aria-pressed="{{ app()->getLocale() === 'en' ? 'true' : 'false' }}">English</button>
            </form>
            <span class="date">{{ now()->format('d M Y · H:i') }} <span class="dot"></span> Yangon</span>
        </div></header>
        <div class="content">@if(session('success'))<div class="alert success" role="status">✓ {{ session('success') }}</div>@endif @if($errors->any())<div class="alert error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif @yield('content')<footer>{{ __('ဝန်ထမ်းသုံးစနစ် · ငွေကြေး MMK · အချိန် Asia/Yangon') }}</footer></div>
    </main>
</div><script src="{{ asset('app.js') }}?v={{ filemtime(public_path('app.js')) }}" defer></script></body></html>
