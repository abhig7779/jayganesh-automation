@extends('errors.layout')

@section('title', 'Updating')
@section('code', '503')
@section('heading', 'The CRM is being updated')
@section('message', 'This usually takes less than a minute. The page will reload by itself.')
@section('action')
    <a href="{{ url()->current() }}" class="btn-accent w-full">Try again now</a>
@endsection
