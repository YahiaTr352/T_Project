@extends('layouts.app')

@section('title', 'مراقبة المحادثات — نظرة عامة')

@section('content')
<div class="container-fluid">
    @include('dashboard.chat-moderation.partials._header', ['chat-title' => 'مراقبة المحادثات — نظرة عامة', 'chat-subtitle' => 'مؤشرات النشاط العام وإحصائيات المحادثات.'])

    @livewire('admin.chat.chat-overview')
</div>
@endsection