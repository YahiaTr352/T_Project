@extends('layouts.app')

@section('title', 'إضافة مسابقة قرآن كريم جديدة')

@section('content')
<div class="container-fluid py-4">
    {{-- Page Header --}}
    <x-dashboard.page-header
        title="إضافة مسابقة قرآن كريم جديدة"
        description="أنشئ مسابقة جديدة لحفظ القرآن الكريم وتلاوته، وحدد السنة الهجرية والتصنيف والشروط.">
        <x-slot name="actions">
            <a href="{{ route('dashboard.quran-competitions.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 px-3 py-2" style="border-radius: 12px; font-weight: 600;">
                <i class="fe fe-arrow-right"></i>
                <span>العودة للقائمة</span>
            </a>
        </x-slot>
    </x-dashboard.page-header>

    {{-- System Alerts --}}
    @include('components.alerts')

    {{-- Shared Form Partial --}}
    @include('dashboard.quran-competitions.partials._form', [
        'action'      => route('dashboard.quran-competitions.store'),
        'method'      => 'POST',
        'competition' => null,
        'categories'  => $categories,
        'submitLabel' => 'حفظ ونشر المسابقة',
    ])
</div>

{{-- Quick Category Creation Modal --}}
@include('dashboard.quran-competitions.modals.quick-category')
@endsection
