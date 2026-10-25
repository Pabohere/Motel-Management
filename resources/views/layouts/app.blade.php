<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <script>try { document.documentElement.dataset.theme = localStorage.getItem('motel-theme') === 'dark' ? 'dark' : 'light'; } catch (_) { document.documentElement.dataset.theme = 'light'; }</script>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="preload" href="{{ asset('fonts/noto-sans-myanmar-variable.ttf') }}" as="font" type="font/ttf" crossorigin>
    <title>@yield('title') · Motel</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}">
    <link rel="stylesheet" href="{{ asset('glass.css') }}?v={{ filemtime(public_path('glass.css')) }}">
    <link rel="stylesheet" href="{{ asset('reference.css') }}?v={{ filemtime(public_path('reference.css')) }}">
    @include('pwa-head')
</head>
<body>
@php
    $navigation = [
        'dashboard' => ['label' => 'အနှစ်ချုပ်', 'short' => 'အနှစ်ချုပ်', 'icon' => 'grid', 'active' => request()->routeIs('dashboard')],
        'rooms.index' => ['label' => 'အခန်းများ', 'short' => 'အခန်းများ', 'icon' => 'home', 'active' => request()->routeIs('rooms.*')],
        'stays.index' => ['label' => 'ဧည့်သည်စာရင်း', 'short' => 'ဧည့်သည်', 'icon' => 'users', 'active' => request()->routeIs('stays.*')],
        'reservations.index' => ['label' => 'Booking စာရင်း', 'short' => 'Booking', 'icon' => 'calendar', 'active' => request()->routeIs('reservations.*')],
        'reports.daily' => ['label' => 'နေ့စဉ်စာရင်း', 'icon' => 'calendar', 'active' => request()->routeIs('reports.daily')],
    ];
    if (auth()->user()->isManager()) {
        $navigation += [
            'reports.monthly' => ['label' => 'လစဉ်စာရင်း', 'icon' => 'wallet', 'active' => request()->routeIs('reports.monthly*')],
            'users.index' => ['label' => 'ဝန်ထမ်းများ', 'icon' => 'users', 'active' => request()->routeIs('users.*')],
            'audit.index' => ['label' => 'ပြောင်းလဲမှုမှတ်တမ်း', 'icon' => 'clock', 'active' => request()->routeIs('audit.*')],
        ];
    }
    $primaryRoutes = ['dashboard', 'rooms.index', 'stays.index', 'reservations.index'];
    $moreActive = request()->routeIs('password.*') || collect($navigation)->except($primaryRoutes)->contains('active', true);
@endphp
<div class="shell">
    <aside class="sidebar">
        <a href="{{ route('dashboard') }}" class="brand profile-brand"><span class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span><span>{{ auth()->user()->name }}<small>{{ auth()->user()->isManager() ? __('မန်နေဂျာ') : __('ဝန်ထမ်း') }}</small></span><span class="profile-chevron" aria-hidden="true">⌄</span></a>
        <p class="nav-label">{{ __('လုပ်ငန်းဆောင်ရွက်မှု') }}</p>
        <nav aria-label="{{ __('အဓိကမီနူး') }}">
            @foreach($navigation as $route => $item)
                @if($route === 'reports.monthly')<p class="nav-label">{{ __('စီမံခန့်ခွဲမှု') }}</p>@endif
                <a class="{{ $item['active'] ? 'active' : '' }}" href="{{ route($route) }}" @if($item['active']) aria-current="page" @endif>@include('icon', ['name' => $item['icon']]){{ __($item['label']) }}</a>
            @endforeach
        </nav>
        <div class="sidebar-bottom">@include('pwa-install-button')<span class="avatar">{{ mb_substr(auth()->user()->name,0,1) }}</span><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->isManager() ? __('မန်နေဂျာ') : __('ဝန်ထမ်း') }}</small><a href="{{ route('password.edit') }}">{{ __('စကားဝှက်ပြောင်းရန်') }}</a><form method="post" action="{{ route('logout') }}">@csrf<button class="logout">{{ __('စနစ်မှထွက်ရန်') }}</button></form></div>
    </aside>
    <main>
        <header class="topbar"><strong class="topbar-title">@yield('title')</strong><div class="topbar-tools">
            <button type="button" class="theme-toggle" data-theme-toggle data-label-dark="{{ __('Dark mode') }}" data-label-light="{{ __('Light mode') }}" aria-label="{{ __('အမှောင် / အလင်း ပြောင်းရန်') }}" aria-pressed="false">☾ <span>{{ __('Dark mode') }}</span></button>
            <form class="language-switch" method="post" action="{{ route('locale.update') }}" aria-label="{{ __('ဘာသာစကား') }}">@csrf
                <button type="submit" name="locale" value="my" class="{{ app()->getLocale() === 'my' ? 'active' : '' }}" aria-pressed="{{ app()->getLocale() === 'my' ? 'true' : 'false' }}">မြန်မာ</button>
                <button type="submit" name="locale" value="en" class="{{ app()->getLocale() === 'en' ? 'active' : '' }}" aria-pressed="{{ app()->getLocale() === 'en' ? 'true' : 'false' }}">English</button>
            </form>
            <span class="date">{{ now()->format('d M Y · H:i') }} <span class="dot"></span> Yangon</span>
        </div></header>
        <div class="content">@if(session('success'))<div class="alert success" role="status">✓ {{ session('success') }}</div>@endif @if($errors->any())<div class="alert error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif @yield('content')<footer>{{ __('ဝန်ထမ်းသုံးစနစ် · ငွေကြေး MMK · အချိန် Asia/Yangon') }}</footer></div>
    </main>
</div>
<nav class="mobile-bottom-nav" aria-label="{{ __('အဓိကမီနူး') }}">
    @foreach($primaryRoutes as $route)
        @php($item = $navigation[$route])
        <a href="{{ route($route) }}" class="mobile-nav-item {{ $item['active'] ? 'active' : '' }}" @if($item['active']) aria-current="page" @endif>@include('icon', ['name' => $item['icon']])<span>{{ __($item['short']) }}</span></a>
    @endforeach
    <button class="mobile-nav-item {{ $moreActive ? 'active' : '' }}" type="button" data-mobile-menu-open aria-controls="mobile-menu" aria-haspopup="dialog" aria-expanded="false">@include('icon', ['name' => 'menu'])<span>{{ __('ပိုမို') }}</span></button>
</nav>
<dialog class="mobile-menu-dialog" id="mobile-menu" aria-labelledby="mobile-menu-title">
    <div class="mobile-menu-heading"><h2 id="mobile-menu-title">{{ __('ပိုမို') }}</h2><button type="button" class="mobile-menu-close" data-mobile-menu-close aria-label="{{ __('ပိတ်ရန်') }}" autofocus>@include('icon', ['name' => 'close'])</button></div>
    <div class="mobile-menu-profile"><span class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span><div><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->isManager() ? __('မန်နေဂျာ') : __('ဝန်ထမ်း') }}</small></div></div>
    <nav class="mobile-menu-links" aria-label="{{ __('အခြားမီနူးများ') }}">
        @foreach($navigation as $route => $item)
            @continue(in_array($route, $primaryRoutes, true))
            <a href="{{ route($route) }}" class="{{ $item['active'] ? 'active' : '' }}" @if($item['active']) aria-current="page" @endif>@include('icon', ['name' => $item['icon']])<span>{{ __($item['label']) }}</span>@if($item['active'])<span class="mobile-current-marker" aria-hidden="true">@include('icon', ['name' => 'check'])</span>@endif</a>
        @endforeach
        <a href="{{ route('password.edit') }}" class="{{ request()->routeIs('password.*') ? 'active' : '' }}" @if(request()->routeIs('password.*')) aria-current="page" @endif>@include('icon', ['name' => 'key'])<span>{{ __('စကားဝှက်ပြောင်းရန်') }}</span></a>
    </nav>
    @include('pwa-install-button')
    <form method="post" action="{{ route('logout') }}" class="mobile-menu-logout">@csrf<button type="submit">@include('icon', ['name' => 'logout']){{ __('စနစ်မှထွက်ရန်') }}</button></form>
</dialog>
@include('pwa-controls')
<script src="{{ asset('app.js') }}?v={{ filemtime(public_path('app.js')) }}" defer></script></body></html>
