@extends('errors.layout')

@section('title', 'Not allowed')
@section('code', '403')
@section('heading', 'You don’t have access to this page')
@section('message', 'Your login doesn’t include this screen. If you need it for your work, ask the owner or admin to change your role.')
