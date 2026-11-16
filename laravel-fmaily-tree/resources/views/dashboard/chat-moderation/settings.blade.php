@extends('layouts.app')

@section('title', 'مراقبة المحادثات — الإعدادات')

@section('content')
<div class="container-fluid">
    @include('dashboard.chat-moderation.partials._header', ['chat-title' => 'مراقبة المحادثات — الإعدادات', 'chat-subtitle' => 'إعدادات نظام المحادثات العامة.'])

    @livewire('admin.chat.chat-settings')
</div>
@endsection