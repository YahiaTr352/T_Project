@extends('layouts.app')

@section('title', 'تعديل المناسبة: ' . $event->title)

@section('content')
<div class="container-fluid">
    {{-- Page Header --}}
    <x-dashboard.page-header
        :title="'تعديل المناسبة: ' . $event->title"
        description="قم بتحديث بيانات المناسبة، الموعد، المكان، أو خيارات العرض والعداد التنازلي.">
        <x-slot name="actions">
            <a href="{{ route('dashboard.events.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 px-3 py-2" style="border-radius: 12px; font-weight: 600;">
                <i class="fe fe-arrow-right"></i>
                <span>العودة للقائمة</span>
            </a>
        </x-slot>
    </x-dashboard.page-header>

    @include('components.alerts')

    {{-- Event Form --}}
    @include('dashboard.events.partials._form', [
        'action'      => route('dashboard.events.update', $event),
        'submitLabel' => 'حفظ التعديلات',
        'submitIcon'  => 'fe fe-check',
        'event'       => $event,
    ])
</div>
@endsection
