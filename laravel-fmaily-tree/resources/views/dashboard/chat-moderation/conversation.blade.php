@extends('layouts.app')

@section('title', 'مراقبة المحادثات — تفاصيل المحادثة')

@section('content')
<div class="container-fluid">
    @include('dashboard.chat-moderation.partials._header', [
        'chat-title' => 'مراقبة المحادثات — تفاصيل المحادثة',
        'chat-subtitle' => 'سجل الرسائل والمشاركون وأدوات الإشراف الفورية.',
    ])

    @livewire('admin.chat.chat-conversation-detail', ['conversation' => $conversation])
</div>
@endsection
