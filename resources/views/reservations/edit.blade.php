@extends('layouts.app')
@section('title', __('Change reservation dates'))
@section('content')
<div class="page-heading"><div><h1>{{ __('Change reservation dates') }}</h1><p>{{ $reservation->guest_name }} · {{ __('Room') }} {{ $reservation->room->number }}</p></div><a href="{{ route('reservations.index') }}">{{ __('Back to reservations') }}</a></div>
<form class="panel" method="post" action="{{ route('reservations.update', $reservation) }}">
    @csrf @method('PUT')
    <input type="hidden" id="room-select" value="{{ $reservation->room_id }}">
    <div class="form-grid">
        <label>{{ __('Arrival') }}<input type="datetime-local" id="check-in" name="arrival_at" value="{{ old('arrival_at', $reservation->arrival_at->format('Y-m-d\TH:i')) }}" required>@if($errors->has('arrival_at') || $errors->has('arrival_at.*'))<small class="field-error" role="alert">{{ $errors->first('arrival_at') ?: $errors->first('arrival_at.*') }}</small>@endif</label>
        <label>{{ __('Departure') }}<input type="datetime-local" id="check-out" name="departure_at" value="{{ old('departure_at', $reservation->departure_at->format('Y-m-d\TH:i')) }}" required>@if($errors->has('departure_at') || $errors->has('departure_at.*'))<small class="field-error" role="alert">{{ $errors->first('departure_at') ?: $errors->first('departure_at.*') }}</small>@endif</label>
        <label>{{ __('Room charge (MMK)') }}<input type="number" id="room-charge" data-manual="true" name="room_charge" value="{{ old('room_charge', $reservation->room_charge) }}" min="0" max="100000000" required>@if($errors->has('room_charge') || $errors->has('room_charge.*'))<small class="field-error" role="alert">{{ $errors->first('room_charge') ?: $errors->first('room_charge.*') }}</small>@endif</label>
    </div>
    @include('stays.companions', ['companionRows' => $reservation->companions ?? []])
    @include('stays.extra-bed-fields')
    @include('stays.price-summary', ['showPayment' => false])
    <p class="muted">{{ __('The current room charge is preserved. Review it when changing dates. Conflicting dates cannot be saved.') }}</p>
    <button class="btn">{{ __('Save reservation') }}</button>
</form>
@endsection
