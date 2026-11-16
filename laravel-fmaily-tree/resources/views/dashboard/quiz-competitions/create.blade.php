@extends('layouts.app')

@section('title', 'إنشاء استطلاع رأي جديد')

@section('content')
<div class="container-fluid py-4">
    {{-- Page Header --}}
    <x-dashboard.page-header
        title="إنشاء استطلاع رأي جديد"
        description="قم بإعداد استطلاع رأي جديد أو مسابقة عائلية، وحدد التوقيت والرعاة وشروط المشاركة.">
        <x-slot name="actions">
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
        'action'      => route('dashboard.quiz-competitions.store'),
        'method'      => 'POST',
        'competition' => null,
        'sponsors'    => $sponsors,
        'submitLabel' => 'حفظ ونشر الاستطلاع',
    ])
</div>
@endsection