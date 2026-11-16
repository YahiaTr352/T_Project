@extends('layouts.app')

@section('title', 'مراقبة المحادثات — المحظورون')

@section('content')
<div class="container-fluid">
    @include('dashboard.chat-moderation.partials._header', ['chat-title' => 'مراقبة المحادثات — المحظورون', 'chat-subtitle' => 'إدارة حظر المشاركين من المحادثات.'])

    @livewire('admin.chat.chat-bans')
</div>
@endsection