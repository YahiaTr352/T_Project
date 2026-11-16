@extends('layouts.app')

@section('title', 'إضافة مناسبة جديدة')

@section('content')
<div class="container-fluid">
    {{-- Page Header --}}
    <x-dashboard.page-header
        title="إضافة مناسبة عائلية جديدة"
        description="أدخل تفاصيل وموعد المناسبة الجديدة ليتم جدولتها وعرضها في الصفحة الرئيسية للبوابة.">
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
        'action'      => route('dashboard.events.store'),
        'submitLabel' => 'حفظ ونشر المناسبة',
        'submitIcon'  => 'fe fe-save',
    ])
</div>
@endsection
