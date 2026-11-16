@extends('layouts.app')

@section('title', 'نتائج استطلاع الرأي: ' . $quizCompetition->title)

@section('content')
@php
    $filterQs = request()->only(['q', 'correct', 'from', 'to']);
    $typeLabel = function ($q) {
        return match ($q->answer_type) {
            'multiple_choice' => 'اختيار من متعدد',
            'vote' => 'تصويت / استطلاع رأي',
            'true_false' => 'صح / خطأ',
            'ordering' => 'ترتيب العناصر',
            'survey' => 'تقييم / استبيان',
            'walking' => 'تحدي المشي',
            'walking_prediction' => 'ربط تصويت بتحدي مشي',
            default => 'إجابة حرة',
        };
    };
    $typeBadge = function ($q) {
        return match ($q->answer_type) {
            'multiple_choice' => 'info',
            'vote' => 'success',
            'true_false' => 'warning',
            'ordering' => 'primary',
            'survey' => 'teal',
            'walking' => 'success',
            'walking_prediction' => 'warning',
            default => 'secondary',
        };
    };
@endphp

@push('styles')
@include('dashboard.quiz-competitions._styles')
<style>
    .vote-progress-bar {
        background: linear-gradient(90deg, #0d9488, #10b981) !important;
        border-radius: 9999px;
    }
</style>
@endpush

<div class="container-fluid py-4">
    {{-- Page Header --}}
    <x-dashboard.page-header
        title="نتائج وإحصائيات استطلاع الرأي"
        :description="$quizCompetition->title . ' — تحليلات التصويت، نسب المشاركة، وسجل الإجابات التفصيلي.'">
        <x-slot name="actions">
            <a href="{{ route('dashboard.quiz-competitions.show', $quizCompetition) }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1 px-3 py-2" style="border-radius: 12px; font-weight: 600;">
                <i class="fe fe-arrow-right"></i>
                <span>تفاصيل الاستطلاع</span>
            </a>
            <a href="{{ route('dashboard.quiz-competitions.export', $quizCompetition) }}" class="btn btn-outline-success d-inline-flex align-items-center gap-1 px-3 py-2" style="border-radius: 12px; font-weight: 600;" target="_blank" rel="noopener">
                <i class="fe fe-download"></i>
                <span>تصدير البيانات Excel</span>
            </a>
        </x-slot>
    </x-dashboard.page-header>

    {{-- System Alerts --}}
    @include('components.alerts')

    @if(!$activeQuestion)
        <x-dashboard.card title="لا توجد بيانات" icon="fe-alert-circle">
            <div class="text-center py-5">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light text-muted mb-3" style="width: 80px; height: 80px;">
                    <i class="fe fe-help-circle" style="font-size: 2.2rem;"></i>
                </span>
                <h5 class="font-weight-bold text-dark mb-2">لا توجد أسئلة أو بنود حالياً</h5>
                <p class="text-muted max-w-sm mx-auto mb-4">أضف بنود تصويت أو أسئلة في الاستطلاع لعرض الإحصائيات.</p>
                <a href="{{ route('dashboard.quiz-competitions.show', $quizCompetition) }}" class="btn btn-primary d-inline-flex align-items-center gap-2 px-4 py-2" style="border-radius: 12px; font-weight: 700;">
                    <i class="fe fe-arrow-right"></i>
                    <span>العودة لصفحة الاستطلاع</span>
                </a>
            </div>
        </x-dashboard.card>
    @else
        <div class="row">
            {{-- Sidebar: Questions List & Poll Details (Right Column in RTL) --}}
            <div class="col-xl-3 col-lg-4 mb-4">
                <div class="sticky-top" style="top: 1.5rem; z-index: 10;">
                    {{-- Poll Summary Details Card --}}
                    <div class="card modern-card mb-3 shadow-sm">
                        <div class="card-body p-3 p-md-4">
                            <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                                <span class="font-weight-bold text-dark d-flex align-items-center gap-2" style="font-size: 0.95rem;">
                                    <i class="fe fe-info text-teal"></i> تفاصيل الاستطلاع
                                </span>
                                @if($quizCompetition->is_active)
                                    <span class="badge badge-success px-2 py-1 font-weight-bold" style="border-radius: 6px; font-size: 0.75rem;">
                                        <i class="fe fe-check mr-1"></i>نشط ومتاح
                                    </span>
                                @else
                                    <span class="badge badge-secondary px-2 py-1 font-weight-bold" style="border-radius: 6px; font-size: 0.75rem;">
                                        <i class="fe fe-pause mr-1"></i>معطل
                                    </span>
                                @endif
                            </div>

                            <h6 class="font-weight-bold text-dark mb-3" style="line-height: 1.4;">
                                {{ $quizCompetition->title }}
                            </h6>

                            <div class="small text-muted mb-2 d-flex justify-content-between align-items-center">
                                <span><i class="fe fe-calendar mr-1 text-teal"></i>تاريخ البدء:</span>
                                <strong class="text-dark">{{ $quizCompetition->start_at ? $quizCompetition->start_at->format('Y-m-d H:i') : 'غير محدد' }}</strong>
                            </div>
                            <div class="small text-muted mb-3 d-flex justify-content-between align-items-center">
                                <span><i class="fe fe-clock mr-1 text-danger"></i>تاريخ الانتهاء:</span>
                                <strong class="text-dark">{{ $quizCompetition->end_at ? $quizCompetition->end_at->format('Y-m-d H:i') : 'غير محدد' }}</strong>
                            </div>

                            <div class="p-2 rounded bg-light border mb-3 d-flex justify-content-around text-center">
                                <div>
                                    <span class="d-block text-muted small font-weight-bold">البنود</span>
                                    <strong class="text-teal font-weight-bold" style="font-size: 1.1rem;">{{ $questions->count() }}</strong>
                                </div>
                                <div class="border-right"></div>
                                <div>
                                    <span class="d-block text-muted small font-weight-bold">إجمالي المشاركات</span>
                                    <strong class="text-primary font-weight-bold" style="font-size: 1.1rem;">{{ number_format($totalAnswerCount) }}</strong>
                                </div>
                            </div>

                            <div class="d-flex flex-column gap-2">
                                <a href="{{ route('dashboard.quiz-competitions.show', $quizCompetition) }}" class="btn btn-sm btn-outline-secondary font-weight-bold d-flex align-items-center justify-content-center gap-1" style="border-radius: 8px;">
                                    <i class="fe fe-eye"></i>
                                    <span>لوحة إدارة الاستطلاع</span>
                                </a>
                                <a href="{{ route('dashboard.quiz-competitions.edit', $quizCompetition) }}" class="btn btn-sm btn-outline-primary font-weight-bold d-flex align-items-center justify-content-center gap-1" style="border-radius: 8px;">
                                    <i class="fe fe-settings"></i>
                                    <span>تعديل إعدادات الاستطلاع</span>
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- Questions Nav Card --}}
                    <div class="card modern-card shadow-sm">
                        <div class="card-header bg-transparent border-bottom p-3 d-flex align-items-center justify-content-between">
                            <span class="font-weight-bold text-dark d-flex align-items-center gap-2" style="font-size: 0.95rem;">
                                <i class="fe fe-list text-teal"></i> بنود الاستطلاع
                            </span>
                            <span class="badge badge-teal px-2 py-1 font-weight-bold" style="border-radius: 6px; font-size: 0.75rem;">
                                {{ $questions->count() }} بند
                            </span>
                        </div>
                        <div class="p-2 quiz-nav-container" style="max-height: calc(100vh - 430px); min-height: 120px; overflow-y: auto;">
                            @foreach($questions as $q)
                                <a href="{{ route('dashboard.quiz-competitions.responses', array_merge($filterQs, ['quizCompetition' => $quizCompetition, 'question_id' => $q->id])) }}"
                                   class="quiz-nav-item {{ $q->id === $activeQuestion->id ? 'active' : '' }}">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="small font-weight-bold text-muted">البند {{ sprintf('%02d', $loop->iteration) }}</span>
                                        @if($q->id === $activeQuestion->id)
                                            <span class="badge badge-success px-2 py-0 font-weight-bold" style="font-size: 0.68rem; border-radius: 4px;">
                                                <i class="fe fe-eye mr-1"></i>المعروض حالياً
                                            </span>
                                        @endif
                                    </div>
                                    <div class="quiz-nav-item-title mb-2">
                                        {!! Str::limit(strip_tags($q->question_text), 70) !!}
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-1 mt-auto">
                                        <span class="badge-modern badge-modern-{{ $typeBadge($q) }}" style="font-size: 0.72rem !important;">{{ $typeLabel($q) }}</span>
                                        <span class="badge badge-light border px-2 py-1 small" style="border-radius: 6px;">{{ $q->answers_count }} مشارك</span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            {{-- Main Content: Active Question Stats & Responses --}}
            <div class="col-xl-9 col-lg-8">
                {{-- Stat Metrics Cards --}}
                <div class="row mb-4">
                    @if($activeQuestion->answer_type === 'walking')
                        @php
                            $walkingStandingsData = $walkingStandingsData ?? app(\App\Services\Walking\WalkingCompetitionScoringService::class)->getOverallStandings($activeQuestion);
                            $walkingStandingsList = $walkingStandingsData['standings'] ?? collect();
                            $walkingTotalSteps = $walkingStandingsData['total_steps'] ?? (int) $walkingStandingsList->sum('total_steps');
                            $walkingTotalDist = $walkingStandingsData['total_distance_km'] ?? round($walkingTotalSteps * 0.00075, 2);
                            $walkingHighest = $walkingStandingsData['highest_score'] ?? (int) ($walkingStandingsList->max('total_points') ?? 0);
                        @endphp
                        <x-dashboard.stat-card
                            icon="fe-users"
                            title="إجمالي المتسابقين"
                            :value="number_format($walkingStandingsList->count()) . ' متسابق'"
                            gradient="primary"
                            class="col-xl-3 col-md-6 mb-3"
                        />
                        <x-dashboard.stat-card
                            icon="fe-trending-up"
                            title="إجمالي الخطوات المقطوعة"
                            :value="number_format($walkingTotalSteps) . ' خطوة'"
                            gradient="info"
                            class="col-xl-3 col-md-6 mb-3"
                        />
                        <x-dashboard.stat-card
                            icon="fe-map-pin"
                            title="إجمالي المسافة التقديرية"
                            :value="$walkingTotalDist . ' كم'"
                            gradient="teal"
                            class="col-xl-3 col-md-6 mb-3"
                        />
                        <x-dashboard.stat-card
                            icon="fe-award"
                            title="أعلى رصيد نقاط"
                            :value="number_format($walkingHighest) . ' نقطة'"
                            gradient="warning"
                            class="col-xl-3 col-md-6 mb-3"
                        />
                    @else
                        <x-dashboard.stat-card
                            icon="fe-send"
                            title="إجمالي الردود والمشاركات"
                            :value="number_format($totalAnswerCount)"
                            gradient="primary"
                            class="col-xl-4 col-md-6 mb-3"
                        />

                        @if(!in_array($activeQuestion->answer_type, ['vote', 'survey', 'walking_prediction'], true))
                            <x-dashboard.stat-card
                                icon="fe-check-circle"
                                title="إجابات صحيحة"
                                :value="number_format($correctCount)"
                                gradient="success"
                                class="col-xl-4 col-md-6 mb-3"
                            />
                            <x-dashboard.stat-card
                                icon="fe-x-circle"
                                title="إجابات خاطئة"
                                :value="number_format($wrongCount)"
                                gradient="danger"
                                class="col-xl-4 col-md-6 mb-3"
                            />
                        @elseif($activeQuestion->answer_type === 'survey')
                            <x-dashboard.stat-card
                                icon="fe-sliders"
                                title="بنود التقييم المضافة"
                                :value="$activeQuestion->surveyItems->count() . ' بند'"
                                gradient="success"
                                class="col-xl-4 col-md-6 mb-3"
                            />
                            <x-dashboard.stat-card
                                icon="fe-info"
                                title="نوع الاستطلاع"
                                value="استبيان إحصائي"
                                gradient="info"
                                description="تقييمات بدون درجات أو فائزين"
                                class="col-xl-4 col-md-6 mb-3"
                            />
                        @elseif($activeQuestion->answer_type === 'walking_prediction')
                            <x-dashboard.stat-card
                                icon="fe-award"
                                title="أصحاب النقاط حالياً"
                                :value="number_format(collect($predictionStandingsData['standings'] ?? [])->where('total_points', '>', 0)->count()) . ' مشارك'"
                                gradient="success"
                                class="col-xl-4 col-md-6 mb-3"
                            />
                            <x-dashboard.stat-card
                                icon="fe-activity"
                                title="حالة تحدي المشي المرتبط"
                                :value="($predictionStandingsData['is_final'] ?? false) ? 'مكتمل (نهائي)' : 'مباشر (يتحدث لحظياً)'"
                                gradient="info"
                                class="col-xl-4 col-md-6 mb-3"
                            />
                        @else
                            <x-dashboard.stat-card
                                icon="fe-users"
                                title="عدد المصوتين"
                                :value="number_format($totalAnswerCount)"
                                gradient="info"
                                class="col-xl-4 col-md-6 mb-3"
                            />
                            <x-dashboard.stat-card
                                icon="fe-list"
                                title="خيارات المرشحين"
                                :value="count($voteStats) . ' مرشح/خيار'"
                                gradient="success"
                                class="col-xl-4 col-md-6 mb-3"
                            />
                        @endif
                    @endif
                </div>

                @if($lastAnswerAt)
                    <div class="alert alert-light border py-2 px-3 small d-flex align-items-center mb-4" style="border-radius: 10px;">
                        <i class="fe fe-clock mr-2 text-primary" style="font-size: 1.1rem;"></i>
                        <span>آخر مشاركة سجلت في هذا البند بتاريخ: <strong>{{ \Carbon\Carbon::parse($lastAnswerAt)->format('Y-m-d H:i') }}</strong></span>
                    </div>
                @endif

                {{-- Active Question Details --}}
                <x-dashboard.card title="تفاصيل البند الحالي" icon="fe-help-circle" class="mb-4">
                    <span class="badge-modern badge-modern-{{ $typeBadge($activeQuestion) }} mb-2">
                        {{ $typeLabel($activeQuestion) }}
                    </span>
                    <h5 class="font-weight-bold text-dark mb-2" style="line-height: 1.5;">
                        {!! $activeQuestion->question_text !!}
                    </h5>
                    @if($activeQuestion->description)
                        <div class="text-muted small mt-2">
                            {!! Str::limit(strip_tags($activeQuestion->description), 300) !!}
                        </div>
                    @endif

                    @if($activeQuestion->answer_type === 'vote')
                        @php $rules = (array) ($activeQuestion->candidate_rules ?? []); @endphp
                        <div class="mt-3 pt-3 border-top">
                            <h6 class="font-weight-bold text-muted small mb-2 d-flex align-items-center gap-1">
                                <i class="fe fe-sliders text-teal"></i>
                                <span>قواعد وشروط التصويت لهذا البند:</span>
                            </h6>
                            <div class="d-flex flex-wrap gap-2">
                                @if(($rules['nomination_type'] ?? '') === 'open_tree')
                                    <span class="rule-chip"><i class="fe fe-git-pull-request"></i> ترشيح واختيار مفتوح من شجرة العائلة</span>
                                @else
                                    <span class="rule-chip"><i class="fe fe-check-square"></i> قائمة مرشحين محددة مسبقاً</span>
                                @endif

                                <span class="rule-chip"><i class="fe fe-check-circle"></i> الحد الأقصى للاختيار: <strong>{{ $activeQuestion->vote_max_selections ?? 1 }} مرشح</strong></span>

                                @if(($rules['allowed_gender'] ?? '') === 'male')
                                    <span class="rule-chip"><i class="fe fe-user"></i> المرشحون: <strong>رجال العائلة فقط</strong></span>
                                @elseif(($rules['allowed_gender'] ?? '') === 'female')
                                    <span class="rule-chip"><i class="fe fe-user"></i> المرشحات: <strong>نساء العائلة فقط</strong></span>
                                @else
                                    <span class="rule-chip"><i class="fe fe-users"></i> المرشحون: <strong>كافة أفراد العائلة</strong></span>
                                @endif

                                @if(!empty($rules['exclude_deceased']))
                                    <span class="rule-chip"><i class="fe fe-shield"></i> استبعاد المتوفين: <strong>مفعل</strong></span>
                                @endif

                                @if(!empty($rules['walking_condition']['enabled']))
                                    <span class="rule-chip"><i class="fe fe-activity text-success"></i> شرط المشي: <strong>مفعل (نافذة {{ $rules['walking_condition']['days_window'] ?? 3 }} أيام)</strong></span>
                                @endif

                                @if(!empty($rules['require_whatsapp']))
                                    <span class="rule-chip"><i class="fe fe-message-circle text-success"></i> اشتراط الواتساب: <strong>مفعل</strong></span>
                                @endif
                            </div>
                        </div>
                    @endif
                </x-dashboard.card>

                {{-- Vote Results Chart --}}
                @if($activeQuestion->answer_type === 'vote')
                    @if(count($voteStats))
                        <x-dashboard.card title="نتائج وتوزيع التصويت الحالي" icon="fe-pie-chart" class="mb-4">
                            <x-slot name="headerAction">
                                <span class="badge badge-teal px-3 py-1 font-weight-bold" style="border-radius: 8px;">
                                    إجمالي الأصوات: {{ number_format(array_sum(array_column($voteStats, 'count'))) }} صوت
                                </span>
                            </x-slot>

                            <div class="row">
                                @foreach($voteStats as $index => $vs)
                                    @php
                                        $choiceRow = $vs['choice'];
                                        $rank = $index + 1;
                                        $rankClass = match($rank) {
                                            1 => 'vote-rank-1',
                                            2 => 'vote-rank-2',
                                            3 => 'vote-rank-3',
                                            default => 'vote-rank-other'
                                        };
                                        $rankLabel = match($rank) {
                                            1 => '🥇 المركز 1',
                                            2 => '🥈 المركز 2',
                                            3 => '🥉 المركز 3',
                                            default => '#' . $rank
                                        };
                                    @endphp
                                    <div class="col-12">
                                        <div class="vote-candidate-card">
                                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="vote-rank-badge {{ $rankClass }}">{{ $rankLabel }}</span>
                                                    <div>
                                                        <strong class="d-block" style="font-size: 0.98rem; color: var(--premium-text-primary);">
                                                            {!! strip_tags($choiceRow->choice_text) !!}
                                                        </strong>
                                                        @if(!empty($choiceRow->person_id))
                                                            <span class="text-muted small"><i class="fe fe-user-check text-teal mr-1"></i>عضو في شجرة العائلة (#{{ $choiceRow->person_id }})</span>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="text-right">
                                                    <span class="font-weight-extrabold text-teal" style="font-size: 1.35rem;">{{ $vs['percent'] }}%</span>
                                                    <small class="text-muted d-block font-weight-bold">({{ number_format($vs['count']) }} صوت)</small>
                                                </div>
                                            </div>
                                            <div class="progress rounded-pill bg-light" style="height: 12px; overflow: hidden;">
                                                <div class="progress-bar vote-progress-bar" role="progressbar"
                                                     style="width: {{ min(100, $vs['percent']) }}%;"
                                                     aria-valuenow="{{ $vs['percent'] }}" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </x-dashboard.card>
                    @else
                        <x-dashboard.card title="نتائج وتوزيع التصويت الحالي" icon="fe-pie-chart" class="mb-4">
                            <div class="text-center py-4 text-muted">
                                <i class="fe fe-pie-chart fa-2x mb-2 d-block opacity-50"></i>
                                <p class="mb-0">لا توجد أصوات مسجلة بعد لهذا البند.</p>
                            </div>
                        </x-dashboard.card>
                    @endif
                @endif

                {{-- Survey Summary Cards --}}
                @if($activeQuestion->answer_type === 'survey' && count($surveySummary))
                    <div class="row mb-4">
                        @foreach($surveySummary as $sum)
                            @php $item = $sum['item']; @endphp
                            <div class="col-md-6 mb-4">
                                <div class="card survey-item-card h-100 shadow-sm border-0">
                                    <div class="survey-item-header">
                                        <span class="badge-modern badge-modern-teal mb-2" style="font-size: 0.72rem !important;">
                                            @if($sum['kind'] === 'rating') تقييم نجوم
                                            @elseif($sum['kind'] === 'number') إدخال رقمي
                                            @else رد نصي @endif
                                        </span>
                                        <strong class="d-block text-dark" style="font-size: 0.95rem;">
                                            {!! strip_tags($item->body_text ?: $item->labelForAdmin()) !!}
                                        </strong>
                                    </div>
                                    <div class="card-body p-4">
                                        @if($sum['kind'] === 'rating')
                                            <div class="d-flex align-items-baseline mb-3">
                                                <span class="font-weight-extrabold text-primary" style="font-size: 2.2rem;">{{ $sum['avg'] ?? '—' }}</span>
                                                <span class="text-muted mr-2">/ 5 متوسط التقييم</span>
                                            </div>
                                            <small class="text-muted d-block mb-3">إجمالي المقيمين: {{ $sum['count'] }} مشارك</small>

                                            @if(!empty($sum['distribution']))
                                                @foreach($sum['distribution'] as $star => $cnt)
                                                    <div class="d-flex align-items-center mb-2 small">
                                                        <span class="text-muted font-weight-bold" style="width: 3.5rem;">{{ $star }} نجوم</span>
                                                        <div class="flex-grow-1 progress mx-3 bg-light" style="height: 10px; border-radius: 999px;">
                                                            <div class="progress-bar bg-warning" style="width: {{ $sum['max_bar'] ? round(100 * $cnt / $sum['max_bar']) : 0 }}%; border-radius: 999px;"></div>
                                                        </div>
                                                        <span class="font-weight-bold text-dark" style="width: 25px; text-align: left;">{{ $cnt }}</span>
                                                    </div>
                                                @endforeach
                                            @endif
                                        @elseif($sum['kind'] === 'number')
                                            <div class="row text-center py-2 bg-light rounded-lg mb-3">
                                                <div class="col-4">
                                                    <small class="text-muted font-weight-bold d-block">أدنى قيمة</small>
                                                    <span class="h5 mb-0 font-weight-bold text-dark">{{ $sum['min'] ?? '—' }}</span>
                                                </div>
                                                <div class="col-4 border-right border-left">
                                                    <small class="text-muted font-weight-bold d-block">المتوسط</small>
                                                    <span class="h5 mb-0 font-weight-bold text-primary">{{ $sum['avg'] ?? '—' }}</span>
                                                </div>
                                                <div class="col-4">
                                                    <small class="text-muted font-weight-bold d-block">أقصى قيمة</small>
                                                    <span class="h5 mb-0 font-weight-bold text-dark">{{ $sum['max'] ?? '—' }}</span>
                                                </div>
                                            </div>
                                            <small class="text-muted d-block"><i class="fe fe-calculator mr-1"></i> إجمالي المدخلات: <strong>{{ $sum['count'] }} مشاركة</strong></small>
                                        @else
                                            <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                                                <span class="h5 mb-0 font-weight-bold text-dark">{{ $sum['count'] }}</span>
                                                <small class="text-muted">ردود نصية</small>
                                            </div>
                                            @if(!empty($sum['samples']))
                                                <ul class="list-unstyled small text-muted mb-0">
                                                    @foreach($sum['samples'] as $sample)
                                                        <li class="border-bottom py-2 d-flex align-items-start gap-2">
                                                            <i class="fe fe-message-square text-primary mt-1"></i>
                                                            <span>{{ Str::limit($sample, 100) }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Walking Prediction Admin Breakdown Card --}}
                @if(!empty($predictionStandingsData))
                    @php
                        $predStandings = $predictionStandingsData['standings'] ?? collect();
                        $predRankPointsMap = $predictionStandingsData['rank_points_map'] ?? [];
                        $predWalkingTopRanks = $predictionStandingsData['walking_top_ranks'] ?? [];
                        $predIsFinal = (bool) ($predictionStandingsData['is_final'] ?? false);
                    @endphp
                    <x-dashboard.card title="تفصيل نقاط وتوقعات المتسابقين (مرتبط بتحدي المشي — خاص بالإدارة)" icon="fe-award" class="mb-4">
                        <x-slot name="headerAction">
                            <span class="badge {{ $predIsFinal ? 'badge-success' : 'badge-warning text-dark' }} px-3 py-1 font-weight-bold" style="border-radius: 8px;">
                                {{ $predIsFinal ? '🏆 نتائج نهائية معتمدة' : '🔴 مباشر — يتحدث مع خطوات المشي' }}
                            </span>
                        </x-slot>

                        {{-- 1. Rank Points Map & Current Walking Top Ranks --}}
                        <div class="row mb-3">
                            <div class="col-lg-6 mb-3 mb-lg-0">
                                <div class="p-3 rounded border bg-light h-100">
                                    <h6 class="font-weight-bold text-dark small mb-2">
                                        <i class="fe fe-sliders text-warning mr-1"></i>جدول النقاط المعتمد لكل مركز متوقع:
                                    </h6>
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach($predRankPointsMap as $rNum => $ptsVal)
                                            <span class="badge badge-white border px-2 py-1 font-weight-bold text-dark" style="border-radius: 8px; font-size: 0.82rem;">
                                                المركز #{{ $rNum }}: <span class="text-success font-weight-extrabold">{{ $ptsVal }} نقطة</span>
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="p-3 rounded border bg-light h-100">
                                    <h6 class="font-weight-bold text-dark small mb-2">
                                        <i class="fe fe-activity text-teal mr-1"></i>المتصدرون الفعليون حالياً في تحدي المشي:
                                    </h6>
                                    @if(count($predWalkingTopRanks))
                                        <div class="d-flex flex-wrap gap-2">
                                            @foreach($predWalkingTopRanks as $wRank)
                                                <span class="badge badge-white border px-2 py-1 font-weight-bold text-dark" style="border-radius: 8px; font-size: 0.8rem;">
                                                    <span class="text-primary">#{{ $wRank['rank'] }}</span> {{ $wRank['name'] }}
                                                    <small class="text-muted">({{ number_format($wRank['total_points'] ?? 0) }} ن / {{ number_format($wRank['total_steps'] ?? 0) }} خطوة)</small>
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-muted small">لم تُسجل خطوات أو نقاط فوق الصفر في تحدي المشي حتى الآن.</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- 2. Detailed Voters Breakdown Table --}}
                        <div class="table-responsive border rounded">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th style="width: 110px;">المركز</th>
                                        <th style="width: 200px;">المشارك (المصوّت)</th>
                                        <th>تفصيل التوقعات بالترتيب (الخانة المتوقعة ← المرشح ← المطابقة)</th>
                                        <th style="width: 140px;" class="text-center">إجمالي النقاط</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($predStandings as $row)
                                        <tr>
                                            <td>
                                                @if(($row->total_points ?? 0) > 0 && $row->rank)
                                                    <span class="badge {{ $row->rank === 1 ? 'badge-warning text-dark' : ($row->rank === 2 ? 'badge-secondary' : 'badge-info') }} px-2 py-1 font-weight-bold" style="border-radius: 6px; font-size: 0.82rem;">
                                                        @if($row->rank === 1) 👑 @elseif($row->rank === 2) 🥈 @elseif($row->rank === 3) 🥉 @endif
                                                        المركز #{{ $row->rank }}
                                                    </span>
                                                @else
                                                    <span class="badge badge-light border text-muted px-2 py-1" style="border-radius: 6px;">
                                                        ⏳ بانتظار إصابة
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                <strong class="text-dark d-block">{{ $row->voter_name }}</strong>
                                                <small class="text-muted">أصاب {{ $row->matched_count }} من {{ count($predRankPointsMap) }} مراكز</small>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-2">
                                                    @foreach(($row->matches_details ?? []) as $md)
                                                        <div class="px-2 py-1 rounded border small {{ !empty($md['is_match']) ? 'bg-success-light border-success text-dark' : 'bg-light text-muted' }}" style="border-radius: 8px; font-size: 0.8rem;">
                                                            <span class="font-weight-bold">المركز #{{ $md['predicted_rank'] }}:</span>
                                                            <span>{{ $md['predicted_name'] ?? '—' }}</span>
                                                            @if(!empty($md['is_match']))
                                                                <span class="badge badge-success ml-1 px-1 py-0" style="font-size: 0.72rem;">✅ +{{ $md['points_earned'] }}</span>
                                                            @else
                                                                <span class="badge badge-light border text-danger ml-1 px-1 py-0" style="font-size: 0.7rem;">
                                                                    ❌ (بالمشي: {{ !empty($md['actual_walking_rank']) ? '#' . $md['actual_walking_rank'] : 'خارج التصدر' }}) +0
                                                                </span>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="font-weight-extrabold {{ ($row->total_points ?? 0) > 0 ? 'text-success' : 'text-muted' }}" style="font-size: 1.15rem;">
                                                    {{ number_format($row->total_points ?? 0) }} نقطة
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">لا توجد توقعات مسجلة حتى الآن.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </x-dashboard.card>
                @endif

                @if($activeQuestion->answer_type === 'walking')
                    @php
                        $walkingStandingsData = $walkingStandingsData ?? app(\App\Services\Walking\WalkingCompetitionScoringService::class)->getOverallStandings($activeQuestion);
                        $walkingStandingsList = $walkingStandingsData['standings'] ?? collect();
                    @endphp

                    {{-- Walking Leaderboard Card --}}
                    <x-dashboard.card title="لوحة متصدري تحدي المشي وإدارة النقاط المعتمدة" icon="fe-activity" padding="p-0" class="mb-4">
                        <x-slot name="headerAction">
                            <span class="badge badge-teal px-3 py-1 font-weight-bold" style="border-radius: 8px;">
                                إجمالي المتنافسين: {{ $walkingStandingsList->count() }} متسابق
                            </span>
                        </x-slot>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="walkingLeaderboardTable">
                                <thead>
                                    <tr>
                                        <th style="width: 60px;" class="text-center">#</th>
                                        <th>المتسابق</th>
                                        <th class="text-center">خطوات اليوم</th>
                                        <th class="text-center">إجمالي الخطوات</th>
                                        <th class="text-center">المسافة (كم)</th>
                                        <th class="text-center">نقاط المشي</th>
                                        <th class="text-center">التعديل اليدوي</th>
                                        <th class="text-center">إجمالي النقاط</th>
                                        <th class="text-center" style="width: 140px;">الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($walkingStandingsList as $index => $row)
                                        @php
                                            $rank = $row->rank ?? ($index + 1);
                                            $rankBadge = match($rank) {
                                                1 => '🥇 1',
                                                2 => '🥈 2',
                                                3 => '🥉 3',
                                                default => '#' . $rank
                                            };
                                            $rankClass = match($rank) {
                                                1 => 'badge-warning text-dark font-weight-extrabold',
                                                2 => 'badge-light border text-dark font-weight-bold',
                                                3 => 'badge-light border text-dark font-weight-bold',
                                                default => 'badge-light text-muted'
                                            };
                                            $walkingPts = (int) ($row->actual_points ?? 0);
                                            $manualPts = (int) ($row->manual_points ?? 0);
                                            $distKm = round(($row->total_steps ?? 0) * 0.00075, 2);
                                        @endphp
                                        <tr id="participant-row-{{ $row->person_id }}">
                                            <td class="text-center">
                                                <span class="badge {{ $rankClass }} px-2 py-1" style="font-size: 0.85rem; border-radius: 6px;">
                                                    {{ $rankBadge }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    @if(!empty($row->photo_url) || !empty($row->avatar))
                                                        <img src="{{ $row->avatar ?? $row->photo_url }}" alt="{{ $row->name }}" class="rounded-circle border" style="width: 36px; height: 36px; object-fit: cover;">
                                                    @else
                                                        <div class="rounded-circle bg-teal-light text-teal d-flex align-items-center justify-content-center font-weight-bold" style="width: 36px; height: 36px;">
                                                            {{ mb_substr($row->name, 0, 1) }}
                                                        </div>
                                                    @endif
                                                    <div>
                                                        <strong class="d-block text-dark" style="font-size: 0.95rem;">
                                                            {{ $row->name }}
                                                        </strong>
                                                        @if(!empty($row->is_anonymous))
                                                            <span class="badge badge-secondary px-2 py-0" style="font-size: 0.7rem;">
                                                                <i class="fe fe-eye-off mr-1"></i>متخفي بالتطبيق
                                                            </span>
                                                        @else
                                                            <small class="text-muted">#{{ $row->person_id }}</small>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center font-weight-bold text-dark">
                                                {{ number_format($row->today_steps ?? 0) }}
                                            </td>
                                            <td class="text-center font-weight-bold text-teal">
                                                {{ number_format($row->total_steps ?? 0) }}
                                            </td>
                                            <td class="text-center text-muted small">
                                                {{ $distKm }} كم
                                            </td>
                                            <td class="text-center font-weight-bold text-primary">
                                                <span class="row-walking-points">{{ number_format($walkingPts) }}</span>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm p-0 border-0 btn-open-history"
                                                    data-person-id="{{ $row->person_id }}"
                                                    data-person-name="{{ $row->name }}"
                                                    data-actual-points="{{ $walkingPts }}"
                                                    data-manual-points="{{ $manualPts }}"
                                                    data-total-points="{{ $row->total_points ?? 0 }}"
                                                    title="عرض سجل التعديلات السابقة">
                                                    @if($manualPts > 0)
                                                        <span class="badge badge-success px-2 py-1 font-weight-bold row-manual-badge" style="border-radius: 6px;">
                                                            +{{ $manualPts }}
                                                        </span>
                                                    @elseif($manualPts < 0)
                                                        <span class="badge badge-danger px-2 py-1 font-weight-bold row-manual-badge" style="border-radius: 6px;">
                                                            {{ $manualPts }}
                                                        </span>
                                                    @else
                                                        <span class="badge badge-light border text-muted px-2 py-1 row-manual-badge" style="border-radius: 6px;">
                                                            0
                                                        </span>
                                                    @endif
                                                </button>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-teal px-3 py-1 font-weight-extrabold row-total-badge" style="font-size: 0.95rem; border-radius: 8px;">
                                                    {{ number_format($row->total_points ?? 0) }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-outline-primary font-weight-bold d-inline-flex align-items-center gap-1 btn-adjust-points"
                                                    data-person-id="{{ $row->person_id }}"
                                                    data-person-name="{{ $row->name }}"
                                                    data-actual-points="{{ $walkingPts }}"
                                                    data-manual-points="{{ $manualPts }}"
                                                    data-total-points="{{ $row->total_points ?? 0 }}"
                                                    style="border-radius: 8px;">
                                                    <i class="fe fe-edit-2"></i>
                                                    <span>تعديل النقاط</span>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center text-muted py-5">
                                                <i class="fe fe-activity fa-2x mb-2 d-block text-muted"></i>
                                                <p class="mb-0">لا توجد سجلات خطوات أو نقاط مسجلة في هذا التحدي حتى الآن.</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </x-dashboard.card>

                    {{-- Manual Points Adjustment Modal --}}
                    <div class="modal fade" id="adjustWalkingModal" tabindex="-1" role="dialog" aria-labelledby="adjustWalkingModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                            <div class="modal-content" style="border-radius: 14px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
                                <div class="modal-header bg-light border-bottom p-3">
                                    <h5 class="modal-title font-weight-bold text-dark d-flex align-items-center gap-2" id="adjustWalkingModalLabel">
                                        <i class="fe fe-sliders text-teal"></i>
                                        <span>إدارة نقاط المتسابق: <span id="modalPersonName" class="text-teal font-weight-bold"></span></span>
                                    </h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body p-4">
                                    {{-- Participant Balance Overview Strip --}}
                                    <div class="row text-center mb-4">
                                        <div class="col-4">
                                            <div class="p-3 rounded bg-light border">
                                                <span class="d-block text-muted small font-weight-bold mb-1">نقاط المشي المحسوبة</span>
                                                <h4 class="font-weight-extrabold text-primary mb-0" id="modalActualPoints">0</h4>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="p-3 rounded bg-light border">
                                                <span class="d-block text-muted small font-weight-bold mb-1">صافي التعديلات اليدوية</span>
                                                <h4 class="font-weight-extrabold text-teal mb-0" id="modalManualPoints">0</h4>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="p-3 rounded bg-teal-light border border-teal">
                                                <span class="d-block text-teal small font-weight-bold mb-1">إجمالي الرصيد النهائي</span>
                                                <h4 class="font-weight-extrabold text-success mb-0" id="modalTotalPoints">0</h4>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Navigation Tabs --}}
                                    <ul class="nav nav-pills mb-3 border-bottom pb-2" id="walkingModalTabs" role="tablist">
                                        <li class="nav-item">
                                            <a class="nav-link active font-weight-bold px-3 py-2" id="tab-new-adj" data-toggle="pill" href="#pane-new-adj" role="tab" style="border-radius: 8px;">
                                                <i class="fe fe-plus-circle mr-1"></i>إضافة تعديل جديد
                                            </a>
                                        </li>
                                        <li class="nav-item mr-2">
                                            <a class="nav-link font-weight-bold px-3 py-2" id="tab-history-adj" data-toggle="pill" href="#pane-history-adj" role="tab" style="border-radius: 8px;">
                                                <i class="fe fe-clock mr-1"></i>سجل التعديلات السابقة (<span id="modalHistoryCount">0</span>)
                                            </a>
                                        </li>
                                    </ul>

                                    <div class="tab-content" id="walkingModalTabsContent">
                                        {{-- Tab 1: New Adjustment Form --}}
                                        <div class="tab-pane fade show active" id="pane-new-adj" role="tabpanel">
                                            <form id="formAdjustWalkingPoints">
                                                <input type="hidden" name="person_id" id="formPersonId">

                                                <div class="row">
                                                    <div class="col-md-4 mb-3">
                                                        <label class="font-weight-bold small text-muted mb-2">نوع التعديل <span class="text-danger">*</span></label>
                                                        <select name="type" id="formAdjustmentType" class="form-control" style="border-radius: 8px;" required>
                                                            <option value="bonus">مكافأة إضافية (+)</option>
                                                            <option value="penalty">خصم جزائي (-)</option>
                                                            <option value="correction">تصحيح رصيد (+/-)</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4 mb-3">
                                                        <label class="font-weight-bold small text-muted mb-2">عدد النقاط <span class="text-danger">*</span></label>
                                                        <input type="number" name="points" id="formAdjustmentPoints" class="form-control" placeholder="مثلاً: 10" style="border-radius: 8px;" required>
                                                        <small class="text-muted d-block mt-1">القيمة سالبة تلقائياً عند اختيار خصم</small>
                                                    </div>
                                                    <div class="col-md-4 mb-3">
                                                        <label class="font-weight-bold small text-muted mb-2">تاريخ السريان المرتبط</label>
                                                        <input type="date" name="date" id="formAdjustmentDate" class="form-control" style="border-radius: 8px;">
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="font-weight-bold small text-muted mb-2">سبب التعديل وسجل التبرير <span class="text-danger">*</span></label>
                                                    <input type="text" name="reason" id="formAdjustmentReason" class="form-control" maxlength="255" placeholder="أدخل سبب التعديل (مثلاً: حضور الفعالية الميدانية، الفوز بتحدي فرعي، مخالفة مسار...)" style="border-radius: 8px;" required>
                                                </div>

                                                <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                                                    <button type="button" class="btn btn-secondary px-3" data-dismiss="modal" style="border-radius: 8px;">إلغاء</button>
                                                    <button type="submit" id="btnSubmitAdjustment" class="btn btn-primary px-4 font-weight-bold d-inline-flex align-items-center gap-1" style="border-radius: 8px;">
                                                        <i class="fe fe-check"></i>
                                                        <span>حفظ وتطبيق التعديل</span>
                                                    </button>
                                                </div>
                                            </form>
                                        </div>

                                        {{-- Tab 2: History Table --}}
                                        <div class="tab-pane fade" id="pane-history-adj" role="tabpanel">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover align-middle mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>النوع</th>
                                                            <th class="text-center">النقاط</th>
                                                            <th>السبب والتبرير</th>
                                                            <th class="text-center">التاريخ</th>
                                                            <th>المشرف</th>
                                                            <th style="width: 60px;" class="text-center">حذف</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="modalHistoryTableBody">
                                                        <tr>
                                                            <td colspan="6" class="text-center text-muted py-4">
                                                                <div class="spinner-border spinner-border-sm text-teal mr-2" role="status"></div>
                                                                <span>جاري تحميل سجل التعديلات...</span>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    {{-- Filter Card --}}
                    <x-dashboard.card title="تصفية وفرز المشاركات" icon="fe-filter" class="mb-4">
                        <form method="get" action="{{ route('dashboard.quiz-competitions.responses', $quizCompetition) }}" class="row align-items-end">
                            <input type="hidden" name="question_id" value="{{ $activeQuestion->id }}">

                            <div class="col-xl-3 col-md-6 mb-3 mb-xl-0">
                                <label class="small font-weight-bold text-muted mb-2">بحث بالاسم أو رقم الجوال</label>
                                <input type="text" name="q" class="form-control" style="border-radius: 10px;" value="{{ request('q') }}" placeholder="أدخل الاسم أو الجوال...">
                            </div>

                            @if(!in_array($activeQuestion->answer_type, ['vote', 'survey', 'walking_prediction'], true))
                                <div class="col-xl-2 col-md-6 mb-3 mb-xl-0">
                                    <label class="small font-weight-bold text-muted mb-2">حالة الإجابة</label>
                                    <select name="correct" class="form-control" style="border-radius: 10px;">
                                        <option value="">كل الإجابات</option>
                                        <option value="1" @selected(request('correct') === '1')>إجابة صحيحة</option>
                                        <option value="0" @selected(request('correct') === '0')>إجابة خاطئة</option>
                                    </select>
                                </div>
                            @endif

                            <div class="col-xl-2 col-md-6 mb-3 mb-md-0">
                                <label class="small font-weight-bold text-muted mb-2">من تاريخ</label>
                                <input type="date" name="from" class="form-control" style="border-radius: 10px;" value="{{ request('from') }}">
                            </div>
                            <div class="col-xl-2 col-md-6 mb-3 mb-md-0">
                                <label class="small font-weight-bold text-muted mb-2">إلى تاريخ</label>
                                <input type="date" name="to" class="form-control" style="border-radius: 10px;" value="{{ request('to') }}">
                            </div>

                            <div class="col-xl-3 col-md-12 d-flex gap-2">
                                <button type="submit" class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-1 flex-grow-1" style="border-radius: 10px; font-weight: 700;">
                                    <i class="fe fe-search"></i>
                                    <span>تطبيق الفلتر</span>
                                </button>
                                <a href="{{ route('dashboard.quiz-competitions.responses', ['quizCompetition' => $quizCompetition, 'question_id' => $activeQuestion->id]) }}" class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center" style="border-radius: 10px;" title="إعادة تعيين">
                                    <i class="fe fe-rotate-ccw"></i>
                                </a>
                            </div>
                        </form>
                    </x-dashboard.card>

                    {{-- Responses Table --}}
                    <x-dashboard.card title="سجل المشاركات والردود الفردية" icon="fe-database" padding="p-0">
                        <x-slot name="headerAction">
                            <span class="badge badge-light border px-3 py-1 font-weight-bold" style="border-radius: 8px;">
                                المشاركات المصفاة: {{ $answers->total() }} سجل
                            </span>
                        </x-slot>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">#</th>
                                        <th>اسم العضو المشارك</th>
                                        <th>رقم الجوال</th>
                                        <th>اسم الأم (للتأكيد)</th>
                                        <th>الاستجابة / الإجابة</th>
                                        <th>الحالة / النوع</th>
                                        <th style="width: 140px;">التاريخ والوقت</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($answers as $i => $answer)
                                        <tr>
                                            <td>
                                                <span class="text-muted font-weight-bold">{{ $answers->firstItem() + $i }}</span>
                                            </td>
                                            <td>
                                                @if($answer->person)
                                                    <span class="font-weight-bold text-dark">{{ $answer->person->name }}</span>
                                                    <span class="badge badge-success-light text-success font-weight-normal px-2 py-0 mr-1" style="font-size: 0.75rem;">
                                                        <i class="fe fe-check-circle mr-1"></i>عضو عائلة
                                                    </span>
                                                @else
                                                    <span class="font-weight-bold text-dark">{{ $answer->user?->name ?? 'مستخدم غير مسجل' }}</span>
                                                    <span class="badge badge-secondary-light text-secondary font-weight-normal px-2 py-0 mr-1" style="font-size: 0.75rem;">
                                                        رمز 112 / ضيف
                                                    </span>
                                                @endif
                                            </td>
                                            <td dir="ltr" class="text-muted font-weight-bold">
                                                {{ $answer->person ? ($answer->person->resolveQuizPhone() ?? '—') : ($answer->user?->phone ?? '—') }}
                                            </td>
                                            <td>
                                                @if($answer->person && $answer->person->mother)
                                                    <span class="badge badge-light border px-2 py-1">{{ $answer->person->mother->name }}</span>
                                                @elseif($answer->user && $answer->user->is_from_ancestry && $answer->user->mother_name)
                                                    <span class="badge badge-light border px-2 py-1">{{ $answer->user->mother_name }}</span>
                                                @else
                                                    <span class="text-muted small">—</span>
                                                @endif
                                            </td>
                                            <td style="max-width: 320px;">
                                                <div class="font-weight-bold text-dark" style="font-size: 0.92rem;">
                                                    <x-quiz-answer-cell :question="$activeQuestion->sourceVoteQuestion ?? $activeQuestion" :answer="$answer" :limit="600" />
                                                </div>
                                            </td>
                                            <td>
                                                @if(in_array($activeQuestion->answer_type, ['vote', 'walking_prediction'], true))
                                                    <span class="badge badge-success px-2 py-1 font-weight-bold" style="border-radius: 6px;">تصويت / توقع</span>
                                                @elseif($activeQuestion->answer_type === 'survey')
                                                    <span class="badge badge-info px-2 py-1 font-weight-bold" style="border-radius: 6px;">استبيان</span>
                                                @elseif($answer->is_correct)
                                                    <span class="badge badge-success px-2 py-1 font-weight-bold" style="border-radius: 6px;">صحيحة</span>
                                                @else
                                                    <span class="badge badge-danger px-2 py-1 font-weight-bold" style="border-radius: 6px;">خاطئة</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="text-muted small text-nowrap">
                                                    {{ $answer->created_at?->format('Y-m-d H:i') ?? '—' }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-5">
                                                <i class="fe fe-search fa-2x mb-2 d-block text-muted"></i>
                                                <p class="mb-0">لم يتم العثور على أي ردود مطابقة لشروط البحث والتصفية.</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if($answers->hasPages())
                            <div class="card-footer bg-transparent border-top py-3 d-flex justify-content-center">
                                {{ $answers->links() }}
                            </div>
                        @endif
                    </x-dashboard.card>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection

@if($activeQuestion && $activeQuestion->answer_type === 'walking')
@push('scripts')
<script>
$(document).ready(function() {
    var adjustPointsUrl = "{{ route('dashboard.quiz-questions.adjust-walking-points', [$quizCompetition, $activeQuestion]) }}";
    var getAdjustmentsBaseUrl = "{{ url('dashboard/quiz-competitions/' . $quizCompetition->id . '/questions/' . $activeQuestion->id . '/adjustments') }}";

    function updateModalBalances(actual, manual, total) {
        $('#modalActualPoints').text(Number(actual).toLocaleString());
        $('#modalManualPoints').text((manual > 0 ? '+' : '') + Number(manual).toLocaleString());
        $('#modalTotalPoints').text(Number(total).toLocaleString());
    }

    function loadHistory(personId) {
        $('#modalHistoryTableBody').html('<tr><td colspan="6" class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm text-teal mr-2" role="status"></div><span>جاري تحميل سجل التعديلات...</span></td></tr>');
        $.ajax({
            url: getAdjustmentsBaseUrl + '/' + personId,
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(res) {
                if (res.success && res.data) {
                    var adjustments = res.data.adjustments || [];
                    $('#modalHistoryCount').text(adjustments.length);
                    if (adjustments.length === 0) {
                        $('#modalHistoryTableBody').html('<tr><td colspan="6" class="text-center text-muted py-4">لا توجد أي تعديلات يدوية مسجلة لهذا المتسابق.</td></tr>');
                        return;
                    }
                    var html = '';
                    adjustments.forEach(function(adj) {
                        var typeLabel = adj.type === 'bonus' ? '<span class="badge badge-success px-2 py-0">مكافأة</span>' : (adj.type === 'penalty' ? '<span class="badge badge-danger px-2 py-0">خصم</span>' : '<span class="badge badge-info px-2 py-0">تصحيح</span>');
                        var pointsFormatted = adj.points > 0 ? '<strong class="text-success">+' + adj.points + '</strong>' : '<strong class="text-danger">' + adj.points + '</strong>';
                        html += '<tr>' +
                            '<td>' + typeLabel + '</td>' +
                            '<td class="text-center">' + pointsFormatted + '</td>' +
                            '<td>' + (adj.reason || '—') + '</td>' +
                            '<td class="text-center small text-muted">' + (adj.date || adj.created_at || '—') + '</td>' +
                            '<td class="small">' + (adj.creator_name || 'النظام') + '</td>' +
                            '<td class="text-center">' +
                                '<button type="button" class="btn btn-sm btn-outline-danger p-1 btn-delete-adj" data-adj-id="' + adj.id + '" title="حذف هذا التعديل">' +
                                    '<i class="fe fe-trash-2"></i>' +
                                '</button>' +
                            '</td>' +
                        '</tr>';
                    });
                    $('#modalHistoryTableBody').html(html);
                }
            },
            error: function() {
                $('#modalHistoryTableBody').html('<tr><td colspan="6" class="text-center text-danger py-4">فشل في تحميل سجل التعديلات.</td></tr>');
            }
        });
    }

    function openModalForPerson(personId, personName, actualPts, manualPts, totalPts, openHistoryTab) {
        $('#formPersonId').val(personId);
        $('#modalPersonName').text(personName);
        $('#formAdjustmentPoints').val('');
        $('#formAdjustmentReason').val('');
        $('#formAdjustmentType').val('bonus');
        $('#formAdjustmentDate').val('');
        updateModalBalances(actualPts, manualPts, totalPts);

        loadHistory(personId);

        if (openHistoryTab) {
            $('#tab-history-adj').tab('show');
        } else {
            $('#tab-new-adj').tab('show');
        }

        $('#adjustWalkingModal').modal('show');
    }

    $(document).on('click', '.btn-adjust-points', function() {
        var btn = $(this);
        openModalForPerson(
            btn.data('person-id'),
            btn.data('person-name'),
            btn.data('actual-points'),
            btn.data('manual-points'),
            btn.data('total-points'),
            false
        );
    });

    $(document).on('click', '.btn-open-history', function() {
        var btn = $(this);
        openModalForPerson(
            btn.data('person-id'),
            btn.data('person-name'),
            btn.data('actual-points'),
            btn.data('manual-points'),
            btn.data('total-points'),
            true
        );
    });

    $('#formAdjustWalkingPoints').on('submit', function(e) {
        e.preventDefault();
        var submitBtn = $('#btnSubmitAdjustment');
        submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1"></span> جاري الحفظ...');

        var formData = {
            _token: '{{ csrf_token() }}',
            person_id: $('#formPersonId').val(),
            type: $('#formAdjustmentType').val(),
            points: $('#formAdjustmentPoints').val(),
            reason: $('#formAdjustmentReason').val(),
            date: $('#formAdjustmentDate').val()
        };

        $.ajax({
            url: adjustPointsUrl,
            method: 'POST',
            data: formData,
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(res) {
                submitBtn.prop('disabled', false).html('<i class="fe fe-check"></i> <span>حفظ وتطبيق التعديل</span>');
                if (res.success) {
                    if (window.toastr) {
                        toastr.success(res.message);
                    } else if (window.Swal) {
                        Swal.fire({ icon: 'success', title: 'تم بنجاح', text: res.message, timer: 1500, showConfirmButton: false });
                    }
                    setTimeout(function() {
                        window.location.reload();
                    }, 800);
                }
            },
            error: function(xhr) {
                submitBtn.prop('disabled', false).html('<i class="fe fe-check"></i> <span>حفظ وتطبيق التعديل</span>');
                var msg = 'حدث خطأ أثناء حفظ التعديل';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                if (window.toastr) {
                    toastr.error(msg);
                } else if (window.Swal) {
                    Swal.fire({ icon: 'error', title: 'خطأ', text: msg });
                } else {
                    alert(msg);
                }
            }
        });
    });

    $(document).on('click', '.btn-delete-adj', function() {
        var adjId = $(this).data('adj-id');
        if (!confirm('هل أنت متأكد من رغبتك في حذف هذا التعديل اليدوي واستعادة الرصيد السابق؟')) {
            return;
        }

        $.ajax({
            url: getAdjustmentsBaseUrl + '/' + adjId,
            method: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(res) {
                if (res.success) {
                    if (window.toastr) {
                        toastr.success(res.message);
                    } else if (window.Swal) {
                        Swal.fire({ icon: 'success', title: 'تم الحذف', text: res.message, timer: 1500, showConfirmButton: false });
                    }
                    setTimeout(function() {
                        window.location.reload();
                    }, 800);
                }
            },
            error: function() {
                alert('فشل في حذف التعديل');
            }
        });
    });
});
</script>
@endpush
@endif
