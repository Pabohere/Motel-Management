@extends('layouts.app')
@section('title', __('Settings'))
@section('content')
<h1>{{ __('Settings') }}</h1><div class="two-columns"><a class="panel" href="{{ route('password.edit') }}"><h2>{{ __('စကားဝှက်ပြောင်းရန်') }}</h2></a>@if(auth()->user()->isManager())<a class="panel" href="{{ route('users.index') }}"><h2>{{ __('ဝန်ထမ်းများ') }}</h2></a><a class="panel" href="{{ route('rooms.index', ['manage' => 1]) }}"><h2>{{ __('Room administration') }}</h2></a><a class="panel" href="{{ route('audit.index') }}"><h2>{{ __('ပြောင်းလဲမှုမှတ်တမ်း') }}</h2></a>@endif</div>
@endsection
