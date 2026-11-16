@extends('layouts.app')

@section('title', 'تعديل مسابقة: ' . $quranCompetition->title)

@section('content')
<div class="container-fluid py-4">
    {{-- Page Header --}}
    <x-dashboard.page-header
        title="تعديل مسابقة القرآن الكريم"
        :description="$quranCompetition->title . ' — سنة ' . $quranCompetition->hijri_year . ' هـ'">
        <x-slot name="actions">
            <a href="{{ route('dashboard.quran-competitions.show', $quranCompetition) }}" class="btn btn-outline-info d-inline-flex align-items-center gap-2 px-3 py-2" style="border-radius: 12px; font-weight: 600;">
                <i class="fe fe-eye"></i>
                <span>تفاصيل المسابقة</span>
            </a>
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
        'action'      => route('dashboard.quran-competitions.update', $quranCompetition),
        'method'      => 'PUT',
        'competition' => $quranCompetition,
        'categories'  => $categories,
        'submitLabel' => 'تحديث بيانات المسابقة',
    ])
</div>

{{-- Quick Category Creation Modal --}}
@include('dashboard.quran-competitions.modals.quick-category')
@endsection
