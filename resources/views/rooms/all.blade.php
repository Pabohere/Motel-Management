@extends('layouts.app')
@section('title', __('အခန်းအားလုံး'))
@section('content')
<div class="rooms-page">
<div class="page-heading"><div><span class="eyebrow">{{ __('အခန်းများ') }}</span><h1>{{ __('အခန်းအားလုံး') }}</h1><p class="muted">{{ __('အထပ်နှင့် အခြေအနေအလိုက် အခန်းများ') }} · {{ $rooms->count() }} {{ __('ခန်း') }}</p></div><a class="btn secondary" href="{{ route('rooms.index', request()->only('floor_id', 'status')) }}">{{ __('အခန်းများသို့ ပြန်ရန်') }}</a></div>
@include('rooms.filters', ['action' => route('rooms.all')])
@include('rooms.cards', ['roomDisplay' => 'grid'])
</div>
@endsection
