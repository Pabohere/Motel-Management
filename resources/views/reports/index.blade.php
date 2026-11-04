@extends('layouts.app')
@section('title', __('Reports'))
@section('content')
<h1>{{ __('Reports') }}</h1><div class="two-columns"><a class="panel" href="{{ route('reports.daily') }}"><h2>{{ __('နေ့စဉ်စာရင်း') }}</h2><p>{{ __('Review daily receipts and expenses.') }}</p></a>@if(auth()->user()->isManager())<a class="panel" href="{{ route('reports.monthly') }}"><h2>{{ __('လစဉ်စာရင်း') }}</h2><p>{{ __('Monthly results, financial statements and exports.') }}</p></a>@endif</div>
@endsection
