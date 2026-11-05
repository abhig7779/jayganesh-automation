@extends('errors.layout')

@section('title', 'Session expired')
@section('code', '419')
@section('heading', 'Session expired, please sign in again')
@section('message', 'The page was open for too long, so for safety it stopped working. Sign in again and repeat the last step. Nothing was saved from that step.')
@section('action')
    <a href="{{ url('/login') }}" class="btn-accent w-full">Sign in again</a>
@endsection
