@extends('layouts.app')

@section('title', 'توزيع نص قالب الصورة')

@push('styles')
    @include('dashboard.notifications.partials.styles')
@endpush

@section('content')
    <div class="container py-4" dir="rtl">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 notif-title mb-0">{{ $template->name }}</h1>
            <a class="btn btn-outline-secondary" href="{{ route('dashboard.creatives.index', ['status' => $template->status]) }}">رجوع للتصاميم</a>
        </div>
        @if (session('layout_saved'))
            <div class="alert alert-success" role="status">{{ session('layout_saved') }}</div>
        @endif
        @error('text_layout')
            <div class="alert alert-danger" role="alert">{{ $message }}</div>
        @enderror
        <form class="dashboard-notif-card p-3 p-md-4" method="POST" action="{{ route('dashboard.creatives.layout.save', $template) }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="invitation_type" value="{{ $template->invitation_type }}" data-visual-invitation-type>
            <x-dashboard.visual.text-layout-editor
                :fields-by-type="[$template->invitation_type => $fields]"
                :image-url="$template->image_url"
                :layout="$layout"
            />
            <button type="submit" class="btn btn-success">حفظ توزيع النص</button>
            <button type="button" class="btn btn-outline-secondary" data-layout-proof="{{ route('dashboard.creatives.layout.preview', $template) }}">معاينة الصورة الناتجة</button>
            <div class="mt-3" data-layout-proof-result hidden>
                <p data-layout-proof-message role="status" class="mb-1"></p>
                <img data-layout-proof-image class="img-fluid" style="max-height:440px;" alt="صورة القالب مع أسماء المتغيرات" hidden>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/dashboard/creative-text-layout.js') }}?v={{ filemtime(public_path('js/dashboard/creative-text-layout.js')) }}" defer></script>
@endpush
