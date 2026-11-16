@extends('layouts.app')

@section('title', 'مراقبة المحادثات — الكلمات الممنوعة')

@section('content')
<div class="container-fluid">
    @include('dashboard.chat-moderation.partials._header', ['chat-title' => 'مراقبة المحادثات — الكلمات الممنوعة', 'chat-subtitle' => 'الكلمات التي يتم حجبها أو إخفاؤها أو التنبيه عليها.'])

    @livewire('admin.chat.chat-blocked-words')
</div>
@endsection