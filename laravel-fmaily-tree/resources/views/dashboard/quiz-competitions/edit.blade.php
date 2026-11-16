@extends('layouts.app')

@section('title', 'تعديل استطلاع الرأي: ' . $quizCompetition->title)

@section('content')
<div class="container-fluid py-4">
    {{-- Page Header --}}
    <x-dashboard.page-header
        title="تعديل استطلاع الرأي"
        :description="$quizCompetition->title">
        <x-slot name="actions">
            <a href="{{ route('dashboard.quiz-competitions.show', $quizCompetition) }}" class="btn btn-outline-info d-inline-flex align-items-center gap-2 px-3 py-2" style="border-radius: 12px; font-weight: 600;">
                <i class="fe fe-eye"></i>
                <span>تفاصيل الاستطلاع</span>
            </a>
            <a href="{{ route('dashboard.quiz-competitions.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 px-3 py-2" style="border-radius: 12px; font-weight: 600;">
                <i class="fe fe-arrow-right"></i>
                <span>العودة للقائمة</span>
            </a>
        </x-slot>
    </x-dashboard.page-header>

    {{-- System Alerts --}}
    @include('components.alerts')

    {{-- Shared Form Partial --}}
    @include('dashboard.quiz-competitions.partials._form', [
        'action'      => route('dashboard.quiz-competitions.update', $quizCompetition),
        'method'      => 'PUT',
        'competition' => $quizCompetition,
        'sponsors'    => $sponsors,
        'submitLabel' => 'تحديث إعدادات الاستطلاع',
    ])
</div>
@endsection