@extends('layouts.app')

@section('title', 'مراقبة المحادثات — المحادثات')

@section('content')
<div class="container-fluid">
    @include('dashboard.chat-moderation.partials._header', ['chat-title' => 'مراقبة المحادثات — المحادثات', 'chat-subtitle' => 'كل المحادثات المرتبطة بتحديات ومسابقات التطبيق.'])

    @livewire('admin.chat.chat-conversations')
</div>
@endsection