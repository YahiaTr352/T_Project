@extends('layouts.app')

@section('title', 'مراقبة المحادثات — البلاغات')

@section('content')
<div class="container-fluid">
    @include('dashboard.chat-moderation.partials._header', ['chat-title' => 'مراقبة المحادثات — البلاغات', 'chat-subtitle' => 'بلاغات المستخدمين على رسائل المحادثات.'])

    @livewire('admin.chat.chat-reports')
</div>
@endsection