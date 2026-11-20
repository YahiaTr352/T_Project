@extends('layouts.app')

@section('title', 'تفاصيل استطلاع الرأي: ' . $quizCompetition->title)

@push('styles')
@include('dashboard.quiz-competitions._styles')
<style>
    .question-row-text {
        font-weight: 700;
        font-size: 0.95rem;
        color: var(--dash-heading, #1e293b);
        line-height: 1.5;
        max-width: 420px;
    }
    body.dark .question-row-text {
        color: #f1f5f9;
    }
    .quiz-desc-box {
        line-height: 1.8;
        direction: rtl;
        text-align: right;
    }
</style>
@endpush

@section('content')
<div class="container-fluid py-4">
    {{-- Page Header --}}
    <x-dashboard.page-header
        :title="$quizCompetition->title"
        description="إدارة البنود والأسئلة، إجراء القرعة والسحب الآلي للفائزين، واستعراض نتائج المشاركين.">
        <x-slot name="actions">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <a href="{{ route('dashboard.quiz-competitions.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1 px-3 py-2" style="border-radius: 12px; font-weight: 600;">
                    <i class="fe fe-arrow-right"></i>
                    <span>العودة للقائمة</span>
                </a>
                <a href="{{ route('quiz-competitions.show', $quizCompetition) }}" target="_blank" rel="noopener" class="btn btn-outline-info d-inline-flex align-items-center gap-1 px-3 py-2" style="border-radius: 12px; font-weight: 600;">
                    <i class="fe fe-external-link"></i>
                    <span>معاينة في البوابة</span>
                </a>
                <a href="{{ route('dashboard.quiz-competitions.responses', $quizCompetition) }}" class="btn btn-outline-success d-inline-flex align-items-center gap-1 px-3 py-2" style="border-radius: 12px; font-weight: 600;">
                    <i class="fe fe-bar-chart-2"></i>
                    <span>لوحة النتائج</span>
                </a>
                <a href="{{ route('dashboard.quiz-competitions.export', $quizCompetition) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1 px-3 py-2" style="border-radius: 12px; font-weight: 600;">
                    <i class="fe fe-download"></i>
                    <span>تصدير Excel</span>
                </a>
                <form action="{{ route('dashboard.quiz-competitions.simulate-answers', $quizCompetition) }}" method="POST" class="d-inline" onsubmit="return confirm('سيقوم هذا الإجراء بتسجيل تصويتات وإجابات عشوائية لاختبار النظام والمحاكاة. هل ترغب بالاستمرار؟');">
                    @csrf
                    <button type="submit" class="btn btn-outline-warning d-inline-flex align-items-center gap-1 px-3 py-2" style="border-radius: 12px; font-weight: 600;" title="محاكاة التصويت">
                        <i class="fe fe-play"></i>
                        <span>محاكاة التصويت</span>
                    </button>
                </form>
                <a href="{{ route('dashboard.quiz-questions.create', $quizCompetition) }}" class="btn btn-primary d-inline-flex align-items-center gap-1 px-3 py-2 shadow-sm" style="border-radius: 12px; font-weight: 700;">
                    <i class="fe fe-plus"></i>
                    <span>إضافة بند / سؤال</span>
                </a>
                <a href="{{ route('dashboard.quiz-competitions.edit', $quizCompetition) }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1 px-3 py-2" style="border-radius: 12px; font-weight: 600;">
                    <i class="fe fe-settings"></i>
                    <span>تعديل الإعدادات</span>
                </a>
            </div>
        </x-slot>
    </x-dashboard.page-header>

    {{-- System Alerts --}}
    @include('components.alerts')

    {{-- Competition Info Glance Card --}}
    <x-dashboard.card title="بيانات الاستطلاع والجدولة" icon="fe-info" class="mb-4">
        <div class="row g-3">
            <div class="col-sm-6 col-md-3 mb-3">
                <span class="text-muted d-block small font-weight-bold">الحالة الحالية:</span>
                <div class="mt-1">
                    @if($quizCompetition->is_active)
                        <span class="badge badge-success px-2 py-1 font-weight-bold" style="border-radius: 6px;">
                            <i class="fe fe-check mr-1"></i>نشط ومتاح للتصويت
                        </span>
                    @else
                        <span class="badge badge-secondary px-2 py-1 font-weight-bold" style="border-radius: 6px;">
                            <i class="fe fe-pause mr-1"></i>معطل / مغلق
                        </span>
                    @endif
                    @if($quizCompetition->show_draw_only)
                        <span class="badge badge-warning px-2 py-1 font-weight-bold mr-1" style="border-radius: 6px;">
                            وضع القرعة فقط
                        </span>
                    @endif
                </div>
            </div>

            <div class="col-sm-6 col-md-3 mb-3">
                <span class="text-muted d-block small font-weight-bold">تاريخ البدء:</span>
                <span class="font-weight-bold d-inline-block mt-1">
                    {{ $quizCompetition->start_at ? $quizCompetition->start_at->format('Y-m-d H:i') : 'غير محدد' }}
                </span>
            </div>

            <div class="col-sm-6 col-md-3 mb-3">
                <span class="text-muted d-block small font-weight-bold">تاريخ الانتهاء:</span>
                <span class="font-weight-bold d-inline-block mt-1">
                    {{ $quizCompetition->end_at ? $quizCompetition->end_at->format('Y-m-d H:i') : 'غير محدد' }}
                </span>
            </div>

            <div class="col-sm-6 col-md-3 mb-3">
                <span class="text-muted d-block small font-weight-bold">مهلة الانتظار:</span>
                <span class="font-weight-bold d-inline-block mt-1">
                    {{ $quizCompetition->reveal_delay_seconds ?? 60 }} ثانية
                </span>
            </div>
        </div>

        @if($quizCompetition->description)
            <div class="border-top pt-3 mt-2">
                <span class="text-muted d-block small font-weight-bold mb-2">الوصف والتعليمات العامة:</span>
                <div class="quiz-desc-box text-dark">
                    {!! $quizCompetition->description !!}
                </div>
            </div>
        @endif
    </x-dashboard.card>

    {{-- Questions & Survey Items Card --}}
    <x-dashboard.card title="البنود والأسئلة المضافة" icon="fe-list">
        <x-slot name="headerAction">
            <span class="badge badge-light px-3 py-2 border font-weight-bold" style="border-radius: 10px; font-size: 0.82rem;">
                العدد: {{ $quizCompetition->questions->count() }} بند
            </span>
        </x-slot>

        @if($quizCompetition->questions->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>نص البند / السؤال</th>
                            <th>نوع الاستجابة</th>
                            <th>الردود والإحصائيات</th>
                            <th>الفائزون والقرعة</th>
                            <th style="width: 100px;" class="text-center">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($quizCompetition->questions as $index => $question)
                            <tr>
                                <td>
                                    <span class="text-muted font-weight-bold">{{ sprintf('%02d', $index + 1) }}</span>
                                </td>
                                <td>
                                    <div class="question-row-text">
                                        {!! Str::limit(strip_tags($question->question_text), 90) !!}
                                    </div>
                                </td>
                                <td>
                                    @if($question->answer_type === 'multiple_choice')
                                        <span class="badge-modern badge-modern-primary">
                                            <i class="fe fe-check-square"></i> اختيار من متعدد
                                        </span>
                                    @elseif($question->answer_type === 'vote')
                                        <span class="badge-modern badge-modern-success">
                                            <i class="fe fe-pie-chart"></i> تصويت / استطلاع رأي
                                        </span>
                                    @elseif($question->answer_type === 'true_false')
                                        <span class="badge-modern badge-modern-warning">
                                            <i class="fe fe-check-circle"></i> صح أم خطأ
                                        </span>
                                    @elseif($question->answer_type === 'ordering')
                                        <span class="badge-modern badge-modern-info">
                                            <i class="fe fe-shuffle"></i> ترتيب العناصر
                                        </span>
                                    @elseif($question->answer_type === 'survey')
                                        <span class="badge-modern badge-modern-teal">
                                            <i class="fe fe-sliders"></i> تقييم / استبيان
                                        </span>
                                    @elseif($question->answer_type === 'walking')
                                        <span class="badge-modern badge-modern-success">
                                            <i class="fe fe-activity"></i> تحدي المشي والخطوات
                                        </span>
                                    @else
                                        <span class="badge-modern badge-modern-secondary">
                                            <i class="fe fe-edit-3"></i> إجابة حرة
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if($question->answer_type === 'walking')
                                        <a href="{{ route('dashboard.quiz-competitions.responses', ['quizCompetition' => $quizCompetition, 'question_id' => $question->id]) }}" class="btn btn-sm btn-outline-success px-3 font-weight-bold mb-1" style="border-radius: 8px;" title="عرض ترتيب المتسابقين وإدارة النقاط">
                                            <i class="fe fe-activity mr-1"></i> جدول الترتيب والنقاط
                                        </a>
                                        <small class="d-block text-muted mb-2">
                                            {{ $question->walkingScores->count() }} سجلات نقاط
                                        </small>

                                        @php $raceCfg = $question->getWalkingRaceConfig(); @endphp
                                        <div class="d-flex flex-wrap align-items-center gap-1">
                                            <form action="{{ route('dashboard.quiz-questions.walking-race-settings', [$quizCompetition, $question]) }}" method="POST" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="quick_toggle" value="1">
                                                <input type="hidden" name="race_enabled" value="{{ $raceCfg['enabled'] ? '0' : '1' }}">
                                                <button type="submit"
                                                        class="btn btn-sm font-weight-bold {{ $raceCfg['enabled'] ? 'btn-primary' : 'btn-outline-secondary' }}"
                                                        style="border-radius: 8px; font-size: 0.78rem;"
                                                        title="{{ $raceCfg['enabled'] ? 'إخفاء زر سباق الترتيب اليومي عن الزوار' : 'إظهار زر سباق الترتيب اليومي في الصفحة الرئيسية وتفاصيل المسابقة' }}">
                                                    <i class="fe {{ $raceCfg['enabled'] ? 'fe-eye' : 'fe-eye-off' }} mr-1"></i>
                                                    {{ $raceCfg['enabled'] ? 'السباق: ظاهر (إخفاء)' : 'السباق: مخفي (تفعيل)' }}
                                                </button>
                                            </form>

                                            <button type="button"
                                                    class="btn btn-sm btn-outline-primary font-weight-bold"
                                                    style="border-radius: 8px; font-size: 0.78rem;"
                                                    data-toggle="modal"
                                                    data-target="#walkingRaceSettingsModal{{ $question->id }}"
                                                    title="تخصيص إعدادات سباق الترتيب اليومي">
                                                <i class="fe fe-sliders mr-1"></i> إعدادات السباق
                                            </button>
                                        </div>
                                    @elseif($question->answers_count > 0)
                                        <a href="{{ route('dashboard.quiz-competitions.responses', ['quizCompetition' => $quizCompetition, 'question_id' => $question->id]) }}" class="btn btn-sm btn-outline-info px-3 font-weight-bold" style="border-radius: 8px;" title="عرض إحصائيات الإجابات">
                                            <i class="fe fe-bar-chart mr-1"></i>
                                            {{ $question->answers_count }} {{ $question->answer_type === 'vote' ? 'صوت' : 'مشاركة' }}
                                        </a>
                                        @if(!in_array($question->answer_type, ['vote', 'survey'], true))
                                            <small class="d-block text-success font-weight-bold mt-1">
                                                <i class="fe fe-check mr-1"></i>{{ $question->correct_answers_count }} إجابة صحيحة
                                            </small>
                                        @endif
                                    @else
                                        <span class="text-muted d-block small mb-1">لا توجد ردود بعد</span>
                                        <a href="{{ route('dashboard.quiz-competitions.responses', ['quizCompetition' => $quizCompetition, 'question_id' => $question->id]) }}" class="text-info font-weight-bold small text-decoration-none">
                                            فتح الإحصائيات <i class="fe fe-chevron-left small"></i>
                                        </a>
                                    @endif
                                </td>
                                <td>
                                    @if($question->answer_type === 'walking')
                                        @if($question->winners->count() > 0)
                                            <button type="button" class="btn btn-sm btn-success px-3 font-weight-bold" style="border-radius: 8px;" data-toggle="modal" data-target="#winnersModal{{ $question->id }}">
                                                <i class="fe fe-award mr-1"></i>
                                                الفائزون ({{ $question->winners->count() }})
                                            </button>
                                            <form action="{{ route('dashboard.quiz-questions.select-winners', [$quizCompetition, $question]) }}" method="POST" class="d-inline ml-1" onsubmit="return confirm('إعادة احتساب وتحديث قائمة الفائزين بناءً على أعلى النقاط/الخطوات؟');">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-warning p-1" style="border-radius: 6px;" title="إعادة احتساب الفائزين">
                                                    <i class="fe fe-refresh-cw"></i>
                                                </button>
                                            </form>
                                        @else
                                            <form action="{{ route('dashboard.quiz-questions.select-winners', [$quizCompetition, $question]) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-warning font-weight-bold px-3" style="border-radius: 8px;">
                                                    <i class="fe fe-award mr-1"></i> احتساب وتتويج الفائزين
                                                </button>
                                            </form>
                                        @endif
                                    @elseif(in_array($question->answer_type, ['vote', 'survey'], true))
                                        <span class="text-muted small">— استطلاع رأي إحصائي</span>
                                    @elseif($question->winners->count() > 0)
                                        <button type="button" class="btn btn-sm btn-success px-3 font-weight-bold" style="border-radius: 8px;" data-toggle="modal" data-target="#winnersModal{{ $question->id }}" title="عرض تفاصيل الفائزين">
                                            <i class="fe fe-award mr-1"></i>
                                            الفائزون ({{ $question->winners->count() }})
                                        </button>
                                        @if($question->hasEnded() && $question->correct_answers_count > 0)
                                            <form action="{{ route('dashboard.quiz-questions.select-winners', [$quizCompetition, $question]) }}" method="POST" class="d-inline ml-1" onsubmit="return confirm('سيتم تصفير قائمة الفائزين الحالية وإجراء سحب عشوائي جديد. هل أنت متأكد؟');">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-warning p-1" style="border-radius: 6px;" title="إعادة إجراء السحب">
                                                    <i class="fe fe-refresh-cw"></i>
                                                </button>
                                            </form>
                                        @endif
                                    @else
                                        @if($question->hasEnded())
                                            @if($question->correct_answers_count > 0)
                                                <form action="{{ route('dashboard.quiz-questions.select-winners', [$quizCompetition, $question]) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-warning font-weight-bold px-3" style="border-radius: 8px;" title="إجراء قرعة وسحب الفائزين عشوائياً">
                                                        <i class="fe fe-award mr-1"></i> إجراء السحب
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-muted small text-danger font-weight-bold">لا توجد إجابات صحيحة للسحب</span>
                                            @endif
                                        @else
                                            <span class="text-muted small">بانتظار إغلاق الاستطلاع</span>
                                        @endif
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex align-items-center justify-content-center gap-1">
                                        <a href="{{ route('dashboard.quiz-questions.edit', [$quizCompetition, $question]) }}" class="btn btn-sm btn-outline-primary p-2" title="تعديل السؤال والخيارات" style="border-radius: 8px;">
                                            <i class="fe fe-edit-2"></i>
                                        </a>
                                        <form action="{{ route('dashboard.quiz-questions.destroy', [$quizCompetition, $question]) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا السؤال وكل خياراته ونتائجه نهائياً؟');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger p-2" title="حذف السؤال" style="border-radius: 8px;">
                                                <i class="fe fe-trash-2"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Winners Modals --}}
            @foreach($quizCompetition->questions as $question)
                @if(!in_array($question->answer_type, ['vote', 'survey'], true) && $question->winners->count() > 0)
                    <div class="modal fade" id="winnersModal{{ $question->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
                            <div class="modal-content shadow-lg border-0" style="border-radius: 20px;">
                                <div class="modal-header border-bottom p-4">
                                    <h5 class="modal-title font-weight-bold d-flex align-items-center gap-2 mb-0">
                                        <i class="fe fe-award text-warning"></i>
                                        <span>لوحة الفائزين ونتائج القرعة</span>
                                    </h5>
                                    <button type="button" class="close text-muted" data-dismiss="modal"><span>&times;</span></button>
                                </div>
                                <div class="modal-body p-4">
                                    <div class="p-3 mb-4 rounded-lg bg-light border-right" style="border-right: 4px solid #0d9488 !important; border-radius: 12px;">
                                        <span class="text-xs text-muted font-weight-bold d-block mb-1">نص البند المسحوب عليه:</span>
                                        <div class="text-dark font-weight-bold" style="font-size: 1rem;">
                                            {!! strip_tags($question->question_text) !!}
                                        </div>
                                    </div>

                                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
                                        <div>
                                            <span class="badge badge-success px-3 py-2 font-weight-bold" style="border-radius: 8px; font-size: 0.88rem;">
                                                إجمالي الفائزين حالياً: {{ $question->winners->count() }} من أصل {{ $question->winners_count }} مقاعد
                                            </span>
                                        </div>
                                        <div class="d-flex gap-2">
                                            @if($question->winners->count() < $question->winners_count && $question->hasEnded() && $question->correct_answers_count > $question->winners->count())
                                                <form action="{{ route('dashboard.quiz-questions.fill-winners', [$quizCompetition, $question]) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-info font-weight-bold px-3 py-2" style="border-radius: 8px;">
                                                        <i class="fe fe-plus-circle mr-1"></i> إكمال المقاعد (شاغر: {{ $question->winners_count - $question->winners->count() }})
                                                    </button>
                                                </form>
                                            @endif
                                            
                                            <form action="{{ route('dashboard.quiz-questions.select-winners', [$quizCompetition, $question]) }}" method="POST" class="d-inline ml-2" onsubmit="return confirm('سيتم مسح الفائزين الحاليين وإجراء سحب جديد بالكامل. هل أنت متأكد؟');">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger font-weight-bold px-3 py-2" style="border-radius: 8px;">
                                                    <i class="fe fe-refresh-cw mr-1"></i> إعادة سحب الكل
                                                </button>
                                            </form>
                                        </div>
                                    </div>

                                    <div class="alert alert-info border-0 py-2 px-3 small d-flex align-items-center mb-3" style="border-radius: 10px;">
                                        <i class="fe fe-info mr-2" style="font-size: 1.1rem;"></i> 
                                        <span>يمكنك سحب وإفلات الصفوف لتعديل ترتيب مراكز الفائزين يدوياً بشكل فوري ثم الضغط على حفظ الترتيب.</span>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th style="width: 40px;"></th>
                                                    <th style="width: 140px;">المركز / الرتبة</th>
                                                    <th>اسم الفائز</th>
                                                    <th>رقم الجوال</th>
                                                    <th>اسم الأم (للتأكيد)</th>
                                                    <th style="width: 80px;" class="text-center">إزالة</th>
                                                </tr>
                                            </thead>
                                            <tbody class="sortable-winners" data-question-id="{{ $question->id }}">
                                                @foreach($question->winners->sortBy('position') as $winner)
                                                    <tr class="sortable-row" data-winner-id="{{ $winner->id }}">
                                                        <td class="drag-handle text-center">
                                                            <i class="fe fe-menu"></i>
                                                        </td>
                                                        <td>
                                                            @if($winner->position == 1)
                                                                <span class="winner-rank winner-rank-1">
                                                                    <i class="fe fe-award"></i> المركز الأول
                                                                </span>
                                                            @elseif($winner->position == 2)
                                                                <span class="winner-rank winner-rank-2">
                                                                    <i class="fe fe-award"></i> المركز الثاني
                                                                </span>
                                                            @elseif($winner->position == 3)
                                                                <span class="winner-rank winner-rank-3">
                                                                    <i class="fe fe-award"></i> المركز الثالث
                                                                </span>
                                                            @else
                                                                <span class="winner-rank winner-rank-other">
                                                                    المركز {{ $winner->position }}
                                                                </span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            <span class="font-weight-bold text-dark">{{ $winner->user->name ?? 'مستخدم غير معروف' }}</span>
                                                        </td>
                                                        <td dir="ltr" class="text-muted">{{ $winner->user->phone ?? '—' }}</td>
                                                        <td>
                                                            @if($winner->user && $winner->user->is_from_ancestry && $winner->user->mother_name)
                                                                <span class="badge badge-light border px-2 py-1">{{ $winner->user->mother_name }}</span>
                                                            @else
                                                                <span class="text-muted small">—</span>
                                                            @endif
                                                        </td>
                                                        <td class="text-center">
                                                            <form action="{{ route('dashboard.quiz-questions.remove-winner', $winner) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من استبعاد هذا الفائز؟');">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-sm btn-outline-danger p-1" style="border-radius: 6px;" title="إزالة من الفائزين">
                                                                    <i class="fe fe-user-minus"></i>
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="text-left mt-3 save-order-container" id="saveOrder{{ $question->id }}" style="display: none;">
                                        <form action="{{ route('dashboard.quiz-questions.reorder-winners', [$quizCompetition, $question]) }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="winner_ids[]" class="winner-ids-input">
                                            <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2 px-4 py-2" style="border-radius: 10px; font-weight: 700;">
                                                <i class="fe fe-save"></i>
                                                <span>حفظ الترتيب والمراكز الجديدة</span>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach

            {{-- Walking Daily Bar Chart Race Settings Modals --}}
            @foreach($quizCompetition->questions as $question)
                @if($question->answer_type === 'walking')
                    @php
                        $rCfg = $question->getWalkingRaceConfig();
                        $rawWs = $question->getWalkingSettings();
                    @endphp
                    <div class="modal fade" id="walkingRaceSettingsModal{{ $question->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                            <div class="modal-content shadow-lg border-0" style="border-radius: 20px;">
                                <div class="modal-header border-bottom p-4" style="background: linear-gradient(135deg, #0f2942, #1e3a8a); color: #ffffff; border-radius: 20px 20px 0 0;">
                                    <h5 class="modal-title font-weight-bold text-white d-flex align-items-center gap-2 mb-0" style="color: #ffffff !important;">
                                        <i class="fe fe-bar-chart-2 text-warning" style="color: #fbbf24 !important;"></i>
                                        <span class="text-white" style="color: #ffffff !important;">التحكم في سباق الترتيب اليومي المتحرك</span>
                                    </h5>
                                    <button type="button" class="close text-white" style="color: #ffffff !important;" data-dismiss="modal"><span>&times;</span></button>
                                </div>
                                <form action="{{ route('dashboard.quiz-questions.walking-race-settings', [$quizCompetition, $question]) }}" method="POST">
                                    @csrf
                                    <div class="modal-body p-4">
                                        <div class="alert alert-light border d-flex align-items-center justify-content-between mb-4" style="border-radius: 14px;">
                                            <div>
                                                <strong class="d-block text-dark">إظهار زر ونافذة السباق للجمهور (الويب والموبايل)</strong>
                                                <small class="text-muted">عند التفعيل سيظهر الزر في كارت المسابقة بالصفحة الرئيسية وصفحة تفاصيل المسابقة.</small>
                                            </div>
                                            <div class="custom-control custom-switch">
                                                <input type="hidden" name="race_enabled" value="0">
                                                <input type="checkbox" class="custom-control-input" id="modalRaceEnabled{{ $question->id }}" name="race_enabled" value="1" {{ $rCfg['enabled'] ? 'checked' : '' }}>
                                                <label class="custom-control-label font-weight-bold" for="modalRaceEnabled{{ $question->id }}">مفعّل</label>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-5 mb-3">
                                                <label class="font-weight-bold text-dark">طريقة ترتيب السباق</label>
                                                <select name="race_calc_mode" class="form-control font-weight-bold" style="border-radius: 10px;">
                                                    <option value="cumulative" {{ $rCfg['calc_mode'] === 'cumulative' ? 'selected' : '' }}>
                                                        تراكمي يوم بيوم (يجمع كل يوم على السابق)
                                                    </option>
                                                    <option value="daily" {{ $rCfg['calc_mode'] === 'daily' ? 'selected' : '' }}>
                                                        نقاط كل يوم على حدة
                                                    </option>
                                                </select>
                                            </div>

                                            <div class="col-md-3 mb-3">
                                                <label class="font-weight-bold text-dark">سرعة عرض كل يوم</label>
                                                <select name="race_speed_ms" class="form-control font-weight-bold" style="border-radius: 10px;">
                                                    <option value="4000" {{ $rCfg['speed_ms'] >= 3600 ? 'selected' : '' }}>بطيء (4.0 ث)</option>
                                                    <option value="2800" {{ $rCfg['speed_ms'] > 2100 && $rCfg['speed_ms'] < 3600 ? 'selected' : '' }}>متوسط (2.8 ث)</option>
                                                    <option value="1800" {{ $rCfg['speed_ms'] <= 2100 ? 'selected' : '' }}>سريع (1.8 ث)</option>
                                                </select>
                                            </div>

                                            <div class="col-md-4 mb-3">
                                                <label class="font-weight-bold text-dark" for="modalRaceMaxRanks{{ $question->id }}">عدد المراكز المعروضة</label>
                                                <div class="input-group">
                                                    <input type="number"
                                                           id="modalRaceMaxRanks{{ $question->id }}"
                                                           name="race_max_ranks"
                                                           class="form-control font-weight-bold"
                                                           value="{{ $rCfg['max_ranks'] }}"
                                                           min="0"
                                                           max="500"
                                                           required
                                                           oninput="document.querySelectorAll('[data-modal-preset=\'{{ $question->id }}\']').forEach(b => { const m = Number(this.value) === Number(b.dataset.val); b.classList.toggle('btn-primary', m); b.classList.toggle('btn-outline-primary', !m); })">
                                                    <div class="input-group-append">
                                                        <span class="input-group-text font-weight-bold bg-light text-muted" style="font-size: 0.75rem;">0 = الكل</span>
                                                    </div>
                                                </div>
                                                <div class="d-flex flex-wrap mt-2" style="gap: 5px;">
                                                    <button type="button" data-modal-preset="{{ $question->id }}" data-val="10"
                                                            class="btn btn-sm font-weight-bold {{ $rCfg['max_ranks'] === 10 ? 'btn-primary' : 'btn-outline-primary' }}"
                                                            style="border-radius: 8px; padding: 2px 9px;"
                                                            onclick="document.getElementById('modalRaceMaxRanks{{ $question->id }}').value = 10; document.getElementById('modalRaceMaxRanks{{ $question->id }}').dispatchEvent(new Event('input'));">10</button>
                                                    <button type="button" data-modal-preset="{{ $question->id }}" data-val="20"
                                                            class="btn btn-sm font-weight-bold {{ $rCfg['max_ranks'] === 20 ? 'btn-primary' : 'btn-outline-primary' }}"
                                                            style="border-radius: 8px; padding: 2px 9px;"
                                                            onclick="document.getElementById('modalRaceMaxRanks{{ $question->id }}').value = 20; document.getElementById('modalRaceMaxRanks{{ $question->id }}').dispatchEvent(new Event('input'));">20</button>
                                                    <button type="button" data-modal-preset="{{ $question->id }}" data-val="0"
                                                            class="btn btn-sm font-weight-bold {{ $rCfg['max_ranks'] === 0 ? 'btn-primary' : 'btn-outline-primary' }}"
                                                            style="border-radius: 8px; padding: 2px 9px;"
                                                            onclick="document.getElementById('modalRaceMaxRanks{{ $question->id }}').value = 0; document.getElementById('modalRaceMaxRanks{{ $question->id }}').dispatchEvent(new Event('input'));">الكل</button>
                                                </div>
                                            </div>

                                            <div class="col-md-6 mb-3">
                                                <label class="font-weight-bold text-dark">العنوان الرئيسي أعلى النافذة (اختياري)</label>
                                                <input type="text" name="race_title" class="form-control" style="border-radius: 10px;" value="{{ $rawWs['race_title'] ?? '' }}" placeholder="تلقائي: {{ Str::limit(strip_tags($quizCompetition->title), 35) }}">
                                            </div>

                                            <div class="col-md-6 mb-3">
                                                <label class="font-weight-bold text-dark">العنوان الفرعي أسفل العنوان (اختياري)</label>
                                                <input type="text" name="race_subtitle" class="form-control" style="border-radius: 10px;" value="{{ $rawWs['race_subtitle'] ?? '' }}" placeholder="ماهي مجرد مسابقة... بل بداية عادة">
                                            </div>

                                            <div class="col-md-12 mb-2">
                                                <label class="font-weight-bold text-dark">نص زر فتح السباق في المسابقة (اختياري)</label>
                                                <input type="text" name="race_button_text" class="form-control" style="border-radius: 10px;" value="{{ $rawWs['race_button_text'] ?? '' }}" placeholder="سباق الترتيب اليومي">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer border-top p-3 bg-light" style="border-radius: 0 0 20px 20px;">
                                        <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal" style="border-radius: 10px;">إلغاء</button>
                                        <button type="submit" class="btn btn-primary font-weight-bold px-4" style="border-radius: 10px;">
                                            <i class="fe fe-save mr-1"></i> حفظ إعدادات السباق
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        @else
            <div class="text-center py-5">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light text-muted mb-3" style="width: 80px; height: 80px;">
                    <i class="fe fe-help-circle" style="font-size: 2.2rem;"></i>
                </span>
                <h5 class="font-weight-bold text-dark mb-2">لا توجد أسئلة أو بنود حالياً</h5>
                <p class="text-muted max-w-sm mx-auto mb-4">
                    أضف بنود استطلاع جديدة أو أسئلة للمسابقة لبدء تفاعل أفراد العائلة وسحب الجوائز.
                </p>
                <a href="{{ route('dashboard.quiz-questions.create', $quizCompetition) }}" class="btn btn-primary d-inline-flex align-items-center gap-2 px-4 py-2" style="border-radius: 12px; font-weight: 700;">
                    <i class="fe fe-plus"></i>
                    <span>إضافة أول بند / سؤال</span>
                </a>
            </div>
        @endif
    </x-dashboard.card>
</div>

@push('scripts')
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script>
    $(function() {
        $('.sortable-winners').sortable({
            handle: '.drag-handle',
            items: '.sortable-row',
            axis: 'y',
            cursor: 'move',
            update: function(event, ui) {
                var $tbody = $(this);
                var questionId = $tbody.data('question-id');
                var $container = $('#saveOrder' + questionId);
                var $form = $container.find('form');

                $form.find('input[name="winner_ids[]"]').remove();

                $tbody.find('.sortable-row').each(function() {
                    var winnerId = $(this).data('winner-id');
                    $form.append('<input type="hidden" name="winner_ids[]" value="' + winnerId + '">');
                });

                $container.fadeIn();
            }
        });
    });
</script>
@endpush
@endsection
