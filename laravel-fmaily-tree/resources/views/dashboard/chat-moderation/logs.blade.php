@extends('layouts.app')

@section('title', 'مراقبة المح chatter — سجل التدقيق')

@section('content')
<div class="container-fluid">
    @include('dashboard.chat-moderation.partials._header', ['chat-title' => 'مراقبة المحادثات — سجل التدقيق', 'chat-subtitle' => 'سجل كامل لكل إجراءات الإشراف.'])

    @livewire('admin.chat.chat-audit-log')
</div>
@endsection