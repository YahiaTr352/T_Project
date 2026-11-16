@extends('layouts.app')

@section('title', 'تعديل بند / سؤال')

@push('styles')
@include('dashboard.quiz-competitions._styles')
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs4.min.css" rel="stylesheet">

<style>

/* Modern Switch Row for RTL - Left Aligned Switch, Right Aligned Text */
.modern-switch-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    direction: rtl;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 16px 20px;
    margin-bottom: 12px;
    transition: all 0.2s ease-in-out;
}
.modern-switch-row:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 10px rgba(0,0,0,0.03);
}
.modern-switch-info {
    flex: 1;
    text-align: right;
    padding-left: 20px;
}
.modern-switch-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 3px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.modern-switch-desc {
    font-size: 0.82rem;
    color: #64748b;
    line-height: 1.4;
    margin-bottom: 0;
}
.ios-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 28px;
    flex-shrink: 0;
    margin-bottom: 0;
    cursor: pointer;
}
.ios-switch input {
    opacity: 0;
    width: 0;
    height: 0;
    position: absolute;
}
.ios-switch-slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: #cbd5e1;
    transition: .3s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 34px;
}
.ios-switch-slider:before {
    position: absolute;
    content: "";
    height: 22px;
    width: 22px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .3s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 50%;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}
.ios-switch input:checked + .ios-switch-slider {
    background-color: #0d9488;
}
.ios-switch input:checked + .ios-switch-slider:before {
    transform: translateX(22px);
}

/* Vote Mode Switcher Cards */
.vote-mode-card {
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    background: #ffffff;
    cursor: pointer;
    transition: all 0.2s ease-in-out;
}
.vote-mode-card:hover {
    border-color: #0d9488;
    transform: translateY(-2px);
}
.vote-mode-card.is-active {
    border-color: #0d9488 !important;
    background-color: #f0fdfa !important;
    box-shadow: 0 4px 14px rgba(13, 148, 136, 0.12) !important;
}
.vote-mode-card.is-active .vote-mode-icon {
    background-color: #0d9488 !important;
    color: #ffffff !important;
}
.vote-mode-icon {
    transition: all 0.2s ease;
}

/* Sila Columns styling */
.sila-col {
    min-width: 280px;
    max-width: 320px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 14px;
    max-height: 520px;
    overflow-y: auto;
    flex-shrink: 0;
}
.sila-col-title {
    font-weight: 800;
    font-size: 0.95rem;
    color: #0f172a;
    padding-bottom: 10px;
    margin-bottom: 12px;
    border-bottom: 2px solid #f1f5f9;
    text-align: center;
}
.sila-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 12px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
}
.sila-card:hover {
    border-color: #0d9488;
    background: #f0fdf4;
}
.sila-card.active-branch {
    border-color: #0d9488;
    background: #e6fffa;
    box-shadow: 0 0 0 2px rgba(13, 148, 136, 0.2);
}
.sila-card.is-selected {
    border-color: #10b981;
    background: #ecfdf5;
}
.sila-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
    margin-bottom: 6px;
    border: 2px solid #ffffff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.06);
}
.sila-name {
    font-weight: 700;
    font-size: 0.88rem;
    color: #1e293b;
    margin-bottom: 4px;
}
.nominee-chip {
    display: inline-flex;
    align-items: center;
    background: #ffffff;
    border: 1.5px solid #10b981;
    padding: 6px 14px;
    border-radius: 50px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
    gap: 8px;
}
.nominee-chip img {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
}

</style>
@endpush

@section('content')
<div class="container-fluid py-4">
    <!-- Header Section -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <span class="badge badge-light border px-2 py-1 text-secondary mb-2">تحديث البند الحالي</span>
            <h1 class="h2 mb-1 text-gray-900 font-weight-bold">
                <i class="fas fa-edit text-teal mr-2"></i>تعديل بند / سؤال
            </h1>
            <p class="text-muted mb-0">استطلاع: <strong>{{ $quizCompetition->title }}</strong></p>
        </div>
        <a href="{{ route('dashboard.quiz-competitions.show', $quizCompetition) }}" class="modern-btn modern-btn-secondary text-decoration-none">
            <i class="fas fa-arrow-right"></i>
            <span>العودة للاستطلاع</span>
        </a>
    </div>

    <!-- Main Card -->
    <div class="card modern-card">
        <div class="card-body p-4 p-lg-5">
            <form action="{{ route('dashboard.quiz-questions.update', [$quizCompetition, $quizQuestion]) }}" method="POST" id="questionForm" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="row justify-content-center">
                    <div class="col-lg-10">

                        <!-- Question Text -->
                        <div class="form-group mb-4">
                            <label for="question_text" class="modern-label">نص البند / السؤال (اختياري)</label>
                            <textarea class="form-control modern-input @error('question_text') is-invalid @enderror" id="question_text"
                                name="question_text" rows="3" placeholder="اكتب نص السؤال أو العبارة هنا (اختياري)...">{{ old('question_text', $quizQuestion->question_text) }}</textarea>
                            @error('question_text')
                                <div class="invalid-feedback font-weight-bold mt-2">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Description (Summernote) -->
                        <div class="form-group mb-4">
                            <label for="description" class="modern-label">شرح إضافي أو تلميح (اختياري)</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" id="description"
                                name="description" rows="4">{{ old('description', $quizQuestion->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback font-weight-bold mt-2">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Dynamic Prizes Container -->
                        <div class="form-group mb-4 p-4 rounded-lg bg-light" id="prizesContainerGroup" style="border-right: 4px solid #eab308;">
                            <label class="modern-label text-warning mb-2">
                                <i class="fas fa-gift mr-1"></i> جوائز الفائزين (حسب مراكز السحب)
                            </label>
                            <div id="prizesContainer" class="mb-3"></div>
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle mr-1"></i> سيتم توليد حقول الجوائز ديناميكياً بناءً على حقل "عدد الفائزين" في الأسفل.
                            </small>
                        </div>

                        <!-- Answer Type (Pill Selectable) -->
                        <div class="form-group mb-4">
                            <label for="answer_type" class="modern-label">نوع البند / طريقة التصويت والرد <span class="text-danger">*</span></label>
                            <div class="answer-type-container mt-2">
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="type_multiple_choice" name="answer_type"
                                        class="custom-control-input answer-type-radio" value="multiple_choice"
                                        {{ old('answer_type', $quizQuestion->answer_type) == 'multiple_choice' ? 'checked' : '' }} required>
                                    <label class="custom-control-label" for="type_multiple_choice">
                                        <i class="fas fa-list-ul mb-1" style="font-size:1.2rem;"></i>
                                        <span>اختيار من متعدد</span>
                                    </label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="type_ordering" name="answer_type"
                                        class="custom-control-input answer-type-radio" value="ordering"
                                        {{ old('answer_type', $quizQuestion->answer_type) == 'ordering' ? 'checked' : '' }} required>
                                    <label class="custom-control-label" for="type_ordering">
                                        <i class="fas fa-sort-amount-down mb-1" style="font-size:1.2rem;"></i>
                                        <span>ترتيب العناصر</span>
                                    </label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="type_true_false" name="answer_type"
                                        class="custom-control-input answer-type-radio" value="true_false"
                                        {{ old('answer_type', $quizQuestion->answer_type) == 'true_false' ? 'checked' : '' }} required>
                                    <label class="custom-control-label" for="type_true_false">
                                        <i class="fas fa-check-double mb-1" style="font-size:1.2rem;"></i>
                                        <span>صح أم خطأ</span>
                                    </label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="type_custom_text" name="answer_type"
                                        class="custom-control-input answer-type-radio" value="custom_text"
                                        {{ old('answer_type', $quizQuestion->answer_type) == 'custom_text' ? 'checked' : '' }} required>
                                    <label class="custom-control-label" for="type_custom_text">
                                        <i class="fas fa-keyboard mb-1" style="font-size:1.2rem;"></i>
                                        <span>إجابة حرة (نص)</span>
                                    </label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="type_vote" name="answer_type"
                                        class="custom-control-input answer-type-radio" value="vote"
                                        {{ old('answer_type', $quizQuestion->answer_type) == 'vote' ? 'checked' : '' }} required>
                                    <label class="custom-control-label text-primary" for="type_vote">
                                        <i class="fas fa-vote-yea mb-1" style="font-size:1.2rem;"></i>
                                        <span>تصويت للرأي</span>
                                    </label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="type_fill_blank" name="answer_type"
                                        class="custom-control-input answer-type-radio" value="fill_blank"
                                        {{ old('answer_type', $quizQuestion->answer_type) == 'fill_blank' ? 'checked' : '' }} required>
                                    <label class="custom-control-label text-warning" for="type_fill_blank">
                                        <i class="fas fa-pen-nib mb-1" style="font-size:1.2rem;"></i>
                                        <span>أملأ الفراغ</span>
                                    </label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="type_survey" name="answer_type"
                                        class="custom-control-input answer-type-radio" value="survey"
                                        {{ old('answer_type', $quizQuestion->answer_type) == 'survey' ? 'checked' : '' }} required>
                                    <label class="custom-control-label text-success" for="type_survey">
                                        <i class="fas fa-poll mb-1" style="font-size:1.2rem;"></i>
                                        <span>تقييم استبيان</span>
                                    </label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="type_walking" name="answer_type"
                                        class="custom-control-input answer-type-radio" value="walking"
                                        {{ old('answer_type', $quizQuestion->answer_type) == 'walking' ? 'checked' : '' }} required>
                                    <label class="custom-control-label font-weight-bold text-success" for="type_walking">
                                        <i class="fas fa-running mb-1" style="font-size:1.2rem; color: #10b981;"></i>
                                        <span>تحدي المشي والخطوات</span>
                                    </label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="type_walking_prediction" name="answer_type"
                                        class="custom-control-input answer-type-radio" value="walking_prediction"
                                        {{ old('answer_type', $quizQuestion->answer_type) == 'walking_prediction' || $quizQuestion->isLinkedToWalking() ? 'checked' : '' }} required>
                                    <label class="custom-control-label font-weight-bold" style="color: #047857;" for="type_walking_prediction">
                                        <i class="fas fa-trophy mb-1" style="font-size:1.2rem; color: #059669;"></i>
                                        <span>ربط تحدي مشي بتصويت (توقعات) ⭐</span>
                                    </label>
                                </div>
                            </div>
                            @error('answer_type')
                                <div class="invalid-feedback d-block font-weight-bold mt-2">{{ $message }}</div>
                            @enderror
                        </div>

                        @php
    $candRules = $quizQuestion->candidate_rules ?? [];
    $hasTreeNominees = $quizQuestion->choices->contains(fn($c) => !empty($c->person_id));
    $hasTextChoices = $quizQuestion->choices->contains(fn($c) => empty($c->person_id) && !empty($c->choice_text));
    
    if (!empty($candRules['nomination_type'])) {
        $initialNominationType = $candRules['nomination_type'];
        if ($initialNominationType === 'predefined') {
            $initialNominationType = $hasTreeNominees ? 'predefined_tree' : 'custom_options';
        }
    } else {
        if ($hasTreeNominees) {
            $initialNominationType = 'predefined_tree';
        } elseif ($hasTextChoices) {
            $initialNominationType = 'custom_options';
        } else {
            $initialNominationType = 'open_tree';
        }
    }
    
    $candGender = $candRules['allowed_gender'] ?? 'all';
    $candExcludeDeceased = $candRules['exclude_deceased'] ?? true;
    $candRequireWhatsapp = $candRules['require_whatsapp'] ?? false;
    $candWalkingCondition = $candRules['walking_condition'] ?? [];
    $candWalkingEnabled = !empty($candWalkingCondition['enabled']);
    $candWalkingDays = (int) ($candWalkingCondition['days_window'] ?? 3);
    $candWalkingMinSteps = (int) ($candWalkingCondition['min_daily_steps'] ?? 0);
    $candWalkingType = $candWalkingCondition['condition_type'] ?? 'any_day';
    $candScope = $candRules['candidate_scope'] ?? 'all';
    $candExcludeOutside = !empty($candRules['exclude_outside_family']);
    $candExcludeMaternal = !empty($candRules['exclude_maternal_children']);
    $candExcludeBreastfed = !empty($candRules['exclude_breastfed']);
    $candExcludeOriginal = !empty($candRules['exclude_original_family']);
    $eligibility = $quizQuestion->voter_eligibility ?? [];
@endphp

                        <!-- Vote Specific Config -->
                        <div class="form-group mb-4" id="voteOptionsGroup" style="display:none;">

                            <!-- 1. Candidate Nomination Method Selector -->
                            <div class="card p-4 border-0 shadow-sm mb-4" style="border-radius: 20px; background: linear-gradient(to bottom, #f8fafc, #f1f5f9); border: 2px solid #cbd5e1 !important;">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="rounded-circle bg-teal text-white p-2 ml-2 d-flex align-items-center justify-content-center" style="width:36px; height:36px; background-color:#0d9488;">
                                        <i class="fas fa-sliders-h"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 font-weight-bold text-gray-900" style="font-size: 1.05rem;">طريقة ترشيح واختيار المرشحين (Candidate Nomination Method)</h6>
                                        <small class="text-muted">اختر هل يختار المشاركون مرشحهم بأنفسهم من شجرة العائلة، أم تُحدد الإدارة قائمة أسماء مسبقة؟</small>
                                    </div>
                                </div>

                                <div class="row">
                                    <!-- Option 1: Open Tree Voting (Voters pick from family tree) -->
                                    <div class="col-md-4 mb-2">
                                        <div class="card p-3 vote-mode-card {{ $initialNominationType === 'open_tree' ? 'is-active' : '' }} shadow-sm" id="voteModeCardOpenTree" onclick="setVoteNominationMode('open_tree')">
                                            <div class="d-flex align-items-center">
                                                <div class="vote-mode-icon rounded-circle {{ $initialNominationType === 'open_tree' ? 'bg-teal text-white' : 'bg-light text-success' }} p-2 ml-3" style="font-size: 1.25rem; width:46px; height:46px; display:flex; align-items:center; justify-content:center;">
                                                    <i class="fas fa-sitemap"></i>
                                                </div>
                                                <div>
                                                    <h6 class="font-weight-bold mb-1 text-dark">1. ترشيح حر من الشجرة ⭐</h6>
                                                    <small class="text-muted d-block" style="font-size:0.8rem;">المشاركون يفتحون الشجرة ويختارون مرشحهم وفق الضوابط.</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Option 2: Predefined Tree Candidates (Admin picks from tree) -->
                                    <div class="col-md-4 mb-2">
                                        <div class="card p-3 vote-mode-card {{ $initialNominationType === 'predefined_tree' ? 'is-active' : '' }} shadow-sm" id="voteModeCardPredefinedTree" onclick="setVoteNominationMode('predefined_tree')">
                                            <div class="d-flex align-items-center">
                                                <div class="vote-mode-icon rounded-circle {{ $initialNominationType === 'predefined_tree' ? 'bg-teal text-white' : 'bg-light text-primary' }} p-2 ml-3" style="font-size: 1.25rem; width:46px; height:46px; display:flex; align-items:center; justify-content:center;">
                                                    <i class="fas fa-users-cog"></i>
                                                </div>
                                                <div>
                                                    <h6 class="font-weight-bold mb-1 text-dark">2. مرشحون محددون من الشجرة 🌳</h6>
                                                    <small class="text-muted d-block" style="font-size:0.8rem;">الإدارة تختار أسماء معينة من الشجرة فقط ليتنافسوا.</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Option 3: Custom Text Options (Admin enters text choices) -->
                                    <div class="col-md-4 mb-2">
                                        <div class="card p-3 vote-mode-card {{ $initialNominationType === 'custom_options' ? 'is-active' : '' }} shadow-sm" id="voteModeCardCustomOptions" onclick="setVoteNominationMode('custom_options')">
                                            <div class="d-flex align-items-center">
                                                <div class="vote-mode-icon rounded-circle {{ $initialNominationType === 'custom_options' ? 'bg-teal text-white' : 'bg-light text-warning' }} p-2 ml-3" style="font-size: 1.25rem; width:46px; height:46px; display:flex; align-items:center; justify-content:center;">
                                                    <i class="fas fa-list-ol"></i>
                                                </div>
                                                <div>
                                                    <h6 class="font-weight-bold mb-1 text-dark">3. خيارات تصويت مخصصة 📝</h6>
                                                    <small class="text-muted d-block" style="font-size:0.8rem;">تصويت عام بخيارات وبنود نصية يدخلها المشرف.</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="candidate_rules[nomination_type]" id="candidate_nomination_type" value="{{ old('candidate_rules.nomination_type', $initialNominationType) }}">
                                <input type="hidden" name="vote_mode" id="vote_mode_input" value="{{ old('candidate_rules.nomination_type', $initialNominationType) }}">
                            </div>

                            <!-- 2. Candidate Scope & Eligibility (Shown for Open Tree Voting) -->
                            <div id="candidateRulesCard" class="card border-teal p-4 mb-4 shadow-sm" style="border-radius:20px; background-color:#f0fdf4; border: 2px solid #37a05c; {{ $initialNominationType === 'open_tree' ? '' : 'display:none;' }}">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="rounded-circle bg-success text-white p-2 ml-3 d-flex align-items-center justify-content-center" style="width:38px; height:38px; background-color:#10b981;">
                                        <i class="fas fa-user-check"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 font-weight-bold text-gray-900" style="font-size: 1.05rem;">ضوابط وشروط المرشحين من شجرة العائلة (Candidate Eligibility)</h6>
                                        <small class="text-muted">حدد الفئات التي يسمح للمشاركين باختيارها وترشيحها من شجرة العائلة</small>
                                    </div>
                                </div>

                                <div class="row">
                                    <!-- Allowed Gender -->
                                    <div class="col-md-12 mb-3">
                                        <label class="font-weight-bold text-dark mb-2 d-block" style="font-size: 0.95rem;">
                                            <i class="fas fa-venus-mars text-success ml-1"></i> جنس المرشحين المتاح التصويت لهم:
                                        </label>
                                        <div class="d-flex flex-wrap" style="gap: 12px;">
                                            <div class="custom-control custom-radio custom-control-inline bg-white px-3 py-2 border rounded-lg shadow-sm">
                                                <input type="radio" id="cand_gender_all" name="candidate_rules[allowed_gender]" value="all" class="custom-control-input"
                                                    {{ old('candidate_rules.allowed_gender', $candGender) === 'all' ? 'checked' : '' }}>
                                                <label class="custom-control-label font-weight-bold text-dark cursor-pointer" for="cand_gender_all">
                                                    الجميع (رجال ونساء العائلة) 🌳
                                                </label>
                                            </div>
                                            <div class="custom-control custom-radio custom-control-inline bg-white px-3 py-2 border rounded-lg shadow-sm">
                                                <input type="radio" id="cand_gender_male" name="candidate_rules[allowed_gender]" value="male" class="custom-control-input"
                                                    {{ old('candidate_rules.allowed_gender', $candGender) === 'male' ? 'checked' : '' }}>
                                                <label class="custom-control-label font-weight-bold text-primary cursor-pointer" for="cand_gender_male">
                                                    <i class="fas fa-male mr-1"></i> رجال العائلة فقط (Men Only)
                                                </label>
                                            </div>
                                            <div class="custom-control custom-radio custom-control-inline bg-white px-3 py-2 border rounded-lg shadow-sm">
                                                <input type="radio" id="cand_gender_female" name="candidate_rules[allowed_gender]" value="female" class="custom-control-input"
                                                    {{ old('candidate_rules.allowed_gender', $candGender) === 'female' ? 'checked' : '' }}>
                                                <label class="custom-control-label font-weight-bold text-danger cursor-pointer" for="cand_gender_female">
                                                    <i class="fas fa-female mr-1"></i> نساء العائلة فقط (Women Only)
                                                </label>
                                            </div>
                                        </div>
                                        <small class="text-muted d-block mt-2">
                                            <i class="fas fa-info-circle mr-1 text-teal"></i> عند تحديد (رجال فقط) أو (نساء فقط)، سيقوم تطبيق الجوال تلقائياً بإخفاء أو منع اختيار الفئة الأخرى أثناء تصفح الشجرة.
                                        </small>
                                    </div>

                                    <!-- Candidate Target Scope Presets -->
                                    <div class="col-md-12 mb-3">
                                        <label class="font-weight-bold text-dark mb-2 d-block" style="font-size: 0.95rem;">
                                            <i class="fas fa-layer-group text-teal ml-1"></i> النطاق العائلي للمرشحين المسموح ترشيحهم:
                                        </label>
                                        @php
                                            $activeScope = old('candidate_rules.candidate_scope', $candScope);
                                        @endphp
                                        <div class="d-flex flex-wrap" style="gap: 8px;" id="candScopePresets">
                                            <button type="button" class="btn btn-sm cand-scope-btn font-weight-bold {{ $activeScope === 'all' ? 'btn-success text-white' : 'btn-outline-secondary' }}" data-scope="all" style="border-radius: 10px; padding: 7px 14px;">
                                                <i class="fas fa-globe ml-1"></i> الجميع (شامل الكل) 🌐
                                            </button>
                                            <button type="button" class="btn btn-sm cand-scope-btn font-weight-bold {{ $activeScope === 'family_and_ansab' ? 'btn-success text-white' : 'btn-outline-secondary' }}" data-scope="family_and_ansab" style="border-radius: 10px; padding: 7px 14px;">
                                                <i class="fas fa-users ml-1"></i> العائلة والأنساب (أبناء النساء) 🌸
                                            </button>
                                            <button type="button" class="btn btn-sm cand-scope-btn font-weight-bold {{ $activeScope === 'original_family_only' ? 'btn-success text-white' : 'btn-outline-secondary' }}" data-scope="original_family_only" style="border-radius: 10px; padding: 7px 14px;">
                                                <i class="fas fa-tree ml-1"></i> سلالة العائلة الأساسية فقط (العصب) 🌳
                                            </button>
                                            <button type="button" class="btn btn-sm cand-scope-btn font-weight-bold {{ $activeScope === 'maternal_only' ? 'btn-success text-white' : 'btn-outline-secondary' }}" data-scope="maternal_only" style="border-radius: 10px; padding: 7px 14px;">
                                                <i class="fas fa-female ml-1"></i> أبناء نساء العائلة فقط (الأنساب حصراً) 🌸
                                            </button>
                                            <button type="button" class="btn btn-sm cand-scope-btn font-weight-bold {{ $activeScope === 'breastfed_only' ? 'btn-success text-white' : 'btn-outline-secondary' }}" data-scope="breastfed_only" style="border-radius: 10px; padding: 7px 14px;">
                                                <i class="fas fa-baby ml-1"></i> أبناء الرضاعة فقط 🍼
                                            </button>
                                            <button type="button" class="btn btn-sm cand-scope-btn font-weight-bold {{ $activeScope === 'custom' ? 'btn-success text-white' : 'btn-outline-secondary' }}" data-scope="custom" style="border-radius: 10px; padding: 7px 14px;">
                                                <i class="fas fa-sliders-h ml-1"></i> تخصيص يدوي حر ⚙️
                                            </button>
                                        </div>
                                        <input type="hidden" name="candidate_rules[candidate_scope]" id="cand_scope_input" value="{{ $activeScope }}">
                                        <small class="text-muted d-block mt-2">
                                            <i class="fas fa-info-circle mr-1 text-teal"></i> انقر على أي فئة لضبط مفاتيح الاستبعاد أدناه تلقائياً، أو يمكنك تعديل أي مفتاح يدوياً حسب الرغبة.
                                        </small>
                                    </div>

                                    <!-- Switch: Exclude Outside Family -->
                                    <div class="col-12">
                                        <div class="modern-switch-row">
                                            <div class="modern-switch-info">
                                                <div class="modern-switch-title text-dark">
                                                    <i class="fas fa-user-times text-warning"></i>
                                                    استبعاد من هم من خارج العائلة (الأصهار والأزواج)
                                                </div>
                                                <p class="modern-switch-desc">
                                                    استبعاد الأزواج والزوجات أو من هم مسجلون من خارج العائلة تماماً وليس لهم صلة نسب أو رضاعة.
                                                </p>
                                            </div>
                                            <label class="ios-switch">
                                                <input type="hidden" name="candidate_rules[exclude_outside_family]" value="0">
                                                <input type="checkbox" id="cand_exclude_outside_family" name="candidate_rules[exclude_outside_family]" value="1"
                                                    {{ old('candidate_rules.exclude_outside_family', $candExcludeOutside ? '1' : '0') == '1' ? 'checked' : '' }}>
                                                <span class="ios-switch-slider"></span>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Switch: Exclude Maternal Children (Ansab) -->
                                    <div class="col-12">
                                        <div class="modern-switch-row">
                                            <div class="modern-switch-info">
                                                <div class="modern-switch-title text-dark">
                                                    <i class="fas fa-female text-info"></i>
                                                    استبعاد الأبناء من الأنساب (أبناء نساء العائلة من خارجها)
                                                </div>
                                                <p class="modern-switch-desc">
                                                    استبعاد أبناء وبنات نساء العائلة اللاتي تزوجن من خارج العائلة (أمهاتهم من العائلة وآباؤهم من خارجها).
                                                </p>
                                            </div>
                                            <label class="ios-switch">
                                                <input type="hidden" name="candidate_rules[exclude_maternal_children]" value="0">
                                                <input type="checkbox" id="cand_exclude_maternal_children" name="candidate_rules[exclude_maternal_children]" value="1"
                                                    {{ old('candidate_rules.exclude_maternal_children', $candExcludeMaternal ? '1' : '0') == '1' ? 'checked' : '' }}>
                                                <span class="ios-switch-slider"></span>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Switch: Exclude Breastfed Children -->
                                    <div class="col-12">
                                        <div class="modern-switch-row">
                                            <div class="modern-switch-info">
                                                <div class="modern-switch-title text-dark">
                                                    <i class="fas fa-baby text-primary"></i>
                                                    استبعاد الأبناء من الرضاعة (القرابة بالرضاعة فقط)
                                                </div>
                                                <p class="modern-switch-desc">
                                                    استبعاد الأشخاص الذين أرضعتهم إحدى نساء العائلة ولا ينتمون للعائلة بيولوجياً (لا أب ولا أم من العائلة).
                                                </p>
                                            </div>
                                            <label class="ios-switch">
                                                <input type="hidden" name="candidate_rules[exclude_breastfed]" value="0">
                                                <input type="checkbox" id="cand_exclude_breastfed" name="candidate_rules[exclude_breastfed]" value="1"
                                                    {{ old('candidate_rules.exclude_breastfed', $candExcludeBreastfed ? '1' : '0') == '1' ? 'checked' : '' }}>
                                                <span class="ios-switch-slider"></span>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Switch: Exclude Original Family Lineage -->
                                    <div class="col-12">
                                        <div class="modern-switch-row">
                                            <div class="modern-switch-info">
                                                <div class="modern-switch-title text-dark">
                                                    <i class="fas fa-tree text-success"></i>
                                                    استبعاد سلالة العائلة الأساسية (العصب المباشر)
                                                </div>
                                                <p class="modern-switch-desc">
                                                    استبعاد أفراد العائلة الأصليين ذوي النسب المباشر (يُستخدم فقط إذا رغبت بحصر المسابقة لفئة الأنساب أو الرضاعة فقط).
                                                </p>
                                            </div>
                                            <label class="ios-switch">
                                                <input type="hidden" name="candidate_rules[exclude_original_family]" value="0">
                                                <input type="checkbox" id="cand_exclude_original_family" name="candidate_rules[exclude_original_family]" value="1"
                                                    {{ old('candidate_rules.exclude_original_family', $candExcludeOriginal ? '1' : '0') == '1' ? 'checked' : '' }}>
                                                <span class="ios-switch-slider"></span>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Switch: Exclude Deceased -->
                                    <div class="col-12">
                                        <div class="modern-switch-row">
                                            <div class="modern-switch-info">
                                                <div class="modern-switch-title text-dark">
                                                    <i class="fas fa-heartbeat text-danger"></i>
                                                    استبعاد المتوفين من قائمة المرشحين المتاحة للتصويت
                                                </div>
                                                <p class="modern-switch-desc">
                                                    حصر إمكانية التصويت على الأحياء فقط ومنع اختيار الأشخاص المتوفين المسجلين في الشجرة.
                                                </p>
                                            </div>
                                            <label class="ios-switch">
                                                <input type="hidden" name="candidate_rules[exclude_deceased]" value="0">
                                                <input type="checkbox" id="cand_exclude_deceased" name="candidate_rules[exclude_deceased]" value="1"
                                                    {{ old('candidate_rules.exclude_deceased', $candExcludeDeceased ? '1' : '0') == '1' ? 'checked' : '' }}>
                                                <span class="ios-switch-slider"></span>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Switch: Require WhatsApp for Candidate -->
                                    <div class="col-12">
                                        <div class="modern-switch-row">
                                            <div class="modern-switch-info">
                                                <div class="modern-switch-title text-dark">
                                                    <i class="fab fa-whatsapp text-success"></i>
                                                    اشتراط وجود رقم واتساب مسجل للمرشح (اختياري)
                                                </div>
                                                <p class="modern-switch-desc">
                                                    يمنع ترشيح أي شخص لا يملك حساب واتساب موثق في دليل العائلة لضمان سهولة التواصل مع الفائز.
                                                </p>
                                            </div>
                                            <label class="ios-switch">
                                                <input type="hidden" name="candidate_rules[require_whatsapp]" value="0">
                                                <input type="checkbox" id="cand_require_whatsapp" name="candidate_rules[require_whatsapp]" value="1"
                                                    {{ old('candidate_rules.require_whatsapp', $candRequireWhatsapp) ? 'checked' : '' }}>
                                                <span class="ios-switch-slider"></span>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Switch: Walking Program Activity Requirement -->
                                    <div class="col-12">
                                        <div class="modern-switch-row">
                                            <div class="modern-switch-info">
                                                <div class="modern-switch-title text-dark">
                                                    <i class="fas fa-walking text-primary"></i>
                                                    اشتراط تسجيل نشاط في برنامج المشي للمرشحين
                                                </div>
                                                <p class="modern-switch-desc">
                                                    ربط أهلية المرشح ببرنامج المشي، بحيث لا يُتاح ترشيح أي شخص إلا إذا كان قد سجل نشاط مشي وخطوات خلال الفترة المحددة (مثل آخر يومين، 3 أيام، 4 أيام، إلخ).
                                                </p>
                                            </div>
                                            <label class="ios-switch">
                                                <input type="hidden" name="candidate_rules[walking_condition][enabled]" value="0">
                                                <input type="checkbox" id="cand_walking_enabled" name="candidate_rules[walking_condition][enabled]" value="1"
                                                    {{ old('candidate_rules.walking_condition.enabled', $candWalkingEnabled ? '1' : '0') == '1' ? 'checked' : '' }}>
                                                <span class="ios-switch-slider"></span>
                                            </label>
                                        </div>

                                        <!-- Collapsible Sub-settings for Walking Condition -->
                                        <div id="walkingConditionConfigBox" class="p-3 mb-3 border rounded-lg shadow-sm" style="background-color: #f8fafc; border: 2px dashed #0d9488 !important; {{ old('candidate_rules.walking_condition.enabled', $candWalkingEnabled ? '1' : '0') == '1' ? '' : 'display: none;' }}">
                                            <div class="row">
                                                <!-- Days Window Selection -->
                                                <div class="col-lg-6 mb-3">
                                                    <label class="font-weight-bold text-dark d-block mb-2" style="font-size: 0.95rem;">
                                                        <i class="fas fa-calendar-alt text-teal ml-1"></i>
                                                        الفترة المطلوبة (الأيام الأخيرة للمشي):
                                                    </label>
                                                    @php
                                                        $currentDays = (int) old('candidate_rules.walking_condition.days_window', $candWalkingDays);
                                                    @endphp
                                                    <div class="btn-group btn-group-toggle d-flex flex-wrap mb-2" role="group" style="gap: 6px;">
                                                        <button type="button" class="btn btn-sm walking-preset-btn font-weight-bold {{ $currentDays == 2 ? 'btn-success text-white' : 'btn-outline-secondary' }}" data-days="2" style="border-radius: 8px;">
                                                            آخر يومين (2)
                                                        </button>
                                                        <button type="button" class="btn btn-sm walking-preset-btn font-weight-bold {{ $currentDays == 3 ? 'btn-success text-white' : 'btn-outline-secondary' }}" data-days="3" style="border-radius: 8px;">
                                                            آخر 3 أيام (3)
                                                        </button>
                                                        <button type="button" class="btn btn-sm walking-preset-btn font-weight-bold {{ $currentDays == 4 ? 'btn-success text-white' : 'btn-outline-secondary' }}" data-days="4" style="border-radius: 8px;">
                                                            آخر 4 أيام (4)
                                                        </button>
                                                        <button type="button" class="btn btn-sm walking-preset-btn font-weight-bold {{ $currentDays == 7 ? 'btn-success text-white' : 'btn-outline-secondary' }}" data-days="7" style="border-radius: 8px;">
                                                            آخر أسبوع (7)
                                                        </button>
                                                    </div>
                                                    <div class="form-group mb-0">
                                                        <div class="input-group" style="max-width: 250px;">
                                                            <input type="number" id="cand_walking_days" name="candidate_rules[walking_condition][days_window]"
                                                                class="form-control form-control-lg font-weight-bold text-center bg-white" value="{{ $currentDays }}" min="1" max="365"
                                                                placeholder="3" style="border-radius: 0 10px 10px 0; font-size: 1.15rem;" required>
                                                            <div class="input-group-append">
                                                                <span class="input-group-text font-weight-bold bg-light" style="border-radius: 10px 0 0 10px;">أيام</span>
                                                            </div>
                                                        </div>
                                                        <small class="text-muted d-block mt-1" style="font-size: 0.8rem;">
                                                            <i class="fas fa-info-circle ml-1 text-teal"></i> انقر على أي زر سريع أعلاه أو اكتب عدد الأيام مباشرة (مخصص).
                                                        </small>
                                                    </div>
                                                </div>

                                                <!-- Condition Type (Any day vs All days) -->
                                                <div class="col-lg-6 mb-3">
                                                    <label class="font-weight-bold text-dark d-block mb-2" style="font-size: 0.95rem;">
                                                        <i class="fas fa-tasks text-teal ml-1"></i>
                                                        طبيعة شرط النشاط:
                                                    </label>
                                                    @php
                                                        $condType = old('candidate_rules.walking_condition.condition_type', $candWalkingType);
                                                    @endphp
                                                    <div class="d-flex flex-column" style="gap: 8px;">
                                                        <div class="custom-control custom-radio bg-white p-2 px-3 border rounded-lg shadow-sm">
                                                            <input type="radio" id="cand_walking_type_any" name="candidate_rules[walking_condition][condition_type]" value="any_day" class="custom-control-input"
                                                                {{ $condType !== 'all_days' ? 'checked' : '' }}>
                                                            <label class="custom-control-label font-weight-bold text-dark cursor-pointer" for="cand_walking_type_any">
                                                                مشى في أي يوم خلال الفترة (يوم واحد على الأقل) 👟
                                                            </label>
                                                        </div>
                                                        <div class="custom-control custom-radio bg-white p-2 px-3 border rounded-lg shadow-sm">
                                                            <input type="radio" id="cand_walking_type_all" name="candidate_rules[walking_condition][condition_type]" value="all_days" class="custom-control-input"
                                                                {{ $condType === 'all_days' ? 'checked' : '' }}>
                                                            <label class="custom-control-label font-weight-bold text-dark cursor-pointer" for="cand_walking_type_all">
                                                                مشى في جميع أيام الفترة (نشاط متواصل بلا انقطاع) 🔥
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Min Daily Steps (Optional) -->
                                                <div class="col-lg-12 mb-1">
                                                    <label class="font-weight-bold text-dark mb-1" for="cand_walking_min_steps" style="font-size: 0.95rem;">
                                                        <i class="fas fa-shoe-prints text-warning ml-1"></i>
                                                        الحد الأدنى للخطوات اليومية المطلوبة (اختياري):
                                                    </label>
                                                    <div class="input-group" style="max-width: 280px;">
                                                        <input type="number" id="cand_walking_min_steps" name="candidate_rules[walking_condition][min_daily_steps]"
                                                            class="form-control form-control-lg font-weight-bold text-center bg-white"
                                                            value="{{ old('candidate_rules.walking_condition.min_daily_steps', $candWalkingMinSteps) }}" min="0" step="100"
                                                            placeholder="0" style="border-radius: 0 10px 10px 0;">
                                                        <div class="input-group-append">
                                                            <span class="input-group-text font-weight-bold bg-light" style="border-radius: 10px 0 0 10px;">خطوة / يوم</span>
                                                        </div>
                                                    </div>
                                                    <small class="text-muted d-block mt-1" style="font-size: 0.8rem;">
                                                        <i class="fas fa-info-circle mr-1 text-teal"></i> اتركه 0 لاحتساب أي نشاط مشي مسجل، أو حدد مثلاً 3,000 خطوة كحد أدنى يومي.
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 3. Predefined Candidates Section (Shown ONLY when mode == 'predefined') -->
                            <div id="treeNomineesCard" class="card border-primary p-4 mb-4 shadow-sm" style="border-radius:20px; background-color:#eff6ff; border: 2px solid #3b82f6; {{ $initialNominationType === 'predefined_tree' ? '' : 'display:none;' }}">
                                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap" style="gap: 12px;">
                                    <div>
                                        <h6 class="font-weight-bold text-primary mb-1" style="font-size: 1.15rem;">
                                            <i class="fas fa-users-cog mr-1"></i> تحديد قائمة المرشحين يدوياً من قِبل الإدارة
                                        </h6>
                                        <small class="text-muted">اختر المرشحين من شجرة العائلة أو استخدم الخيارات بالأسفل لتحديد الأسماء المتاحة للمصوتين.</small>
                                    </div>
                                    <button type="button" class="btn btn-primary shadow-sm font-weight-bold px-4 py-2" id="btnOpenSilaTree" style="border-radius:12px; font-size:0.95rem;">
                                        <i class="fas fa-user-plus mr-1"></i> اختيار مرشحين من شجرة العائلة (صلة) 🌳
                                    </button>
                                </div>

                                <!-- Selected Tree Nominees Container -->
                                <div id="treeNomineesSection" class="mt-2">
                                    <label class="small font-weight-bold text-gray-800 mb-2 d-block">
                                        <i class="fas fa-users text-primary mr-1"></i> المرشحون المعتمدون للتصويت:
                                    </label>
                                    <div id="treeNomineesList" class="d-flex flex-wrap" style="gap: 12px;">
                                        <!-- Nominee chips dynamically rendered here -->
                                    </div>
                                    <div id="treeNomineesEmpty" class="text-muted small p-4 bg-white rounded border text-center font-weight-bold" style="border-radius: 14px !important; border-style: dashed !important;">
                                        <i class="fas fa-users fa-2x text-muted mb-2 d-block"></i>
                                        لم يتم إضافة مرشحين بعد. يمكنك النقر على زر <strong>اختيار مرشحين من شجرة العائلة</strong> أو إدخال خيارات نصية.
                                    </div>
                                </div>
                            </div>

                            <!-- 4. Max Selections (Applies to both Open Tree & Predefined) -->
                            <div class="card p-3 border-0 bg-white shadow-sm mb-4" style="border-radius: 16px;">
                                <div class="row align-items-center">
                                    <div class="col-md-7 mb-2 mb-md-0">
                                        <label for="vote_max_selections" class="font-weight-bold text-gray-900 mb-1 d-block" style="font-size: 0.95rem;">
                                            <i class="fas fa-check-double text-teal mr-1"></i> عدد المرشحين المطلوب اختيارهم إجبارياً للمصوت الواحد
                                        </label>
                                        <small class="text-muted">حدد عدد المرشحين الإلزامي للمصوت (مثال: إذا حددت 5، يُجبر المصوت على اختيار 5 مرشحين بالضبط لإتمام تصويته ولا يمكنه إرسال التصويت بأقل من ذلك أو أكثر).</small>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="input-group">
                                            <input type="number" class="form-control modern-input font-weight-bold @error('vote_max_selections') is-invalid @enderror"
                                                id="vote_max_selections" name="vote_max_selections"
                                                value="{{ old('vote_max_selections', $quizQuestion->vote_max_selections ?? 1) }}" min="1" max="50" style="font-size: 1.1rem; text-align: center;">
                                            <div class="input-group-append">
                                                <span class="input-group-text bg-light font-weight-bold">مرشح</span>
                                            </div>
                                        </div>
                                        @error('vote_max_selections')
                                            <div class="invalid-feedback font-weight-bold mt-1 d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <!-- 5. Vote Edit Duration Settings -->
                            <div class="card p-4 border-0 bg-white shadow-sm mb-4" style="border-radius: 16px; border-right: 5px solid #3b82f6 !important;">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="rounded-circle text-white p-2 ml-3 d-flex align-items-center justify-content-center" style="width:38px; height:38px; background-color:#3b82f6;">
                                        <i class="fas fa-history"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 font-weight-bold text-gray-900" style="font-size: 1.05rem;">مهلة وإمكانية تعديل التصويت (Vote Edit Window)</h6>
                                        <small class="text-muted">تحكم في صلاحية ومهلة تعديل المصوت لاختياراته بعد تسجيل تصويته أول مرة</small>
                                    </div>
                                </div>

                                <div class="vote-duration-presets d-flex flex-wrap mb-2" style="gap: 8px;">
                                    <button type="button" class="btn btn-sm font-weight-bold vote-duration-btn" data-val="-1" onclick="setVoteDuration(-1)" style="border-radius: 10px; padding: 7px 14px;">
                                        <i class="fas fa-infinity ml-1 text-primary"></i> مفتوح في أي وقت ⏱️
                                    </button>
                                    <button type="button" class="btn btn-sm font-weight-bold vote-duration-btn" data-val="1" onclick="setVoteDuration(1)" style="border-radius: 10px; padding: 7px 14px;">
                                        <i class="fas fa-bolt ml-1 text-warning"></i> ساعة واحدة (1h)
                                    </button>
                                    <button type="button" class="btn btn-sm font-weight-bold vote-duration-btn" data-val="6" onclick="setVoteDuration(6)" style="border-radius: 10px; padding: 7px 14px;">
                                        <i class="fas fa-clock ml-1 text-info"></i> 6 ساعات (6h)
                                    </button>
                                    <button type="button" class="btn btn-sm font-weight-bold vote-duration-btn" data-val="12" onclick="setVoteDuration(12)" style="border-radius: 10px; padding: 7px 14px;">
                                        <i class="fas fa-sun ml-1 text-warning"></i> 12 ساعة
                                    </button>
                                    <button type="button" class="btn btn-sm font-weight-bold vote-duration-btn" data-val="24" onclick="setVoteDuration(24)" style="border-radius: 10px; padding: 7px 14px;">
                                        <i class="fas fa-star ml-1 text-amber-500"></i> 24 ساعة (يوم كامل - الافتراضي) ⭐
                                    </button>
                                    <button type="button" class="btn btn-sm font-weight-bold vote-duration-btn" data-val="48" onclick="setVoteDuration(48)" style="border-radius: 10px; padding: 7px 14px;">
                                        <i class="fas fa-calendar-day ml-1 text-secondary"></i> 48 ساعة (يومان)
                                    </button>
                                    <button type="button" class="btn btn-sm font-weight-bold vote-duration-btn" data-val="0" onclick="setVoteDuration(0)" style="border-radius: 10px; padding: 7px 14px;">
                                        <i class="fas fa-lock ml-1 text-danger"></i> غير مسموح بالتعديل نهائياً 🔒
                                    </button>
                                    <button type="button" class="btn btn-sm font-weight-bold vote-duration-btn" data-val="custom" onclick="setVoteDuration('custom')" style="border-radius: 10px; padding: 7px 14px;">
                                        <i class="fas fa-sliders-h ml-1 text-purple"></i> تخصيص يدوي ⚙️
                                    </button>
                                </div>

                                <input type="hidden" name="vote_edit_duration_hours" id="vote_edit_duration_hours" value="{{ old('vote_edit_duration_hours', $quizQuestion->vote_edit_duration_hours ?? 24) }}">

                                <div id="customDurationBox" class="p-3 bg-light rounded-lg border mt-2 mb-2" style="display: none; border-radius: 12px;">
                                    <div class="row align-items-center">
                                        <div class="col-md-7 mb-2 mb-md-0">
                                            <label for="custom_duration_input" class="font-weight-bold text-dark mb-0" style="font-size: 0.9rem;">
                                                <i class="fas fa-hourglass-half text-primary mr-1"></i> عدد الساعات المخصصة:
                                            </label>
                                            <small class="text-muted d-block">أدخل عدد الساعات المسموح بالتعديل خلالها (مثال: 3، 36، 72...)</small>
                                        </div>
                                        <div class="col-md-5">
                                            <div class="input-group">
                                                <input type="number" id="custom_duration_input" class="form-control font-weight-bold text-center" min="1" max="8760" value="24" oninput="onCustomDurationChange(this.value)">
                                                <div class="input-group-append">
                                                    <span class="input-group-text bg-white font-weight-bold">ساعة</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div id="durationDescriptionNote" class="mt-2 text-muted" style="font-size: 0.85rem;">
                                    <!-- Dynamic text updated via JS -->
                                </div>
                                @error('vote_edit_duration_hours')
                                    <div class="invalid-feedback font-weight-bold mt-1 d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- 5.5. Walking Prediction Linking Settings -->
                            <div class="card p-4 border-0 bg-white shadow-sm mb-4" style="border-radius: 16px; border-right: 5px solid #10b981 !important;">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle text-white p-2 ml-3 d-flex align-items-center justify-content-center" style="width:38px; height:38px; background-color:#10b981;">
                                            <i class="fas fa-person-walking"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0 font-weight-bold text-gray-900" style="font-size: 1.05rem;">ربط بنتيجة تحدي مشي (مسابقة توقعات المراكز)</h6>
                                            <small class="text-muted">تحويل هذا التصويت إلى مسابقة توقعات لمراكز تحدي مشي، ومنح النقاط لمن يُصيب الترتيب الصحيح</small>
                                        </div>
                                    </div>
                                    <label class="ios-switch mb-0">
                                        <input type="checkbox" id="enable_walking_prediction_toggle" onchange="toggleWalkingPredictionBox(this.checked)"
                                            {{ old('linked_walking_question_id', $quizQuestion->linked_walking_question_id) ? 'checked' : '' }}>
                                        <span class="ios-switch-slider"></span>
                                    </label>
                                </div>

                                <div id="walkingPredictionSettingsBox" style="{{ old('linked_walking_question_id', $quizQuestion->linked_walking_question_id) ? 'display: block;' : 'display: none;' }}">
                                    <div class="alert alert-light border border-emerald-200 text-dark mb-3" style="border-radius: 12px; background-color: #ecfdf5;">
                                        <i class="fas fa-info-circle text-success ml-1"></i>
                                        <strong>آلية المسابقة:</strong> يقوم المصوت باختيار الأوائل بالترتيب (الأول، الثاني...). أثناء التحدي يظهر ترتيب حي للمتوقعين مع <strong>النقاط المتوقعة ⭐</strong> على منصة تتويج خماسية وجدول منافسين، وعند انتهاء التحدي واعتماد فائزيه تتحول إلى نقاط فعلية معتمدة. ترشيحات كل مصوت تظل سرية ولا تظهر للعامة.
                                    </div>

                                    <div class="form-group mb-3">
                                        <label for="linked_walking_question_id" class="font-weight-bold text-gray-900">
                                            <i class="fas fa-link text-emerald-600 mr-1"></i> اختر تحدي المشي المرتبط:
                                        </label>
                                        <select name="linked_walking_question_id" id="linked_walking_question_id" class="form-control select2" style="width: 100%;">
                                            <option value="">-- اختر تحدي المشي المستهدف للمطابقة --</option>
                                            @if(isset($walkingQuestions))
                                                @foreach($walkingQuestions as $wq)
                                                    <option value="{{ $wq->id }}" data-gender="{{ $wq->getWalkingTargetGender() }}" {{ old('linked_walking_question_id', $quizQuestion->linked_walking_question_id) == $wq->id ? 'selected' : '' }}>
                                                        {{ $wq->competition ? '[' . $wq->competition->title . '] ' : '' }}{{ $wq->question_text }} (المراكز: {{ $wq->winners_count }}) [{{ $wq->getWalkingTargetGender() === 'female' ? 'نساء فقط' : ($wq->getWalkingTargetGender() === 'male' ? 'رجال فقط' : 'رجال ونساء') }}]
                                                    </option>
                                                @endforeach
                                            @endif
                                        </select>
                                        @error('linked_walking_question_id')
                                            <div class="invalid-feedback font-weight-bold mt-1 d-block">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="form-group mb-3">
                                        <label for="source_vote_question_id" class="font-weight-bold text-gray-900">
                                            <i class="fas fa-poll text-blue-600 mr-1"></i> مصدر الترشيحات (اختياري): استيراد من مسابقة تصويت سابقة
                                        </label>
                                        <small class="text-muted d-block mb-2">إذا كنت تريد استخدام ترشيحات من مسابقة تصويت سابقة بدلاً من جمع تصويتات جديدة، اختر مسابقة التصويت المصدر هنا.</small>
                                        <select name="source_vote_question_id" id="source_vote_question_id" class="form-control select2" style="width: 100%;">
                                            <option value="">-- لا / جمع ترشيحات جديدة في هذا السؤال --</option>
                                            @if(isset($voteQuestions))
                                                @foreach($voteQuestions as $vq)
                                                    <option value="{{ $vq->id }}" {{ old('source_vote_question_id', $quizQuestion->source_vote_question_id ?? '') == $vq->id ? 'selected' : '' }}>
                                                        {{ $vq->competition ? '[' . $vq->competition->title . '] ' : '' }}{{ $vq->question_text }} ({{ $vq->answers()->count() }} مصوت)
                                                    </option>
                                                @endforeach
                                            @endif
                                        </select>
                                        @error('source_vote_question_id')
                                            <div class="invalid-feedback font-weight-bold mt-1 d-block">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="mt-3">
                                        <label class="font-weight-bold text-gray-900 mb-2 d-block">
                                            <i class="fas fa-award text-amber-500 mr-1"></i> نقاط المطابقة لكل مركز متوقع:
                                        </label>
                                        <small class="text-muted d-block mb-3">حدد عدد النقاط المكتسبة لكل مركز يُصيبه المصوت بدقة (مثلاً: مطابقة المركز الأول = 20، الثاني = 15...)</small>

                                        <div class="row" id="predictionRankPointsContainer">
                                            @php
                                                $ordinals = [1 => 'الأول', 2 => 'الثاني', 3 => 'الثالث', 4 => 'الرابع', 5 => 'الخامس', 6 => 'السادس', 7 => 'السابع', 8 => 'الثامن', 9 => 'التاسع', 10 => 'العاشر'];
                                                $defaultPts = [1 => 20, 2 => 15, 3 => 10, 4 => 5, 5 => 3, 6 => 2, 7 => 1];
                                                $savedPts = $quizQuestion->prediction_rank_points ?? [];
                                                $maxSlots = max(1, min(10, (int) old('vote_max_selections', $quizQuestion->vote_max_selections ?? 3)));
                                            @endphp
                                            @for($r = 1; $r <= 10; $r++)
                                                <div class="col-6 col-md-3 mb-3 rank-point-col" data-rank="{{ $r }}" style="{{ $r <= $maxSlots ? '' : 'display:none;' }}">
                                                    <div class="p-2 border rounded-lg bg-light text-center">
                                                        <span class="d-block text-xs font-weight-bold text-gray-700 mb-1">المركز {{ $ordinals[$r] ?? $r }}</span>
                                                        <div class="input-group input-group-sm">
                                                            <input type="number" name="prediction_rank_points[{{ $r }}]" class="form-control text-center font-weight-bold"
                                                                value="{{ old('prediction_rank_points.' . $r, $savedPts[$r] ?? ($defaultPts[$r] ?? 1)) }}" min="0">
                                                            <div class="input-group-append">
                                                                <span class="input-group-text">نقطة</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endfor
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 6. Voter Eligibility & Anti-112 Settings (Unified Participation Control with Left-aligned Switches) -->
                            <div class="card p-4 border shadow-sm mb-4" style="border-radius:20px; background-color:#f8fafc; border-right: 5px solid #0d9488 !important;">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="rounded-circle bg-teal text-white p-2 ml-3 d-flex align-items-center justify-content-center" style="width:38px; height:38px; background-color:#0d9488;">
                                        <i class="fas fa-user-shield"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 font-weight-bold text-gray-900" style="font-size: 1.05rem;">ضوابط وأهلية المصوتين (من يحق له المشاركة والتصويت)</h6>
                                        <small class="text-muted">التحكم الصارم في من يحق له التصويت وحظر الزوار أو ضيوف رمز الدخول (1-1-2)</small>
                                    </div>
                                </div>

                                <!-- Modern Switch 1: Anti-112 / Require Identity -->
                                <div class="modern-switch-row">
                                    <div class="modern-switch-info">
                                        <div class="modern-switch-title text-dark">
                                            <i class="fas fa-shield-alt text-teal"></i>
                                            حظر زوار رمز الموقع (1-1-2) واشتراط الدخول الموثق بالهوية (بصمة الوجه / واتساب) ⭐
                                        </div>
                                        <p class="modern-switch-desc">
                                            عند تفعيله، سيتم منع الزوار الذين دخلوا برمز الموقع المشترك (112) وعرض رسالة رسمية تطالبهم بتسجيل الدخول بهويتهم للمشاركة.
                                        </p>
                                    </div>
                                    <label class="ios-switch">
                                        <input type="hidden" name="voter_eligibility[require_identity]" value="0">
                                        <input type="checkbox" id="voter_require_identity" name="voter_eligibility[require_identity]" value="1"
                                            {{ old('voter_eligibility.require_identity', $eligibility['require_identity'] ?? '1') == '1' ? 'checked' : '' }}>
                                        <span class="ios-switch-slider"></span>
                                    </label>
                                </div>

                                <!-- Modern Switch 2: Require WhatsApp for Voter -->
                                <div class="modern-switch-row">
                                    <div class="modern-switch-info">
                                        <div class="modern-switch-title text-dark">
                                            <i class="fab fa-whatsapp text-success"></i>
                                            اشتراط توفر رقم واتساب مسجل للمصوت
                                        </div>
                                        <p class="modern-switch-desc">
                                            يُشترط أن يملك المصوت حساب واتساب موثق في ملفه العائلي للتأكد من هويته.
                                        </p>
                                    </div>
                                    <label class="ios-switch">
                                        <input type="hidden" name="voter_eligibility[require_whatsapp]" value="0">
                                        <input type="checkbox" id="voter_require_whatsapp" name="voter_eligibility[require_whatsapp]" value="1"
                                            {{ old('voter_eligibility.require_whatsapp', $eligibility['require_whatsapp'] ?? false) ? 'checked' : '' }}>
                                        <span class="ios-switch-slider"></span>
                                    </label>
                                </div>

                                <!-- Modern Switch 3: Require Ancestry for Voter -->
                                <div class="modern-switch-row">
                                    <div class="modern-switch-info">
                                        <div class="modern-switch-title text-dark">
                                            <i class="fas fa-tree text-teal"></i>
                                            اشتراط الانتماء لسلالة العائلة (استبعاد المصاهرة ومن هم من خارج الشجرة)
                                        </div>
                                        <p class="modern-switch-desc">
                                            حصر حق التصويت على أبناء وبنات العائلة الأصليين واستبعاد الأنساب والمصاهرة المسجلين.
                                        </p>
                                    </div>
                                    <label class="ios-switch">
                                        <input type="hidden" name="voter_eligibility[require_ancestry]" value="0">
                                        <input type="checkbox" id="voter_require_ancestry" name="voter_eligibility[require_ancestry]" value="1"
                                            {{ old('voter_eligibility.require_ancestry', $eligibility['require_ancestry'] ?? false) ? 'checked' : '' }}>
                                        <span class="ios-switch-slider"></span>
                                    </label>
                                </div>

                                <div class="row mt-2">
                                    <!-- Target Gender for Voter -->
                                    <div class="col-md-6 mb-3">
                                        <label class="small font-weight-bold text-gray-800">
                                            <i class="fas fa-venus-mars text-primary mr-1"></i> الفئة المسموح لها بالتصويت (جنس المصوت)
                                        </label>
                                        <select class="form-control modern-input" name="voter_eligibility[allowed_gender]">
                                            <option value="all" {{ old('voter_eligibility.allowed_gender', $eligibility['allowed_gender'] ?? 'all') == 'all' ? 'selected' : '' }}>الجميع (رجال ونساء)</option>
                                            <option value="male" {{ old('voter_eligibility.allowed_gender', $eligibility['allowed_gender'] ?? 'all') == 'male' ? 'selected' : '' }}>رجال فقط (Men Only)</option>
                                            <option value="female" {{ old('voter_eligibility.allowed_gender', $eligibility['allowed_gender'] ?? 'all') == 'female' ? 'selected' : '' }}>نساء فقط (Women Only)</option>
                                        </select>
                                    </div>

                                    <!-- Min Age for Voter -->
                                    <div class="col-md-6 mb-3">
                                        <label class="small font-weight-bold text-gray-800">
                                            <i class="fas fa-birthday-cake text-warning mr-1"></i> الحد الأدنى لعمر المصوت (اختياري)
                                        </label>
                                        <input type="number" class="form-control modern-input" name="voter_eligibility[min_age]"
                                            value="{{ old('voter_eligibility.min_age', $eligibility['min_age'] ?? '') }}" min="1" placeholder="مثال: 18 سنة (اتركه فارغاً للسماح للجميع)">
                                    </div>

                                    <!-- Custom Message for Ineligible Voters -->
                                    <div class="col-12">
                                        <label class="small font-weight-bold text-gray-800">
                                            <i class="fas fa-comment-alt text-secondary mr-1"></i> رسالة مخصصة تظهر لغير المؤهلين للتصويت (اختياري)
                                        </label>
                                        <input type="text" class="form-control modern-input" name="voter_eligibility[custom_message]"
                                            value="{{ old('voter_eligibility.custom_message', $eligibility['custom_message'] ?? '') }}" placeholder="اتركه فارغاً لاستخدام الرسالة التلقائية الافتراضية...">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Legacy Participation condition (Hidden during vote to eliminate redundancy) -->
                        <div class="form-group mb-4" id="legacyPriorRegGroup">
                            <div class="modern-switch-row">
                                <div class="modern-switch-info">
                                    <div class="modern-switch-title text-dark">
                                        <i class="fas fa-id-card text-primary"></i>
                                        اشتراط تسجيل مسبق في شجرة العائلة للمشاركة
                                    </div>
                                    <p class="modern-switch-desc">
                                        عند تفعيله، لن يتم قبول الرد إلا إذا كان رقم هاتف المستخدم مسجلاً مسبقاً في قاعدة بيانات العائلة.
                                    </p>
                                </div>
                                <label class="ios-switch">
                                    <input type="checkbox" id="require_prior_registration"
                                        name="require_prior_registration" value="1"
                                        {{ old('require_prior_registration', $quizQuestion->require_prior_registration) ? 'checked' : '' }}>
                                    <span class="ios-switch-slider"></span>
                                </label>
                            </div>
                        </div>

                        <!-- Chat & Live Discussion Enablement -->
                        <div class="form-group mb-4" id="chatEnablementGroup">
                            <div class="modern-switch-row">
                                <div class="modern-switch-info">
                                    <div class="modern-switch-title text-dark">
                                        <i class="fas fa-comments text-success"></i>
                                        تفعيل المحادثة والنقاش المباشر (Chat) لهذا البند
                                    </div>
                                    <p class="modern-switch-desc">
                                        إتاحة قسم نقاش تفاعلي ومباشر لأفراد العائلة حول هذا الاستطلاع/التحدي/السؤال في الصفحة الرئيسية (يدعم النصوص، الصور، ومقاطع الفيديو حتى 30 ثانية).
                                    </p>
                                </div>
                                <label class="ios-switch">
                                    <input type="checkbox" id="has_chat"
                                        name="has_chat" value="1"
                                        {{ old('has_chat', $quizQuestion->has_chat) ? 'checked' : '' }}>
                                    <span class="ios-switch-slider"></span>
                                </label>
                            </div>
                        </div>

                        <!-- Ordering: Groups Count -->
                        <div class="form-group mb-4" id="groupsCountGroup" style="display:none;">
                            <label for="groups_count" class="modern-label">عدد مجموعات التصنيف والترتيب</label>
                            <input type="number" class="form-control modern-input @error('groups_count') is-invalid @enderror"
                                id="groups_count" name="groups_count" value="{{ old('groups_count', $quizQuestion->groups_count) }}" min="0" placeholder="اتركه 0 أو فارغاً لترتيب خطي بسيط...">
                            <small class="form-text text-muted mt-1">يستخدم لتقسيم العناصر إلى مجموعات تصنيف بالسحب والإفلات.</small>
                            @error('groups_count')
                                <div class="invalid-feedback font-weight-bold mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Dynamic Survey Builder Section -->
                        <div class="form-group mb-4" id="surveySection" style="{{ old('answer_type', $quizQuestion->answer_type) === 'survey' ? '' : 'display:none' }};">
                            <div class="card border-teal shadow-sm overflow-hidden" style="border-radius:20px;">
                                <div class="card-header bg-teal text-white py-3">
                                    <h6 class="mb-0 font-weight-bold"><i class="fas fa-stream ml-2"></i>بناء بنود الاستبيان التقييمي</h6>
                                </div>
                                <div class="card-body p-4 bg-light">
                                    <p class="text-muted small mb-3">يمكنك إضافة بنود متعددة (نص، صورة، أو فيديو) وتحديد نوع استجابة المشارك لكل بند (تقييم بالنجوم، إدخال رقم، أو كتابة نص).</p>
                                    <div id="surveyItemsContainer">
                                        @if(old('answer_type', $quizQuestion->answer_type) === 'survey')
                                            @foreach($quizQuestion->surveyItems as $idx => $si)
                                                <div class="survey-item-row mb-3 p-3 border rounded bg-white" style="border-radius: 14px !important;">
                                                    <input type="hidden" name="survey_items[{{ $idx }}][id]" value="{{ $si->id }}">
                                                    <div class="form-row">
                                                        <div class="col-md-3 mb-2">
                                                            <label class="small font-weight-bold">نوع العنصر</label>
                                                            <select name="survey_items[{{ $idx }}][block_type]" class="form-control form-control-sm survey-block-type">
                                                                <option value="text" @selected($si->block_type === 'text')>نص / عبارة استبيان</option>
                                                                <option value="image" @selected($si->block_type === 'image')>صورة</option>
                                                                <option value="video" @selected($si->block_type === 'video')>فيديو</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-4 mb-2 survey-media-col" style="{{ $si->block_type === 'text' ? 'display:none' : '' }}">
                                                            <label class="small font-weight-bold">استبدال الملف (اختياري)</label>
                                                            <input type="file" name="survey_items[{{ $idx }}][media]" class="form-control-file survey-media-input" accept="image/*,video/*">
                                                            <div class="survey-youtube-col mt-2" style="{{ $si->block_type === 'video' ? '' : 'display:none' }}">
                                                                <label class="small font-weight-bold">رابط يوتيوب URL (بديل للملف)</label>
                                                                <input type="text" name="survey_items[{{ $idx }}][youtube_url]"
                                                                    class="form-control form-control-sm survey-youtube-input"
                                                                    placeholder="https://youtube.com/watch?v=..."
                                                                    value="{{ old("survey_items.$idx.youtube_url", $si->youtube_url ?? '') }}">
                                                            </div>
                                                            @php
                                                                $ytUrl = old("survey_items.$idx.youtube_url", $si->youtube_url ?? '');
                                                                $ytId = null;
                                                                if (!empty($ytUrl) && preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/|youtube\.com\/shorts\/)([a-zA-Z0-9_-]{11})/', $ytUrl, $m)) {
                                                                    $ytId = $m[1];
                                                                }
                                                            @endphp
                                                            @if($si->media_path)
                                                                <small class="text-teal d-block mt-1 font-weight-bold"><i class="fas fa-check"></i> يوجد ملف محفوظ مسبقاً</small>
                                                            @endif
                                                            <div class="survey-media-preview mt-2 small text-muted">
                                                                @if($si->media_path)
                                                                    @if($si->block_type === 'image')
                                                                        <img src="{{ asset('storage/' . $si->media_path) }}"
                                                                            alt="صورة عنصر الاستبيان"
                                                                            style="max-height:90px; max-width:220px; object-fit:contain;"
                                                                            class="img-thumbnail bg-white p-1">
                                                                    @elseif($si->block_type === 'video')
                                                                        <video controls
                                                                            src="{{ asset('storage/' . $si->media_path) }}"
                                                                            style="max-height:110px; max-width:260px; object-fit:contain;"
                                                                            class="border rounded bg-white"></video>
                                                                    @endif
                                                                @endif
                                                            </div>
                                                            <div class="survey-youtube-preview mt-2 small text-muted"
                                                                style="{{ $si->block_type === 'video' && !empty($ytId) && empty($si->media_path) ? '' : 'display:none' }}">
                                                                @if(!empty($ytId))
                                                                    <img src="https://img.youtube.com/vi/{{ $ytId }}/maxresdefault.jpg"
                                                                        alt="يوتيوب"
                                                                        style="max-height:90px; max-width:220px; object-fit:contain;"
                                                                        class="img-thumbnail bg-white p-1 d-block">
                                                                @endif
                                                            </div>
                                                        </div>
                                                        <div class="col-md-5 mb-2 survey-body-col">
                                                            <label class="small font-weight-bold">نص البند</label>
                                                            <textarea name="survey_items[{{ $idx }}][body_text]" class="form-control form-control-sm" rows="2">{{ old("survey_items.$idx.body_text", $si->body_text) }}</textarea>
                                                        </div>
                                                    </div>
                                                    <div class="form-row align-items-end">
                                                        <div class="col-md-3 mb-2">
                                                            <label class="small font-weight-bold">طريقة تقييم المشارك</label>
                                                            <select name="survey_items[{{ $idx }}][response_kind]" class="form-control form-control-sm survey-response-kind">
                                                                <option value="rating" @selected($si->response_kind === 'rating')>تقييم بالنجوم</option>
                                                                <option value="number" @selected($si->response_kind === 'number')>رقم عددي</option>
                                                                <option value="text" @selected($si->response_kind === 'text')>نص حر تعبيري</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-2 mb-2 survey-rating-wrap" style="{{ $si->response_kind === 'rating' ? '' : 'display:none' }}">
                                                            <label class="small">الحد الأقصى للنجوم</label>
                                                            <input type="number" name="survey_items[{{ $idx }}][rating_max]" value="{{ old("survey_items.$idx.rating_max", $si->rating_max) }}" min="2" max="100" class="form-control form-control-sm">
                                                        </div>
                                                        <div class="col-md-4 mb-2 survey-number-wrap" style="{{ $si->response_kind === 'number' ? '' : 'display:none' }}">
                                                            <label class="small">النطاق المسموح (من — إلى)</label>
                                                            <div class="input-group input-group-sm">
                                                                <input type="number" name="survey_items[{{ $idx }}][number_min]" class="form-control" value="{{ old("survey_items.$idx.number_min", $si->number_min ?? 0) }}">
                                                                <input type="number" name="survey_items[{{ $idx }}][number_max]" class="form-control" value="{{ old("survey_items.$idx.number_max", $si->number_max ?? 100) }}">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-2 mb-2 text-left">
                                                            <button type="button" class="btn btn-sm btn-outline-danger remove-survey-item mt-3" style="border-radius: 8px;"><i class="fas fa-trash"></i> إزالة</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                    <button type="button" class="modern-btn modern-btn-primary mt-2" id="addSurveyItem">
                                        <i class="fas fa-plus"></i>إضافة بند استبيان جديد
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Walking Challenge Config Section -->
                        @php
                            $ws = $quizQuestion->walking_settings ?? [];
                            $dailyPts = $ws['daily_points'] ?? [1 => 5, 2 => 4, 3 => 3, 4 => 2, 5 => 1];
                        @endphp
                        <div class="form-group mb-4" id="walkingSection" style="display:none;">
                            <div class="card shadow-sm border-0 overflow-hidden" style="border-radius:20px; border: 2px solid #10b981 !important;">
                                <div class="card-header text-white py-3" style="background: linear-gradient(135deg, #059669, #10b981);">
                                    <h6 class="mb-0 font-weight-bold"><i class="fas fa-running mr-2"></i>إعدادات تحدي المشي واحتساب النقاط</h6>
                                </div>
                                <div class="card-body p-4 bg-light">
                                    <div class="row">
                                        <!-- ── Walking Challenge Scope Selector ─────────────────── -->
                                        <div class="col-12 mb-3">
                                            <label class="modern-label font-weight-bold text-gray-800 d-block mb-2">
                                                <i class="fas fa-layer-group mr-1 text-teal"></i> نطاق المنافسة (أفراد أم مجموعات)
                                            </label>
                                            <div class="row">
                                                <div class="col-md-6 mb-2">
                                                    <label class="d-flex align-items-center p-3 rounded-lg border cursor-pointer walking-scope-card {{ old('walking_settings.competition_scope', $ws['competition_scope'] ?? 'individuals') === 'individuals' ? 'border-success bg-white shadow-xs' : 'bg-light' }}" style="cursor: pointer; border-width: 2px !important; border-radius: 12px;">
                                                        <input type="radio" name="walking_settings[competition_scope]" value="individuals" class="walking-scope-radio mr-2" {{ old('walking_settings.competition_scope', $ws['competition_scope'] ?? 'individuals') === 'individuals' ? 'checked' : '' }}>
                                                        <div>
                                                            <strong class="d-block text-dark"><i class="fas fa-user mr-1 text-success"></i> منافسة فردية (بين الأفراد)</strong>
                                                            <small class="text-muted">يتنافس كل متسابق بمفرده لاعتلاء منصة التتويج وحصد المراكز الأولى.</small>
                                                        </div>
                                                    </label>
                                                </div>
                                                <div class="col-md-6 mb-2">
                                                    <label class="d-flex align-items-center p-3 rounded-lg border cursor-pointer walking-scope-card {{ old('walking_settings.competition_scope', $ws['competition_scope'] ?? 'individuals') === 'groups' ? 'border-primary bg-white shadow-xs' : 'bg-light' }}" style="cursor: pointer; border-width: 2px !important; border-radius: 12px;">
                                                        <input type="radio" name="walking_settings[competition_scope]" value="groups" class="walking-scope-radio mr-2" {{ old('walking_settings.competition_scope', $ws['competition_scope'] ?? 'individuals') === 'groups' ? 'checked' : '' }}>
                                                        <div>
                                                            <strong class="d-block text-dark"><i class="fas fa-users mr-1 text-primary"></i> منافسة جماعية (بين الجروبات / الفرق) ⚔️</strong>
                                                            <small class="text-muted">مواجهة نارية بين مجموعتين أو أكثر من جروبات التحدي (مثل: الهلال ضد النصر).</small>
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- ── Groups Challenge Specific Configuration ─────────────────── -->
                                        <div class="col-12" id="groupsConfigBox" style="{{ old('walking_settings.competition_scope', $ws['competition_scope'] ?? 'individuals') === 'groups' ? '' : 'display:none;' }}">
                                            <div class="p-3 mb-3 border rounded-lg bg-white shadow-xs" style="border: 2px dashed #0284c7 !important; border-radius: 14px;">
                                                <h6 class="font-weight-bold text-primary mb-3">
                                                    <i class="fas fa-users-cog mr-1"></i> إعدادات المواجهة بين المجموعات (Group vs Group)
                                                </h6>

                                                <div class="row">
                                                    <!-- Competing Groups Selection -->
                                                    <div class="col-md-12 mb-3">
                                                        <label class="modern-label font-weight-bold text-gray-800">
                                                            <i class="fas fa-shield-alt mr-1 text-primary"></i> المجموعات المتنافسة (اختر مجموعتين على الأقل) <span class="text-danger">*</span>
                                                        </label>
                                                        <select name="walking_settings[competing_group_ids][]" id="competingGroupSelect" class="form-control select2 modern-input" multiple="multiple" style="width: 100%;">
                                                            @foreach ($walkingGroups ?? [] as $wGroup)
                                                                @php
                                                                    $selectedGroupIds = old('walking_settings.competing_group_ids', $ws['competing_group_ids'] ?? []);
                                                                    $isSelected = in_array((string)$wGroup->id, array_map('strval', (array)$selectedGroupIds), true);
                                                                @endphp
                                                                <option value="{{ $wGroup->id }}" {{ $isSelected ? 'selected' : '' }}>
                                                                    {{ $wGroup->name }} ({{ $wGroup->members_count ?? $wGroup->memberships()->where('status','accepted')->count() }} عضو)
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                        <small class="text-muted d-block mt-1">اختر المجموعات التي ستدخل في هذا التحدي. يمكنك اختيار مجموعتين (مبارزة ثنائية ⚔️) أو أكثر من مجموعتين.</small>
                                                    </div>

                                                    <!-- Group Scoring Mode -->
                                                    <div class="col-md-6 mb-3">
                                                        <label class="modern-label font-weight-bold text-gray-800">
                                                            <i class="fas fa-calculator mr-1 text-teal"></i> آلية التنافس واحتساب فوز المجموعة <span class="text-danger">*</span>
                                                        </label>
                                                        <select name="walking_settings[group_scoring_mode]" id="groupScoringMode" class="form-control modern-input font-weight-bold">
                                                            <option value="total_steps" {{ old('walking_settings.group_scoring_mode', $ws['group_scoring_mode'] ?? 'total_steps') == 'total_steps' ? 'selected' : '' }}>
                                                                إجمالي خطوات المجموعة (Total Steps) ⭐
                                                            </option>
                                                            <option value="average_steps" {{ old('walking_settings.group_scoring_mode', $ws['group_scoring_mode'] ?? '') == 'average_steps' ? 'selected' : '' }}>
                                                                متوسط خطوات العضو (Average Steps per Member - يضمن العدالة التامة) ⭐⭐
                                                            </option>
                                                            <option value="top_performer" {{ old('walking_settings.group_scoring_mode', $ws['group_scoring_mode'] ?? '') == 'top_performer' ? 'selected' : '' }}>
                                                                بطل كل مجموعة (مقارنة أعلى متسابق MVP في كل فريق)
                                                            </option>
                                                            <option value="daily_group_points" {{ old('walking_settings.group_scoring_mode', $ws['group_scoring_mode'] ?? '') == 'daily_group_points' ? 'selected' : '' }}>
                                                                تجميع نقاط المراكز اليومية للجروبات (نقاط تُرصد يومياً في منتصف الليل)
                                                            </option>
                                                            <option value="member_matchups" {{ old('walking_settings.group_scoring_mode', $ws['group_scoring_mode'] ?? '') == 'member_matchups' ? 'selected' : '' }}>
                                                                المواجهات المباشرة بين الأعضاء (الأول ضد الأول، الثاني ضد الثاني...)
                                                            </option>
                                                        </select>
                                                        <small class="text-muted d-block mt-1">خيار <strong>متوسط خطوات العضو</strong> مثالي إذا كان أحد الجروبات يضم أعضاء أكثر من الآخر لضمان عدالة مطلقة.</small>
                                                    </div>

                                                    <!-- Min Member Daily Steps -->
                                                    <div class="col-md-6 mb-3">
                                                        <label class="modern-label font-weight-bold text-gray-800">
                                                            <i class="fas fa-shoe-prints mr-1 text-teal"></i> الحد الأدنى لخطوات العضو لاحتسابه (اختياري)
                                                        </label>
                                                        <input type="number" name="walking_settings[min_member_steps]" class="form-control modern-input"
                                                            value="{{ old('walking_settings.min_member_steps', $ws['min_member_steps'] ?? 0) }}" min="0" placeholder="مثال: 1000 خطوة (اتركه 0 للإلغاء)">
                                                        <small class="text-muted d-block mt-1">العضو الذي يمشي أقل من هذا الحد في اليوم لا تُحسب خطواته لصالح فريقه (منع الحسابات الخاملة).</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- ── Individuals Challenge Specific Configuration ───────────── -->
                                        <div class="col-12" id="individualsConfigBox" style="{{ old('walking_settings.competition_scope', $ws['competition_scope'] ?? 'individuals') === 'individuals' ? '' : 'display:none;' }}">
                                            <div class="row">
                                                <!-- Target Gender -->
                                                <div class="col-md-6 mb-3">
                                                    <label class="modern-label font-weight-bold text-gray-800">
                                                        <i class="fas fa-venus-mars mr-1 text-teal"></i> الفئة المستهدفة / الفائزين
                                                    </label>
                                                    <select name="walking_settings[target_gender]" class="form-control modern-input font-weight-bold">
                                                        <option value="both_segregated" {{ old('walking_settings.target_gender', $ws['target_gender'] ?? 'both_segregated') == 'both_segregated' ? 'selected' : '' }}>
                                                            مساران منفصلان (فائزون للرجال وفائزات للنساء) ⭐
                                                        </option>
                                                        <option value="male" {{ old('walking_settings.target_gender', $ws['target_gender'] ?? '') == 'male' ? 'selected' : '' }}>
                                                            رجال فقط (Male Only)
                                                        </option>
                                                        <option value="female" {{ old('walking_settings.target_gender', $ws['target_gender'] ?? '') == 'female' ? 'selected' : '' }}>
                                                            نساء فقط (Female Only)
                                                        </option>
                                                        <option value="all" {{ old('walking_settings.target_gender', $ws['target_gender'] ?? '') == 'all' ? 'selected' : '' }}>
                                                            ترتيب عام موحد (جميع المشاركين)
                                                        </option>
                                                    </select>
                                                    <small class="text-muted d-block mt-1">يضمن عزل خصوصية الإناث عن الذكور عند اختيار المسارين المنفصلين.</small>
                                                </div>

                                                <!-- Scoring Method -->
                                                <div class="col-md-6 mb-3">
                                                    <label class="modern-label font-weight-bold text-gray-800">
                                                        <i class="fas fa-award mr-1 text-teal"></i> آلية الترتيب واحتساب الفائزين
                                                    </label>
                                                    <select name="walking_settings[scoring_method]" id="walkingScoringMethod" class="form-control modern-input font-weight-bold">
                                                        <option value="daily_rank_points" {{ old('walking_settings.scoring_method', $ws['scoring_method'] ?? 'daily_rank_points') == 'daily_rank_points' ? 'selected' : '' }}>
                                                            تجميع نقاط المراكز اليومية (أعلى نقاط بنهاية المسابقة) ⭐
                                                        </option>
                                                        <option value="cumulative_steps" {{ old('walking_settings.scoring_method', $ws['scoring_method'] ?? '') == 'cumulative_steps' ? 'selected' : '' }}>
                                                            إجمالي الخطوات المقطوعة طوال فترة المسابقة
                                                        </option>
                                                    </select>
                                                    <small class="text-muted d-block mt-1">في نظام النقاط اليومية، يتم ترصيد النقاط يومياً عند منتصف الليل (12:00 AM).</small>
                                                </div>

                                                <!-- Min Daily Steps -->
                                                <div class="col-md-6 mb-3">
                                                    <label class="modern-label font-weight-bold text-gray-800">
                                                        <i class="fas fa-shoe-prints mr-1 text-teal"></i> الحد الأدنى للخطوات اليومية للتأهل (اختياري)
                                                    </label>
                                                    <input type="number" name="walking_settings[min_daily_steps]" class="form-control modern-input"
                                                        value="{{ old('walking_settings.min_daily_steps', $ws['min_daily_steps'] ?? 0) }}" min="0" placeholder="مثال: 5000 خطوة (اتركه 0 للإلغاء)">
                                                    <small class="text-muted d-block mt-1">المتسابق الذي يمشي أقل من هذا الحد لا يحصل على نقاط لليوم.</small>
                                                </div>

                                                <!-- Privacy & Exclusions -->
                                                <div class="col-md-6 mb-3">
                                                    <label class="modern-label font-weight-bold text-gray-800">
                                                        <i class="fas fa-user-shield mr-1 text-teal"></i> ضوابط الخصوصية والاستبعاد
                                                    </label>
                                                    <div class="card p-3 border-0 bg-white shadow-sm" style="border-radius:12px;">
                                                        <div class="custom-control custom-switch mb-2">
                                                            <input type="checkbox" class="custom-control-input" id="exclude_hidden"
                                                                name="walking_settings[exclude_hidden]" value="1"
                                                                {{ old('walking_settings.exclude_hidden', $ws['exclude_hidden'] ?? true) ? 'checked' : '' }}>
                                                            <label class="custom-control-label font-weight-bold" for="exclude_hidden">
                                                                استبعاد المتسابقين أصحاب وضع الخصوصية (Hidden)
                                                            </label>
                                                        </div>
                                                        <div class="custom-control custom-switch">
                                                            <input type="checkbox" class="custom-control-input" id="exclude_anonymous"
                                                                name="walking_settings[exclude_anonymous]" value="1"
                                                                {{ old('walking_settings.exclude_anonymous', $ws['exclude_anonymous'] ?? false) ? 'checked' : '' }}>
                                                            <label class="custom-control-label font-weight-bold" for="exclude_anonymous">
                                                                استبعاد المتسابقين المتخفين (Anonymous) من الجوائز
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Daily Points Distribution Scheme -->
                                                <div class="col-12 mt-2" id="dailyPointsSchemeRow">
                                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                                        <label class="modern-label font-weight-bold text-gray-800 mb-0">
                                                            <i class="fas fa-coins mr-1 text-warning"></i> توزيع النقاط اليومية للمراكز (مرتبط بعدد الفائزين أدناه)
                                                        </label>
                                                        <span class="badge badge-info px-2 py-1" id="walkingPointsCountBadge">
                                                            <span id="walkingPointsCountNumber">{{ old('winners_count', $quizQuestion->winners_count) }}</span> مراكز
                                                        </span>
                                                    </div>
                                                    <small class="text-muted d-block mb-3">
                                                        <i class="fas fa-info-circle mr-1"></i> يتم تحديد عدد المراكز والنقاط تلقائياً بحسب <strong>عدد الفائزين</strong> المدخل في أسفل الصفحة.
                                                    </small>
                                                    <div class="row" id="walkingDailyPointsContainer"></div>
                                                </div>
                                            </div>
                                        </div>

                                        @include('dashboard.quiz-questions._walking-race-settings-fields', ['ws' => $ws ?? [], 'uid' => 'edit'])
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Multiple choices selection switch -->
                        <div class="form-group mb-4" id="multipleSelectionsGroup">
                            <div class="card p-3 border" style="border-radius:16px;">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="is_multiple_selections"
                                        name="is_multiple_selections" value="1"
                                        {{ old('is_multiple_selections', $quizQuestion->is_multiple_selections) ? 'checked' : '' }}>
                                    <label class="custom-control-label text-teal font-weight-bold" for="is_multiple_selections">السؤال يقبل إجابات صحيحة متعددة؟</label>
                                </div>
                            </div>
                        </div>

                        <!-- Dedicated Walking Prediction Config (Clean & Simple) -->
                        @php
                            $isPredictionEdit = old('answer_type', $quizQuestion->answer_type) == 'walking_prediction' || $quizQuestion->isLinkedToWalking();
                        @endphp
                        <div class="form-group mb-4" id="dedicatedWalkingPredictionGroup" style="{{ $isPredictionEdit ? 'display:block;' : 'display:none;' }}">
                            <div class="card p-4 border-0 shadow-sm" style="border-radius: 20px; background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%); border: 2px solid #10b981 !important;">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="rounded-circle text-white p-2 ml-2 d-flex align-items-center justify-content-center" style="width:38px; height:38px; background-color:#059669;">
                                        <i class="fas fa-trophy"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 font-weight-bold text-gray-900" style="font-size: 1.1rem;">إعدادات مسابقة توقعات تحدي المشي ⭐</h6>
                                        <small class="text-muted">اربط مسابقة تصويت سابقة بتحدي مشي نشط، ليتم احتساب النقاط وتتويج المتوقعين تلقائياً</small>
                                    </div>
                                </div>

                                <div class="alert alert-light border border-emerald-200 text-dark mb-4" style="border-radius: 12px; background-color: #ffffff;">
                                    <i class="fas fa-info-circle text-success ml-1"></i>
                                    <strong>طريقة عمل المسابقة:</strong> لا يحتاج المشاركون للتصويت هنا، سيقوم النظام تلقائياً بمطابقة ترشيحات المصوتين في مسابقة التصويت المختارة مع ترتيب المتسابقين في تحدي المشي وعرض منصة التتويج والنتائج الحية مباشرة في الموقع والتطبيق.
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="dedicated_linked_walking_question_id" class="font-weight-bold text-gray-900">
                                            <i class="fas fa-running text-emerald-600 mr-1"></i> 1. اختر تحدي المشي (المسابقة النشطة) <span class="text-danger">*</span>
                                        </label>
                                        <select name="linked_walking_question_id" id="dedicated_linked_walking_question_id" class="form-control select2" style="width: 100%;">
                                            <option value="">-- اختر تحدي المشي المستهدف للمطابقة --</option>
                                            @if(isset($walkingQuestions))
                                                @foreach($walkingQuestions as $wq)
                                                    <option value="{{ $wq->id }}" data-gender="{{ $wq->getWalkingTargetGender() }}" {{ old('linked_walking_question_id', $quizQuestion->linked_walking_question_id) == $wq->id ? 'selected' : '' }}>
                                                        {{ $wq->competition ? '[' . $wq->competition->title . '] ' : '' }}{{ $wq->question_text }} (المراكز: {{ $wq->winners_count }}) [{{ $wq->getWalkingTargetGender() === 'female' ? 'نساء فقط' : ($wq->getWalkingTargetGender() === 'male' ? 'رجال فقط' : 'رجال ونساء') }}]
                                                    </option>
                                                @endforeach
                                            @endif
                                        </select>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="dedicated_source_vote_question_id" class="font-weight-bold text-gray-900">
                                            <i class="fas fa-poll text-blue-600 mr-1"></i> 2. اختر مسابقة التصويت (الترشيحات السابقة) <span class="text-danger">*</span>
                                        </label>
                                        <select name="source_vote_question_id" id="dedicated_source_vote_question_id" class="form-control select2" style="width: 100%;" onchange="syncPredictionRankPointsWithVote(this)">
                                            <option value="">-- اختر مسابقة وسؤال التصويت المصدر --</option>
                                            @if(isset($voteQuestions))
                                                @foreach($voteQuestions as $vq)
                                                    @php
                                                        $maxSel = $vq->vote_max_selections ?: ($vq->choices->count() ?: 5);
                                                    @endphp
                                                    <option value="{{ $vq->id }}" data-max-selections="{{ $maxSel }}" {{ old('source_vote_question_id', $quizQuestion->source_vote_question_id) == $vq->id ? 'selected' : '' }}>
                                                        {{ $vq->competition ? '[' . $vq->competition->title . '] ' : '' }}{{ $vq->question_text }} (التصويت كان على {{ $maxSel }} أشخاص)
                                                    </option>
                                                @endforeach
                                            @endif
                                        </select>
                                    </div>
                                </div>

                                <div class="mt-2">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <label class="font-weight-bold text-gray-900 mb-0">
                                            <i class="fas fa-award text-amber-500 mr-1"></i> 3. نقاط المطابقة لكل مركز مصاب بدقة:
                                        </label>
                                        <span class="badge badge-success px-2 py-1" id="predictionSlotsCountBadge" style="font-size:0.85rem;">مطابقة تلقائية مع عدد المرشحين</span>
                                    </div>
                                    <small class="text-muted d-block mb-3">يتم تحديد عدد الخانات تلقائياً بناءً على عدد الأشخاص الذين تم التصويت عليهم في مسابقة التصويت (مثلاً: 5 أشخاص = 5 خانات، 6 أشخاص = 6 خانات):</small>

                                    <div class="row" id="dedicatedPredictionRankPointsContainer">
                                        @php
                                            $ordinals = [1 => 'الأول', 2 => 'الثاني', 3 => 'الثالث', 4 => 'الرابع', 5 => 'الخامس', 6 => 'السادس', 7 => 'السابع', 8 => 'الثامن', 9 => 'التاسع', 10 => 'العاشر'];
                                            $defaultPts = [1 => 20, 2 => 15, 3 => 10, 4 => 5, 5 => 3, 6 => 2, 7 => 1, 8 => 1, 9 => 1, 10 => 1];
                                            $savedPts = $quizQuestion->prediction_rank_points ?? [];
                                            $selectedSourceId = old('source_vote_question_id', $quizQuestion->source_vote_question_id);
                                            $selectedVq = isset($voteQuestions) ? $voteQuestions->firstWhere('id', $selectedSourceId) : null;
                                            $initialSlots = $selectedVq ? ($selectedVq->vote_max_selections ?: ($selectedVq->choices->count() ?: 5)) : (!empty($savedPts) ? count($savedPts) : 5);
                                        @endphp
                                        @for($r = 1; $r <= 10; $r++)
                                            <div class="col-6 col-md-2 mb-3 dedicated-rank-point-col" data-rank="{{ $r }}" style="{{ $r <= $initialSlots ? '' : 'display:none;' }}">
                                                <div class="p-2 border rounded-lg bg-white shadow-xs text-center" style="border-radius:12px;">
                                                    <span class="d-block text-xs font-weight-bold text-emerald-800 mb-1">المركز {{ $ordinals[$r] ?? $r }}</span>
                                                    <div class="input-group input-group-sm">
                                                        <input type="number" name="prediction_rank_points[{{ $r }}]" class="form-control text-center font-weight-bold border-emerald-300"
                                                            value="{{ old('prediction_rank_points.' . $r, $savedPts[$r] ?? ($defaultPts[$r] ?? 1)) }}" min="0">
                                                        <div class="input-group-append">
                                                            <span class="input-group-text bg-emerald-50 text-xs">نقطة</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endfor
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Choices List Builder -->
                        <div id="choicesSection" class="form-group mb-4">
                            <label class="modern-label">الخيارات وبنود الإجابة <span class="text-danger">*</span></label>
                            <div class="alert alert-info border-0 py-2 px-3 small mb-3" style="border-radius: 12px;" id="choicesHint">حدد الخيار الصحيح بعلامة ✓</div>
                            <div class="alert alert-warning border-0 py-2 px-3 small mb-3" id="singleChoiceHint" style="display:none; border-radius:12px;">
                                <i class="fas fa-info-circle mr-1"></i> عند الاكتفاء بخيار واحد، سيظهر للمستخدم كزر تأكيد للمشاركة.
                            </div>
                            
                            <!-- Choices Container -->
                            <div id="choicesContainer">
                                @php
                                    $choices = old('choices', $quizQuestion->choices->map(fn($c) => [
                                        'id' => $c->id,
                                        'person_id' => $c->person_id,
                                        'text' => $c->choice_text,
                                        'is_correct' => $c->is_correct,
                                        'image' => $c->image,
                                        'video' => $c->video,
                                        'group_name' => $c->group_name
                                    ])->values()->all());
                                @endphp
                                @if(count($choices) > 0)
                                    @foreach($choices as $i => $choice)
                                        <div class="choice-row">
                                            <!-- Selector for Correct Choice -->
                                            <div class="correct-choice-container">
                                                <input type="radio" class="correct-choice-input" name="correct_choice"
                                                    value="{{ $i }}" {{ !empty($choice['is_correct']) ? 'checked' : '' }}
                                                    title="الإجابة الصحيحة">
                                            </div>
                                            
                                            @if(isset($choice['id']))
                                                <input type="hidden" class="choice-id-input" name="choices[{{ $i }}][id]" value="{{ $choice['id'] }}">
                                            @endif
                                            @if(!empty($choice['person_id']))
                                                <input type="hidden" class="choice-person-id-input" name="choices[{{ $i }}][person_id]" value="{{ $choice['person_id'] }}">
                                            @endif
                                            
                                            <!-- Choice Text -->
                                            <input type="text" class="form-control modern-input" name="choices[{{ $i }}][text]"
                                                placeholder="نص الخيار {{ $i + 1 }}" value="{{ $choice['text'] ?? '' }}" style="flex-grow:2;">
                                            
                                            <!-- Group Name (ordering only) -->
                                            <input type="text" class="form-control modern-input choice-group-name" name="choices[{{ $i }}][group_name]"
                                                placeholder="المجموعة" value="{{ $choice['group_name'] ?? '' }}" style="display:none; max-width: 20%;">
                                            
                                            <!-- Media upload buttons -->
                                            <div class="d-flex gap-1">
                                                <label class="btn btn-outline-info media-btn-label" title="رفع صورة للخيارات">
                                                    <i class="fas fa-image"></i>
                                                    <input type="file" name="choices[{{ $i }}][image]" class="choice-image-input d-none" accept="image/*">
                                                </label>
                                                <label class="btn btn-outline-info media-btn-label" title="رفع فيديو للخيارات">
                                                    <i class="fas fa-video"></i>
                                                    <input type="file" name="choices[{{ $i }}][video]" class="choice-video-input d-none" accept="video/*">
                                                </label>
                                            </div>
                                            
                                            <!-- Hidden values -->
                                            <input type="hidden" name="choices[{{ $i }}][is_correct]"
                                                value="{{ !empty($choice['is_correct']) && $choice['is_correct'] != 'false' && $choice['is_correct'] != '0' ? '1' : '0' }}" class="choice-correct">
                                            
                                            <!-- Delete row button -->
                                            <div>
                                                <button type="button" class="btn-remove-choice remove-choice" title="إزالة هذا الخيار"><i class="fas fa-trash"></i></button>
                                            </div>
                                        </div>
                                        <div class="choice-media-preview mb-3 px-3 small text-muted font-weight-bold">
                                            @if(!empty($choice['image']))
                                                <span class="badge badge-light border mr-1 text-teal" title="صورة موجودة"><i class="fas fa-image"></i> تم رفع صورة الخيار</span>
                                                <div class="mt-2">
                                                    <img src="{{ asset('storage/' . $choice['image']) }}"
                                                        alt="صورة الخيار"
                                                        style="max-height:90px; max-width:220px; object-fit:contain;"
                                                        class="img-thumbnail bg-white p-1 rounded-lg">
                                                </div>
                                            @endif
                                            @if(!empty($choice['video']))
                                                <span class="badge badge-light border mr-1 text-teal" title="فيديو موجود"><i class="fas fa-video"></i> تم رفع فيديو الخيار</span>
                                                <div class="mt-2">
                                                    <video controls
                                                        src="{{ asset('storage/' . $choice['video']) }}"
                                                        style="max-height:110px; max-width:260px; object-fit:contain;"
                                                        class="border rounded bg-white rounded-lg">
                                                    </video>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                @else
                                    @for($i = 0; $i < 4; $i++)
                                        <div class="choice-row">
                                            <div class="correct-choice-container">
                                                <input type="radio" class="correct-choice-input" name="correct_choice"
                                                    value="{{ $i }}" {{ $i == 0 ? 'checked' : '' }} title="الإجابة الصحيحة">
                                            </div>
                                            <input type="text" class="form-control modern-input" name="choices[{{ $i }}][text]"
                                                placeholder="نص الخيار {{ $i + 1 }}" style="flex-grow:2;">
                                            <input type="text" class="form-control modern-input choice-group-name" name="choices[{{ $i }}][group_name]"
                                                placeholder="المجموعة" style="display:none; max-width: 20%;">
                                            <div class="d-flex gap-1">
                                                <label class="btn btn-outline-info media-btn-label" title="رفع صورة"><i class="fas fa-image"></i><input type="file" name="choices[{{ $i }}][image]" class="choice-image-input d-none" accept="image/*"></label>
                                                <label class="btn btn-outline-info media-btn-label" title="رفع فيديو"><i class="fas fa-video"></i><input type="file" name="choices[{{ $i }}][video]" class="choice-video-input d-none" accept="video/*"></label>
                                            </div>
                                            <input type="hidden" name="choices[{{ $i }}][is_correct]" value="{{ $i == 0 ? '1' : '0' }}" class="choice-correct">
                                            <div>
                                                <button type="button" class="btn-remove-choice remove-choice"><i class="fas fa-trash"></i></button>
                                            </div>
                                        </div>
                                        <div class="choice-media-preview mb-3 px-3 small text-muted"></div>
                                    @endfor
                                @endif
                            </div>
                            
                            <button type="button" class="modern-btn modern-btn-secondary mt-2" id="addChoice">
                                <i class="fas fa-plus"></i>إضافة خيار جديد
                            </button>
                        </div>

                        <div class="alert alert-info border-0 py-2 px-3 small d-flex align-items-center mb-4" style="border-radius:12px; background-color:#eff6ff; color:#1d4ed8;">
                            <i class="fas fa-info-circle mr-2" style="font-size:1.1rem;"></i>
                            <span>وقت بدء ونهاية بنود الاستبيان يتبع صلاحية وقت الاستطلاع ككل. لتعديله انتقل إلى <a href="{{ route('dashboard.quiz-competitions.edit', $quizCompetition) }}" class="alert-link font-weight-bold">إعدادات الاستطلاع</a>.</span>
                        </div>

                        <!-- Bottom Config Inputs -->
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <div class="form-group">
                                    <label for="winners_count" class="modern-label" id="winnersCountLabel">عدد الفائزين المطلوب سحبهم (في حال القرعة) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control modern-input @error('winners_count') is-invalid @enderror"
                                        id="winners_count" name="winners_count" value="{{ old('winners_count', $quizQuestion->winners_count) }}" min="1" required>
                                    @error('winners_count')
                                        <div class="invalid-feedback font-weight-bold mt-2">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="display_order" class="modern-label">ترتيب عرض السؤال</label>
                                    <input type="number" class="form-control modern-input @error('display_order') is-invalid @enderror"
                                        id="display_order" name="display_order" value="{{ old('display_order', $quizQuestion->display_order) }}" min="0">
                                    @error('display_order')
                                        <div class="invalid-feedback font-weight-bold mt-2">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="d-flex align-items-center justify-content-start pt-4 border-top mt-4" style="gap: 15px;">
                            <button type="submit" class="btn btn-primary btn-lg font-weight-bold px-5 shadow-sm" style="border-radius: 12px; font-size: 1.05rem;">
                                <i class="fas fa-check-circle ml-2"></i> حفظ التعديلات
                            </button>
                            <a href="{{ route('dashboard.quiz-competitions.show', $quizCompetition) }}" class="btn btn-secondary btn-lg font-weight-bold px-4 mr-3" style="border-radius: 12px; font-size: 1.05rem;">
                                <i class="fas fa-times ml-2"></i> إلغاء وتراجع
                            </a>
                        </div>

                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Sila Tree Nominee Picker Modal -->
<div class="modal fade" id="silaTreeModal" tabindex="-1" role="dialog" aria-labelledby="silaTreeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document" style="max-width: 95vw;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 24px; overflow: hidden;">
            <div class="modal-header bg-teal text-white py-3 px-4" style="background-color: #37a05c;">
                <h5 class="modal-title font-weight-bold d-flex align-items-center" id="silaTreeModalLabel">
                    <i class="fas fa-sitemap mr-2"></i> اختيار مرشحين من شجرة العائلة (صلة) 🌳
                </h5>
                <button type="button" class="close text-white opacity-100" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4 bg-light">
                <!-- Toolbar: Search Bar + WhatsApp Filter Toggle + Reset -->
                <div class="card p-3 border-0 shadow-sm mb-3" style="border-radius: 16px;">
                    <div class="row align-items-center">
                        <div class="col-md-5 mb-2 mb-md-0">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-white border-left-0"><i class="fas fa-search text-muted"></i></span>
                                </div>
                                <input type="text" id="silaSearchInput" class="form-control border-right-0" placeholder="🔍 ابحث بالاسم عن أي فرد في شجرة العائلة..." style="border-radius: 0 10px 10px 0;">
                            </div>
                        </div>
                        <div class="col-md-7 d-flex align-items-center justify-content-md-end flex-wrap" style="gap: 12px;">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="silaWhatsappFilter">
                                <label class="custom-control-label font-weight-bold text-dark cursor-pointer" for="silaWhatsappFilter">
                                    <i class="fab fa-whatsapp text-success mr-1"></i> تصفية: من لديهم واتساب فقط
                                </label>
                            </div>
                            <span class="badge badge-success px-3 py-2 font-weight-bold" id="silaSelectedCountText" style="font-size:0.85rem; border-radius:10px;">
                                المرشحون المختارون: 0
                            </span>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnResetSilaTree" style="border-radius:8px;">
                                <i class="fas fa-sync-alt mr-1"></i> إعادة ضبط الشجرة
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tree Columns Container (Scrollable Horizontally) -->
                <div id="silaColumnsContainer" class="d-flex" style="gap: 16px; overflow-x: auto; min-height: 480px; padding-bottom: 12px;">
                    <!-- Sila generational columns dynamically rendered here -->
                </div>
            </div>
            <div class="modal-footer bg-white border-top py-3 px-4 d-flex justify-content-between align-items-center">
                <span class="small text-muted font-weight-bold">
                    <i class="fas fa-info-circle text-teal mr-1"></i> انقر على كارت الشخص لاستعراض أبنائه وفروعه، أو انقر على الزر الأخضر لاختياره كمرشح للتصويت.
                </span>
                <button type="button" class="btn btn-success px-4 font-weight-bold" data-dismiss="modal" style="border-radius:12px; background-color:#37a05c; border-color:#37a05c;">
                    <i class="fas fa-check mr-1"></i> اعتماد المرشحين وإغلاق
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs4.min.js"></script>
    <script>
        $(document).ready(function () {
            // Preload existing nominees from quizQuestion choices
            var selectedNominees = <?php
                $existingNominees = [];
                foreach ($quizQuestion->choices as $c) {
                    if ($c->person_id && $c->person) {
                        $existingNominees[] = [
                            'choice_id' => $c->id,
                            'id' => $c->person_id,
                            'name' => $c->person->name,
                            'photo_url' => $c->person->avatar,
                            'has_whatsapp' => $c->person->contactAccounts()->where('type', 'whatsapp')->exists(),
                        ];
                    }
                }
                echo json_encode($existingNominees, JSON_UNESCAPED_UNICODE);
            ?> || [];
            var activeBranchPath = [];
            var nodesCache = {};

            var btnOpenSilaTree = document.getElementById('btnOpenSilaTree');
            var silaColumnsContainer = document.getElementById('silaColumnsContainer');
            var silaWhatsappFilter = document.getElementById('silaWhatsappFilter');
            var btnResetSilaTree = document.getElementById('btnResetSilaTree');
            var treeNomineesList = document.getElementById('treeNomineesList');
            var treeNomineesEmpty = document.getElementById('treeNomineesEmpty');
            var silaSelectedCountText = document.getElementById('silaSelectedCountText');
            var silaSearchInput = document.getElementById('silaSearchInput');

            window.setVoteNominationMode = function(mode) {
                if (mode === 'predefined') mode = 'predefined_tree';
                var nominationInput = document.getElementById('candidate_nomination_type');
                if (nominationInput) nominationInput.value = mode;
                var modeInput = document.getElementById('vote_mode_input');
                if (modeInput) modeInput.value = mode;

                var cardOpenTree = document.getElementById('voteModeCardOpenTree');
                var cardPredefinedTree = document.getElementById('voteModeCardPredefinedTree');
                var cardCustomOptions = document.getElementById('voteModeCardCustomOptions');
                var candidateRulesCard = document.getElementById('candidateRulesCard');
                var treeNomineesCard = document.getElementById('treeNomineesCard');
                var choicesSection = document.getElementById('choicesSection');

                if (cardOpenTree) cardOpenTree.classList.toggle('is-active', mode === 'open_tree');
                if (cardPredefinedTree) cardPredefinedTree.classList.toggle('is-active', mode === 'predefined_tree');
                if (cardCustomOptions) cardCustomOptions.classList.toggle('is-active', mode === 'custom_options');

                if (mode === 'open_tree') {
                    if (candidateRulesCard) candidateRulesCard.style.display = 'block';
                    if (treeNomineesCard) treeNomineesCard.style.display = 'none';
                    if (choicesSection) choicesSection.style.display = 'none';
                } else if (mode === 'predefined_tree') {
                    if (candidateRulesCard) candidateRulesCard.style.display = 'none';
                    if (treeNomineesCard) treeNomineesCard.style.display = 'block';
                    if (choicesSection) choicesSection.style.display = 'none';
                } else { // custom_options
                    if (candidateRulesCard) candidateRulesCard.style.display = 'none';
                    if (treeNomineesCard) treeNomineesCard.style.display = 'none';
                    if (choicesSection) {
                        choicesSection.style.display = 'block';
                        document.querySelectorAll('.correct-choice-container').forEach(function(el) {
                            el.style.display = 'none';
                        });
                    }
                }
            };
            window.setVoteMode = window.setVoteNominationMode;

            window.setVoteDuration = function(val) {
                var hiddenInput = document.getElementById('vote_edit_duration_hours');
                var customBox = document.getElementById('customDurationBox');
                var customInput = document.getElementById('custom_duration_input');
                var noteEl = document.getElementById('durationDescriptionNote');
                var buttons = document.querySelectorAll('.vote-duration-btn');

                buttons.forEach(function(btn) {
                    btn.classList.remove('btn-primary', 'text-white');
                    btn.classList.add('btn-outline-secondary');
                });

                if (val === 'custom') {
                    if (customBox) customBox.style.display = 'block';
                    var currentHours = parseInt(customInput.value, 10) || 24;
                    if (hiddenInput) hiddenInput.value = currentHours;
                    var customBtn = document.querySelector('.vote-duration-btn[data-val="custom"]');
                    if (customBtn) {
                        customBtn.classList.remove('btn-outline-secondary');
                        customBtn.classList.add('btn-primary', 'text-white');
                    }
                    if (noteEl) noteEl.innerHTML = '<i class="fas fa-info-circle text-primary mr-1"></i> سيتمكن المصوت من تعديل اختياراته خلال <strong>' + currentHours + ' ساعة</strong> من تاريخ أول تصويت.';
                } else {
                    if (customBox) customBox.style.display = 'none';
                    if (hiddenInput) hiddenInput.value = val;
                    var activeBtn = document.querySelector('.vote-duration-btn[data-val="' + val + '"]');
                    if (activeBtn) {
                        activeBtn.classList.remove('btn-outline-secondary');
                        activeBtn.classList.add('btn-primary', 'text-white');
                    }

                    if (noteEl) {
                        if (val === -1) {
                            noteEl.innerHTML = '<i class="fas fa-check-circle text-success mr-1"></i> التصويت <strong>مفتوح دائماً</strong>: يحق للمصوت تعديل خياراته في أي وقت طوال فترة التصويت.';
                        } else if (val === 0) {
                            noteEl.innerHTML = '<i class="fas fa-lock text-danger mr-1"></i> التصويت <strong>نهائي وفوري</strong>: بمجرد إرسال التصويت لأول مرة، يُمنع تعديله إطلاقاً.';
                        } else {
                            noteEl.innerHTML = '<i class="fas fa-info-circle text-primary mr-1"></i> سيتمكن المصوت من تعديل اختياراته بحرية خلال <strong>' + val + ' ساعة</strong> من تاريخ تصويته الأول.';
                        }
                    }
                }
            };

            window.onCustomDurationChange = function(val) {
                var hours = parseInt(val, 10);
                if (!isNaN(hours) && hours > 0) {
                    var hiddenInput = document.getElementById('vote_edit_duration_hours');
                    if (hiddenInput) hiddenInput.value = hours;
                    var noteEl = document.getElementById('durationDescriptionNote');
                    if (noteEl) noteEl.innerHTML = '<i class="fas fa-info-circle text-primary mr-1"></i> سيتمكن المصوت من تعديل اختياراته خلال <strong>' + hours + ' ساعة</strong> من تاريخ أول تصويت.';
                }
            };

            updateNomineesDisplay();

            if (btnOpenSilaTree) {
                btnOpenSilaTree.addEventListener('click', function () {
                    $('#silaTreeModal').modal('show');
                    if (silaColumnsContainer && silaColumnsContainer.children.length === 0) {
                        loadLevel(null, 0);
                    }
                });
            }

            if (silaWhatsappFilter) {
                silaWhatsappFilter.addEventListener('change', function () {
                    nodesCache = {};
                    if (silaSearchInput && silaSearchInput.value.trim().length >= 2) {
                        searchSilaNodes(silaSearchInput.value.trim());
                    } else {
                        silaColumnsContainer.innerHTML = '';
                        activeBranchPath = [];
                        loadLevel(null, 0);
                    }
                });
            }

            if (btnResetSilaTree) {
                btnResetSilaTree.addEventListener('click', function () {
                    nodesCache = {};
                    if (silaSearchInput) silaSearchInput.value = '';
                    silaColumnsContainer.innerHTML = '';
                    activeBranchPath = [];
                    loadLevel(null, 0);
                });
            }

            var searchDebounceTimer = null;
            if (silaSearchInput) {
                silaSearchInput.addEventListener('input', function () {
                    var term = this.value.trim();
                    clearTimeout(searchDebounceTimer);
                    searchDebounceTimer = setTimeout(function () {
                        if (term.length >= 2) {
                            searchSilaNodes(term);
                        } else if (term.length === 0) {
                            silaColumnsContainer.innerHTML = '';
                            activeBranchPath = [];
                            loadLevel(null, 0);
                        }
                    }, 300);
                });
            }

            function searchSilaNodes(term) {
                silaColumnsContainer.innerHTML = '';
                var colDiv = document.createElement('div');
                colDiv.className = 'sila-col w-100';
                colDiv.style.maxWidth = '100%';
                colDiv.innerHTML = '<div class="sila-col-title">نتائج البحث عن: "' + term + '"</div><div class="text-center py-4"><i class="fas fa-spinner fa-spin text-teal"></i></div>';
                silaColumnsContainer.appendChild(colDiv);

                var hasWhatsappOnly = silaWhatsappFilter && silaWhatsappFilter.checked ? 1 : 0;
                var url = "{{ route('dashboard.quiz-questions.tree-picker-nodes') }}?q=" + encodeURIComponent(term) + "&has_whatsapp_only=" + hasWhatsappOnly;

                fetch(url)
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        if (data.success) {
                            renderSearchResults(colDiv, data.nodes, term);
                        }
                    })
                    .catch(function(err) {
                        colDiv.innerHTML = '<div class="text-danger text-center p-3">تعذر تحميل نتائج البحث</div>';
                    });
            }

            function renderSearchResults(colDiv, nodes, term) {
                colDiv.innerHTML = '<div class="sila-col-title">نتائج البحث عن: "' + term + '" (' + nodes.length + ')</div><div class="d-flex flex-wrap" style="gap:12px;" id="searchResultsRow"></div>';
                var row = colDiv.querySelector('#searchResultsRow');

                if (nodes.length === 0) {
                    row.innerHTML = '<div class="text-muted small text-center py-4 w-100">لا توجد نتائج مطابقة</div>';
                    return;
                }

                nodes.forEach(function (person) {
                    var card = document.createElement('div');
                    card.style.flex = '0 0 220px';
                    var isSelected = selectedNominees.some(function(n) { return n.id === person.id; });
                    card.className = 'sila-card' + (isSelected ? ' is-selected' : '');
                    var avatarSrc = person.photo_url || (person.gender === 'female' ? '/admin-assets/img/avatar-female.png' : '/admin-assets/img/avatar-male.png');
                    var whatsappBadge = person.has_whatsapp ? '<span class="badge badge-success badge-pill mr-1"><i class="fab fa-whatsapp"></i></span>' : '';

                    card.innerHTML = 
                        '<img src="' + avatarSrc + '" class="sila-avatar" onerror="this.src=\'/admin-assets/img/default-avatar.png\'">' +
                        '<div class="sila-name">' + (person.name || person.first_name) + '</div>' +
                        '<div class="sila-meta d-flex justify-content-center align-items-center mb-2">' + whatsappBadge + '</div>' +
                        '<button type="button" class="btn btn-sm ' + (isSelected ? 'btn-danger' : 'btn-outline-success') + ' btn-block font-weight-bold btn-select-nominee" style="border-radius:8px; font-size:0.75rem;">' +
                            (isSelected ? '<i class="fas fa-check mr-1"></i> مختار كمرشح' : '<i class="fas fa-plus mr-1"></i> اختيار كمرشح') +
                        '</button>';

                    var selectBtn = card.querySelector('.btn-select-nominee');
                    selectBtn.addEventListener('click', function () {
                        toggleNominee(person);
                        renderSearchResults(colDiv, nodes, term);
                    });

                    row.appendChild(card);
                });
            }

            function loadLevel(parentId, level) {
                var hasWhatsappOnly = silaWhatsappFilter && silaWhatsappFilter.checked ? 1 : 0;
                var cacheKey = (parentId || 'root') + '_' + hasWhatsappOnly;

                while (silaColumnsContainer.children.length > level) {
                    silaColumnsContainer.removeChild(silaColumnsContainer.lastChild);
                }

                var colDiv = document.createElement('div');
                colDiv.className = 'sila-col';
                colDiv.id = 'silaCol_' + level;
                colDiv.innerHTML = '<div class="sila-col-title">' + (level === 0 ? 'الآباء والأجداد' : (level === 1 ? 'الأبناء' : 'الأحفاد والمستوى ' + (level + 1))) + '</div><div class="text-center py-4"><i class="fas fa-spinner fa-spin text-teal"></i></div>';
                silaColumnsContainer.appendChild(colDiv);

                if (nodesCache[cacheKey]) {
                    renderColumn(colDiv, nodesCache[cacheKey], level);
                    return;
                }

                var url = "{{ route('dashboard.quiz-questions.tree-picker-nodes') }}?has_whatsapp_only=" + hasWhatsappOnly + (parentId ? '&parent_id=' + parentId : '');
                fetch(url)
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        if (data.success) {
                            nodesCache[cacheKey] = data.nodes;
                            renderColumn(colDiv, data.nodes, level);
                        }
                    })
                    .catch(function(err) {
                        colDiv.innerHTML = '<div class="text-danger small text-center p-2">تعذر التحميل</div>';
                    });
            }

            function renderColumn(colDiv, nodes, level) {
                var title = level === 0 ? 'الآباء والأجداد' : (level === 1 ? 'الأبناء' : 'الأحفاد (مستوى ' + (level + 1) + ')');
                colDiv.innerHTML = '<div class="sila-col-title">' + title + ' (' + nodes.length + ')</div>';

                if (nodes.length === 0) {
                    colDiv.innerHTML += '<div class="text-muted small text-center py-3">لا يوجد أفراد</div>';
                    return;
                }

                nodes.forEach(function (person) {
                    var card = document.createElement('div');
                    var isSelected = selectedNominees.some(function(n) { return n.id === person.id; });
                    var isActiveBranch = activeBranchPath[level] === person.id;

                    card.className = 'sila-card' + (isSelected ? ' is-selected' : '') + (isActiveBranch ? ' active-branch' : '');
                    
                    var avatarSrc = person.photo_url || (person.gender === 'female' ? '/admin-assets/img/avatar-female.png' : '/admin-assets/img/avatar-male.png');
                    var whatsappBadge = person.has_whatsapp ? '<span class="badge badge-success badge-pill mr-1"><i class="fab fa-whatsapp"></i></span>' : '';
                    var childrenCountBadge = person.children_count > 0 ? '<span class="badge badge-light border">' + person.children_count + ' فرع</span>' : '';

                    card.innerHTML = 
                        '<img src="' + avatarSrc + '" class="sila-avatar" onerror="this.src=\'/admin-assets/img/default-avatar.png\'">' +
                        '<div class="sila-name">' + (person.name || person.first_name) + '</div>' +
                        '<div class="sila-meta d-flex justify-content-center align-items-center">' + whatsappBadge + childrenCountBadge + '</div>' +
                        '<div class="mt-2">' +
                            '<button type="button" class="btn btn-sm ' + (isSelected ? 'btn-danger' : 'btn-outline-success') + ' btn-block font-weight-bold btn-select-nominee" style="border-radius:8px; font-size:0.75rem;">' +
                                (isSelected ? '<i class="fas fa-check mr-1"></i> مختار كمرشح' : '<i class="fas fa-plus mr-1"></i> اختيار كمرشح') +
                            '</button>' +
                        '</div>';

                    card.addEventListener('click', function (e) {
                        if (e.target.closest('.btn-select-nominee')) return;
                        
                        Array.from(colDiv.querySelectorAll('.sila-card')).forEach(function(c) { c.classList.remove('active-branch'); });
                        card.classList.add('active-branch');
                        activeBranchPath[level] = person.id;
                        activeBranchPath.length = level + 1;

                        if (person.children_count > 0) {
                            loadLevel(person.id, level + 1);
                        } else {
                            while (silaColumnsContainer.children.length > level + 1) {
                                silaColumnsContainer.removeChild(silaColumnsContainer.lastChild);
                            }
                        }
                    });

                    var selectBtn = card.querySelector('.btn-select-nominee');
                    selectBtn.addEventListener('click', function (e) {
                        e.stopPropagation();
                        toggleNominee(person);
                        renderColumn(colDiv, nodes, level);
                    });

                    colDiv.appendChild(card);
                });
            }

            function toggleNominee(person) {
                var idx = selectedNominees.findIndex(function(n) { return n.id === person.id; });
                if (idx !== -1) {
                    selectedNominees.splice(idx, 1);
                } else {
                    selectedNominees.push(person);
                }
                updateNomineesDisplay();
            }

            function updateNomineesDisplay() {
                if (!treeNomineesList) return;
                treeNomineesList.innerHTML = '';

                if (selectedNominees.length === 0) {
                    treeNomineesEmpty.style.display = 'block';
                    if (silaSelectedCountText) silaSelectedCountText.innerText = 'المرشحون المختارون: 0';
                    return;
                }

                treeNomineesEmpty.style.display = 'none';
                if (silaSelectedCountText) silaSelectedCountText.innerText = 'المرشحون المختارون: ' + selectedNominees.length;

                selectedNominees.forEach(function (person, index) {
                    var chip = document.createElement('div');
                    chip.className = 'nominee-chip';
                    var avatarSrc = person.photo_url || '/admin-assets/img/default-avatar.png';

                    chip.innerHTML = 
                        '<img src="' + avatarSrc + '" onerror="this.src=\'/admin-assets/img/default-avatar.png\'">' +
                        '<span class="font-weight-bold text-dark">' + (person.name || person.first_name) + '</span>' +
                        (person.has_whatsapp ? '<i class="fab fa-whatsapp text-success small"></i>' : '') +
                        (person.choice_id ? '<input type="hidden" name="choices[' + index + '][id]" value="' + person.choice_id + '">' : '') +
                        '<input type="hidden" name="choices[' + index + '][person_id]" value="' + person.id + '">' +
                        '<input type="hidden" name="choices[' + index + '][text]" value="' + (person.name || person.first_name) + '">' +
                        '<button type="button" class="btn btn-sm text-danger p-0 ml-1 btn-remove-chip" title="إزالة"><i class="fas fa-times-circle"></i></button>';

                    chip.querySelector('.btn-remove-chip').addEventListener('click', function () {
                        toggleNominee(person);
                        Array.from(silaColumnsContainer.children).forEach(function(col) {
                            Array.from(col.querySelectorAll('.sila-card')).forEach(function(c) {
                                var cName = c.querySelector('.sila-name');
                                if (cName && cName.innerText === (person.name || person.first_name)) {
                                    c.classList.remove('is-selected');
                                    var b = c.querySelector('.btn-select-nominee');
                                    if (b) {
                                        b.className = 'btn btn-sm btn-outline-success btn-block font-weight-bold btn-select-nominee';
                                        b.innerHTML = '<i class="fas fa-plus mr-1"></i> اختيار كمرشح';
                                    }
                                }
                            });
                        });
                    });

                    treeNomineesList.appendChild(chip);
                });
            }

            var mainForm = document.getElementById('questionForm') || document.querySelector('form');
            if (mainForm) {
                mainForm.addEventListener('submit', function () {
                    var type = getSelectedAnswerType();
                    var mode = (document.getElementById('candidate_nomination_type') && document.getElementById('candidate_nomination_type').value) || 'open_tree';
                    if (type === 'vote' && mode === 'open_tree') {
                        document.querySelectorAll('#choicesContainer input, #treeNomineesList input').forEach(function(inp) {
                            inp.disabled = true;
                        });
                    } else if (type === 'vote' && mode === 'predefined_tree') {
                        document.querySelectorAll('#choicesContainer input').forEach(function(inp) {
                            inp.disabled = true;
                        });
                    } else {
                        document.querySelectorAll('#treeNomineesList input').forEach(function(inp) {
                            inp.disabled = true;
                        });
                    }
                });
            }
            // Initialize Summernote Description Editor
            if ($('#description').length) {
                $('#description').summernote({
                    placeholder: 'اكتب نصاً اختيارياً يوضح تفاصيل البند أو السؤال...',
                    height: 150,
                    direction: 'rtl',
                    toolbar: [
                        ['style', ['style']],
                        ['font', ['bold', 'italic', 'underline', 'clear']],
                        ['fontname', ['fontname']],
                        ['color', ['color']],
                        ['para', ['ul', 'ol', 'paragraph']],
                        ['table', ['table']],
                        ['insert', ['link', 'picture', 'video']],
                        ['view', ['fullscreen', 'codeview', 'help']]
                    ]
                });
            }

            var choicesContainer = document.getElementById('choicesContainer');
            var choicesSection = document.getElementById('choicesSection');
            var multipleSelectionsGroup = document.getElementById('multipleSelectionsGroup');
            var isMultipleSwitch = document.getElementById('is_multiple_selections');
            var addChoiceBtn = document.getElementById('addChoice');
            var singleChoiceHint = document.getElementById('singleChoiceHint');
            var sortableInstance = null;

            function getSelectedAnswerType() {
                var checked = document.querySelector('.answer-type-radio:checked');
                return checked ? checked.value : 'multiple_choice';
            }

            function updateSingleChoiceHint() {
                var rows = choicesContainer.querySelectorAll('.choice-row');
                var type = getSelectedAnswerType();
                if (singleChoiceHint) {
                    singleChoiceHint.style.display = (type === 'multiple_choice' && !isMultipleSwitch.checked && rows.length === 1) ? 'block' : 'none';
                }
            }

            var surveySection = document.getElementById('surveySection');
            var surveyItemsContainer = document.getElementById('surveyItemsContainer');
            var surveyItemSeq = surveyItemsContainer ? surveyItemsContainer.querySelectorAll('.survey-item-row').length : 0;

            function surveyRowTemplate(idx) {
                return '<div class="survey-item-row mb-3 p-3 border rounded bg-white" style="border-radius: 14px !important;">' +
                    '<input type="hidden" name="survey_items[' + idx + '][id]" value="">' +
                    '<div class="form-row">' +
                    '<div class="col-md-3 mb-2"><label class="small font-weight-bold">نوع العنصر</label>' +
                    '<select name="survey_items[' + idx + '][block_type]" class="form-control form-control-sm survey-block-type">' +
                    '<option value="text">نص / عبارة استبيان</option><option value="image">صورة</option><option value="video">فيديو</option></select></div>' +
                    '<div class="col-md-4 mb-2 survey-media-col" style="display:none"><label class="small font-weight-bold">رفع ملف</label>' +
                    '<input type="file" name="survey_items[' + idx + '][media]" class="form-control-file survey-media-input" accept="image/*,video/*">' +
                    '<div class="survey-youtube-col mt-2" style="display:none">' +
                    '<label class="small font-weight-bold">رابط فيديو يوتيوب URL</label>' +
                    '<input type="text" name="survey_items[' + idx + '][youtube_url]" class="form-control form-control-sm survey-youtube-input" placeholder="https://youtube.com/watch?v=...">' +
                    '</div>' +
                    '<div class="survey-youtube-preview mt-2 small text-muted" style="display:none"></div>' +
                    '</div>' +
                    '<div class="col-md-5 mb-2 survey-body-col"><label class="small font-weight-bold">نص البند (إلزامي للنصوص، اختياري للصور/الفيديو)</label>' +
                    '<textarea name="survey_items[' + idx + '][body_text]" class="form-control form-control-sm" rows="2"></textarea></div></div>' +
                    '<div class="form-row align-items-end">' +
                    '<div class="col-md-3 mb-2"><label class="small font-weight-bold">طريقة تقييم المشارك</label>' +
                    '<select name="survey_items[' + idx + '][response_kind]" class="form-control form-control-sm survey-response-kind">' +
                    '<option value="rating">تقييم بالنجوم (من 1 إلى N)</option><option value="number">رقم عددي (ضمن نطاق)</option><option value="text">نص حر تعبيري</option></select></div>' +
                    '<div class="col-md-2 mb-2 survey-rating-wrap"><label class="small">الحد الأقصى للنجوم</label>' +
                    '<input type="number" name="survey_items[' + idx + '][rating_max]" value="10" min="2" max="100" class="form-control form-control-sm"></div>' +
                    '<div class="col-md-4 mb-2 survey-number-wrap" style="display:none"><label class="small">النطاق المسموح (من — إلى)</label>' +
                    '<div class="input-group input-group-sm"><input type="number" name="survey_items[' + idx + '][number_min]" class="form-control" placeholder="من" value="0">' +
                    '<input type="number" name="survey_items[' + idx + '][number_max]" class="form-control" placeholder="إلى" value="100"></div></div>' +
                    '<div class="col-md-2 mb-2 text-left"><button type="button" class="btn btn-sm btn-outline-danger remove-survey-item mt-3" style="border-radius: 8px;"><i class="fas fa-trash"></i> إزالة</button></div></div></div>';
            }

            function bindSurveyRow(row) {
                var block = row.querySelector('.survey-block-type');
                var resp = row.querySelector('.survey-response-kind');
                var mediaInput = row.querySelector('.survey-media-input');
                var youtubeCol = row.querySelector('.survey-youtube-col');
                var youtubeInput = row.querySelector('.survey-youtube-input');
                var youtubePreview = row.querySelector('.survey-youtube-preview');

                function updBlock() {
                    var v = block.value;
                    row.querySelector('.survey-media-col').style.display = (v === 'text') ? 'none' : 'block';
                    if (youtubeCol) youtubeCol.style.display = (v === 'video') ? 'block' : 'none';
                    if (youtubePreview) youtubePreview.style.display = 'none';
                    if (v === 'video' && youtubeInput) youtubeInput.dispatchEvent(new Event('input'));
                }
                function updResp() {
                    var r = resp.value;
                    row.querySelector('.survey-rating-wrap').style.display = r === 'rating' ? 'block' : 'none';
                    row.querySelector('.survey-number-wrap').style.display = r === 'number' ? 'block' : 'none';
                }

                function extractYouTubeId(url) {
                    var pattern = /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/|youtube\.com\/shorts\/)([a-zA-Z0-9_-]{11})/;
                    var m = (url || '').match(pattern);
                    return m ? m[1] : null;
                }

                if (youtubeInput) {
                    youtubeInput.addEventListener('input', function () {
                        if (!youtubePreview) return;
                        var id = extractYouTubeId(this.value.trim());
                        if (!id) {
                            youtubePreview.style.display = 'none';
                            youtubePreview.innerHTML = '';
                            return;
                        }
                        youtubePreview.style.display = 'block';
                        youtubePreview.innerHTML =
                            '<img src="https://img.youtube.com/vi/' + id + '/maxresdefault.jpg"' +
                            ' style="max-height:90px; max-width:220px; object-fit:contain"' +
                            ' class="img-thumbnail bg-white p-1 mt-2 d-block" alt="صورة يوتيوب">';
                    });
                }

                if (mediaInput) {
                    mediaInput.addEventListener('change', function () {
                        var preview = row.querySelector('.survey-media-preview');
                        if (!preview) return;
                        if (!this.files || !this.files[0]) return;

                        if (preview.dataset.objectUrl) {
                            try { URL.revokeObjectURL(preview.dataset.objectUrl); } catch (e) {}
                        }
                        preview.dataset.objectUrl = '';

                        var file = this.files[0];
                        var objectUrl = URL.createObjectURL(file);
                        preview.dataset.objectUrl = objectUrl;

                        preview.innerHTML = '';
                        var isImage = file.type && file.type.startsWith('image/');

                        var fileName = file.name || '';
                        var badge = document.createElement('span');
                        badge.className = 'badge badge-light border d-inline-flex align-items-center';
                        badge.innerHTML = '<i class="fas fa-' + (isImage ? 'image' : 'video') + ' mr-1"></i> ' + (isImage ? 'صورة' : 'فيديو') + (fileName ? (': ' + fileName) : '');
                        preview.appendChild(badge);

                        if (isImage) {
                            var img = document.createElement('img');
                            img.src = objectUrl;
                            img.alt = 'صورة عنصر الاستبيان';
                            img.style.maxHeight = '90px';
                            img.style.maxWidth = '220px';
                            img.style.objectFit = 'contain';
                            img.className = 'img-thumbnail bg-white p-1 mt-2 d-block';
                            preview.appendChild(img);
                        } else {
                            var vid = document.createElement('video');
                            vid.controls = true;
                            vid.src = objectUrl;
                            vid.style.maxHeight = '110px';
                            vid.style.maxWidth = '260px';
                            vid.style.objectFit = 'contain';
                            vid.className = 'border rounded bg-white mt-2 d-block';
                            preview.appendChild(vid);
                        }
                    });
                }

                block.addEventListener('change', updBlock);
                resp.addEventListener('change', updResp);
                row.querySelector('.remove-survey-item').addEventListener('click', function () {
                    if (surveyItemsContainer.querySelectorAll('.survey-item-row').length <= 1) return;
                    row.remove();
                });
                updBlock();
                updResp();
                if (youtubeInput) youtubeInput.dispatchEvent(new Event('input'));
            }

            function appendSurveyRow() {
                var idx = surveyItemSeq++;
                var div = document.createElement('div');
                div.innerHTML = surveyRowTemplate(idx);
                var row = div.firstElementChild;
                surveyItemsContainer.appendChild(row);
                bindSurveyRow(row);
            }

            function toggleChoices() {
                var type = getSelectedAnswerType();
                var isChoice = type === 'multiple_choice';
                var isOrdering = type === 'ordering';
                var isTrueFalse = type === 'true_false';
                var isVote = type === 'vote';
                var isFillBlank = type === 'fill_blank';
                var isSurvey = type === 'survey';
                var isWalking = type === 'walking';
                var isPrediction = type === 'walking_prediction';

                var walkingSection = document.getElementById('walkingSection');
                var winnersCountLabel = document.getElementById('winnersCountLabel');
                var legacyPriorRegGroup = document.getElementById('legacyPriorRegGroup');
                var dedicatedPredGroup = document.getElementById('dedicatedWalkingPredictionGroup');

                if (dedicatedPredGroup) {
                    dedicatedPredGroup.style.display = isPrediction ? 'block' : 'none';
                    dedicatedPredGroup.querySelectorAll('input, select').forEach(function(el) {
                        var col = el.closest('.dedicated-rank-point-col');
                        if (col && col.style.display === 'none') {
                            el.disabled = true;
                        } else {
                            el.disabled = !isPrediction;
                        }
                    });
                }

                var voteWalkingPanel = document.getElementById('voteWalkingLinkPanel');
                if (voteWalkingPanel) {
                    var predSwitch = document.getElementById('enableWalkingPredictionSwitch');
                    var isVotePredEnabled = isVote && !isPrediction && predSwitch && predSwitch.checked;
                    voteWalkingPanel.querySelectorAll('input[name], select[name]').forEach(function(el) {
                        var col = el.closest('.rank-point-col');
                        if (col && col.style.display === 'none') {
                            el.disabled = true;
                        } else {
                            el.disabled = !isVotePredEnabled;
                        }
                    });
                }

                if (walkingSection) {
                    walkingSection.style.display = isWalking ? 'block' : 'none';
                }
                if (winnersCountLabel) {
                    if (isWalking) {
                        winnersCountLabel.innerHTML = 'عدد الفائزين المطلوب تتويجهم في التحدي <span class="text-danger">*</span>';
                    } else if (isPrediction) {
                        winnersCountLabel.innerHTML = 'عدد الفائزين المطلوب تكريمهم في مسابقة التوقعات <span class="text-danger">*</span>';
                    } else if (isVote) {
                        winnersCountLabel.innerHTML = 'عدد المراكز / الفائزين (في حال القرعة أو التكريم) <span class="text-muted small font-weight-normal">(افتراضي: 1)</span> <span class="text-danger">*</span>';
                    } else {
                        winnersCountLabel.innerHTML = 'عدد الفائزين المطلوب سحبهم (في حال القرعة) <span class="text-danger">*</span>';
                    }
                }
                if (isWalking) {
                    updateWalkingDailyPointsInputs();
                }

                if (surveySection) {
                    surveySection.style.display = isSurvey ? 'block' : 'none';
                    if (!isSurvey && surveyItemsContainer) {
                        surveyItemsContainer.innerHTML = '';
                        surveyItemSeq = 0;
                    } else if (isSurvey && surveyItemsContainer && surveyItemsContainer.querySelectorAll('.survey-item-row').length === 0) {
                        appendSurveyRow();
                    }
                }

                if (legacyPriorRegGroup) {
                    legacyPriorRegGroup.style.display = (isVote || isPrediction) ? 'none' : 'block';
                }

                choicesSection.style.display = (isChoice || isOrdering || isTrueFalse || isFillBlank) && !isSurvey && !isWalking && !isPrediction ? 'block' : 'none';
                multipleSelectionsGroup.style.display = isChoice && !isSurvey && !isWalking && !isPrediction ? 'block' : 'none';

                var voteOptionsGroup = document.getElementById('voteOptionsGroup');
                if (voteOptionsGroup) {
                    voteOptionsGroup.style.display = (isVote && !isSurvey && !isPrediction) ? 'block' : 'none';
                    if (isVote) {
                        var currentVoteMode = (document.getElementById('candidate_nomination_type') && document.getElementById('candidate_nomination_type').value) || 'open_tree';
                        setVoteNominationMode(currentVoteMode);
                    }
                }

                var hint = document.getElementById('choicesHint');
                if (isOrdering) {
                    hint.textContent = 'أدخل الخيارات بالترتيب الصحيح، ستظهر للمستخدم بشكل عشوائي ليرتبها بسحبها وإفلاتها.';
                } else if (isTrueFalse) {
                    hint.textContent = 'حدد صح أم خطأ لكل جملة وخيار مع إمكانية إرفاق صورة مع العبارة.';
                } else if (isVote) {
                    hint.textContent = 'أدخل بنود وخيارات التصويت والترشيح (لا توجد إجابات صحيحة في هذا النوع).';
                } else if (isFillBlank) {
                    hint.innerHTML = 'أدخل خيارات الكلمات لملء الفراغ، وحدد الكلمة <strong>الصحيحة ✓</strong>. ضع <code>___</code> (ثلاثة خطوط سفلية) في نص السؤال مكان الفراغ.';
                } else {
                    hint.textContent = 'حدد الخيار الصحيح للمشارك بعلامة ✓';
                }

                var groupsCountGroup = document.getElementById('groupsCountGroup');
                if (groupsCountGroup) groupsCountGroup.style.display = (isOrdering && !isSurvey) ? 'block' : 'none';

                var groupNameInputs = document.querySelectorAll('.choice-group-name');
                groupNameInputs.forEach(function(el) {
                    el.style.display = isOrdering ? 'block' : 'none';
                });

                updateChoiceInputsType();
                updateSingleChoiceHint();
            }

            function updateChoiceInputsType() {
                var type = getSelectedAnswerType();
                var isOrdering = type === 'ordering';
                var isTrueFalse = type === 'true_false';
                var isVote = type === 'vote';
                var isFillBlank = type === 'fill_blank';
                var isMultiple = isMultipleSwitch.checked && !isOrdering && !isTrueFalse && !isVote && !isFillBlank;
                var rows = choicesContainer.querySelectorAll('.choice-row');

                rows.forEach(function (row, idx) {
                    var container = row.querySelector('.correct-choice-container');
                    var hidden = row.querySelector('.choice-correct');
                    var wasChecked = hidden.value === '1' || hidden.value === 'true';

                    if (isOrdering) {
                        container.innerHTML = '<i class="fas fa-grip-lines drag-handle text-muted mr-2" style="cursor:move;" title="اسحب للترتيب"></i><span class="badge badge-info">' + (idx + 1) + '</span>';
                        hidden.value = '1';
                        row.classList.add('is-ordering');
                    } else {
                        row.classList.remove('is-ordering');
                        if (isTrueFalse) {
                            var checkedTrue = wasChecked ? 'checked' : '';
                            var checkedFalse = !wasChecked ? 'checked' : '';
                            container.innerHTML = '<div class="d-flex flex-column gap-1" style="min-width:60px;"><label class="mb-0 text-success small"><input type="radio" class="correct-choice-input" name="tf_choice_' + idx + '" value="1" ' + checkedTrue + '> صح</label><label class="mb-0 text-danger small"><input type="radio" class="correct-choice-input" name="tf_choice_' + idx + '" value="0" ' + checkedFalse + '> خطأ</label></div>';
                        } else if (isVote) {
                            container.innerHTML = '<span class="badge badge-primary"><i class="fas fa-poll"></i> ' + (idx + 1) + '</span>';
                            hidden.value = '0';
                        } else if (isMultiple) {
                            container.innerHTML = '<input type="checkbox" class="correct-choice-input" name="correct_choices[]" value="' + idx + '" ' + (wasChecked ? 'checked' : '') + ' title="إجابة صحيحة">';
                        } else {
                            container.innerHTML = '<input type="radio" class="correct-choice-input" name="correct_choice" value="' + idx + '" ' + (wasChecked ? 'checked' : '') + ' title="الإجابة الصحيحة">';
                        }
                    }
                });

                if (!isOrdering) attachChoiceListeners();
            }

            function attachChoiceListeners() {
                choicesContainer.querySelectorAll('.correct-choice-input').forEach(function (input) {
                    input.addEventListener('change', updateHiddenValues);
                });
            }

            function updateHiddenValues() {
                var type = getSelectedAnswerType();
                var isOrdering = type === 'ordering';
                var isTrueFalse = type === 'true_false';
                var isMultiple = isMultipleSwitch.checked && !isOrdering && !isTrueFalse;
                var rows = choicesContainer.querySelectorAll('.choice-row');

                rows.forEach(function (row, idx) {
                    var hidden = row.querySelector('.choice-correct');
                    if (isOrdering) {
                        hidden.value = '1';
                    } else if (isTrueFalse) {
                        var tfRadio = row.querySelector('input[type="radio"]:checked');
                        if (tfRadio) hidden.value = tfRadio.value;
                    } else if (isMultiple) {
                        var input = row.querySelector('.correct-choice-input');
                        if (input) hidden.value = input.checked ? '1' : '0';
                    }
                });

                if (!isMultiple && !isOrdering && !isTrueFalse) {
                    document.querySelectorAll('.choice-correct').forEach(function (h, i) {
                        var r = document.querySelector('input[name="correct_choice"][value="' + i + '"]');
                        if (r) h.value = r.checked ? '1' : '0';
                    });
                }
            }

            function toggleSortable() {
                if (getSelectedAnswerType() === 'ordering') {
                    if (!sortableInstance) {
                        sortableInstance = new Sortable(choicesContainer, {
                            animation: 150,
                            handle: '.drag-handle',
                            ghostClass: 'bg-light',
                            onEnd: function () { reindexChoices(); }
                        });
                    }
                } else {
                    if (sortableInstance) { sortableInstance.destroy(); sortableInstance = null; }
                }
            }

            function removeChoiceRow(row) {
                if (choicesContainer.querySelectorAll('.choice-row').length <= 1) return;
                var preview = row.nextElementSibling;
                row.remove();
                if (preview && preview.classList.contains('choice-media-preview')) preview.remove();
                reindexChoices();
                updateSingleChoiceHint();
            }

            function reindexChoices() {
                var rows = choicesContainer.querySelectorAll('.choice-row');
                var isOrdering = getSelectedAnswerType() === 'ordering';
                var isVote = getSelectedAnswerType() === 'vote';
                rows.forEach(function (row, idx) {
                    var input = row.querySelector('.correct-choice-input');
                    var container = row.querySelector('.correct-choice-container');
                    var hidden = row.querySelector('.choice-correct');
                    var textInput = row.querySelector('input[type="text"]:not(.choice-group-name)');
                    var groupNameInput = row.querySelector('.choice-group-name');
                    var imageInput = row.querySelector('.choice-image-input');
                    var videoInput = row.querySelector('.choice-video-input');

                    if (isOrdering) {
                        container.innerHTML = '<i class="fas fa-grip-lines drag-handle text-muted mr-2" style="cursor:move;" title="اسحب للترتيب"></i><span class="badge badge-info">' + (idx + 1) + '</span>';
                        hidden.value = '1';
                    } else if (isVote) {
                        container.innerHTML = '<span class="badge badge-primary"><i class="fas fa-poll"></i> ' + (idx + 1) + '</span>';
                        hidden.value = '0';
                    } else if (getSelectedAnswerType() === 'true_false') {
                        var radios = container.querySelectorAll('.correct-choice-input');
                        radios.forEach(function (radio) {
                            radio.name = 'tf_choice_' + idx;
                        });
                    } else if (input) {
                        input.value = idx;
                    }

                    var idInput = row.querySelector('.choice-id-input');
                    var personIdInput = row.querySelector('.choice-person-id-input');
                    if (idInput) idInput.name = 'choices[' + idx + '][id]';
                    if (personIdInput) personIdInput.name = 'choices[' + idx + '][person_id]';
                    textInput.name = 'choices[' + idx + '][text]';
                    if (groupNameInput) groupNameInput.name = 'choices[' + idx + '][group_name]';
                    hidden.name = 'choices[' + idx + '][is_correct]';
                    if (imageInput) imageInput.name = 'choices[' + idx + '][image]';
                    if (videoInput) videoInput.name = 'choices[' + idx + '][video]';
                });

                if (!isOrdering) attachChoiceListeners();
            }

            function attachMediaListeners(container) {
                container = container || choicesContainer;
                container.querySelectorAll('.choice-image-input, .choice-video-input').forEach(function (input) {
                    input.addEventListener('change', function () {
                        var row = this.closest('.choice-row');
                        var preview = row.nextElementSibling;
                        if (!preview || !preview.classList.contains('choice-media-preview')) {
                            preview = document.createElement('div');
                            preview.className = 'choice-media-preview mb-2 px-3 small text-muted font-weight-bold';
                            row.after(preview);
                        }
                        if (!this.files || !this.files[0]) return;

                        if (preview.dataset.objectUrl) {
                            try { URL.revokeObjectURL(preview.dataset.objectUrl); } catch (e) {}
                        }
                        preview.dataset.objectUrl = '';

                        var file = this.files[0];
                        var objectUrl = URL.createObjectURL(file);
                        preview.dataset.objectUrl = objectUrl;

                        preview.innerHTML = '';
                        var isImage = this.classList.contains('choice-image-input') || (file.type && file.type.startsWith('image/'));
                        var mediaType = isImage ? 'صورة' : 'فيديو';
                        var icon = isImage ? 'image' : 'video';
                        var fileName = file.name || '';

                        var badge = document.createElement('span');
                        badge.className = 'badge badge-light border d-inline-flex align-items-center';
                        badge.innerHTML = '<i class="fas fa-' + icon + ' mr-1 text-teal"></i> ' + mediaType + (fileName ? (': ' + fileName) : '');
                        preview.appendChild(badge);

                        if (isImage) {
                            var img = document.createElement('img');
                            img.src = objectUrl;
                            img.alt = 'صورة الخيار';
                            img.style.maxHeight = '90px';
                            img.style.maxWidth = '220px';
                            img.style.objectFit = 'contain';
                            img.className = 'img-thumbnail bg-white p-1 mt-2 d-block';
                            preview.appendChild(img);
                        } else {
                            var vid = document.createElement('video');
                            vid.controls = true;
                            vid.src = objectUrl;
                            vid.style.maxHeight = '110px';
                            vid.style.maxWidth = '260px';
                            vid.style.objectFit = 'contain';
                            vid.className = 'border rounded bg-white mt-2 d-block';
                            preview.appendChild(vid);
                        }
                    });
                });
            }

            var addSurveyItemBtn = document.getElementById('addSurveyItem');
            if (addSurveyItemBtn) {
                addSurveyItemBtn.addEventListener('click', function () { appendSurveyRow(); });
            }

            document.querySelectorAll('.answer-type-radio').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    toggleChoices();
                    toggleSortable();
                });
            });
            isMultipleSwitch.addEventListener('change', function () {
                updateChoiceInputsType();
                updateSingleChoiceHint();
            });

            choicesContainer.querySelectorAll('.remove-choice').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    removeChoiceRow(btn.closest('.choice-row'));
                });
            });

            addChoiceBtn.addEventListener('click', function () {
                var idx = choicesContainer.querySelectorAll('.choice-row').length;
                var type = getSelectedAnswerType();
                var isOrdering = type === 'ordering';
                var isTrueFalse = type === 'true_false';
                var isFillBlank = type === 'fill_blank';
                var isMultiple = isMultipleSwitch.checked && !isOrdering && !isTrueFalse && !isFillBlank;

                var inputHtml = '';
                if (isOrdering) {
                    inputHtml = '<i class="fas fa-grip-lines drag-handle text-muted mr-2" style="cursor:move;" title="اسحب للترتيب"></i><span class="badge badge-info">' + (idx + 1) + '</span>';
                } else if (isTrueFalse) {
                    inputHtml = '<div class="d-flex flex-column gap-1" style="min-width:60px;"><label class="mb-0 text-success small"><input type="radio" class="correct-choice-input" name="tf_choice_' + idx + '" value="1" checked> صح</label><label class="mb-0 text-danger small"><input type="radio" class="correct-choice-input" name="tf_choice_' + idx + '" value="0"> خطأ</label></div>';
                } else if (type === 'vote') {
                    inputHtml = '<span class="badge badge-primary"><i class="fas fa-poll"></i> ' + (idx + 1) + '</span>';
                } else if (isMultiple) {
                    inputHtml = '<input type="checkbox" class="correct-choice-input" name="correct_choices[]" value="' + idx + '" title="إجابة صحيحة">';
                } else {
                    inputHtml = '<input type="radio" class="correct-choice-input" name="correct_choice" value="' + idx + '" title="الإجابة الصحيحة">';
                }

                var div = document.createElement('div');
                div.className = 'choice-row';
                div.innerHTML =
                    '<div class="correct-choice-container">' + inputHtml + '</div>' +
                    '<input type="text" class="form-control modern-input" name="choices[' + idx + '][text]" placeholder="نص الخيار" style="flex-grow:2;">' +
                    '<input type="text" class="form-control modern-input choice-group-name" name="choices[' + idx + '][group_name]" placeholder="المجموعة" style="' + (isOrdering ? 'display:block;' : 'display:none;') + ' max-width: 20%;">' +
                    '<div class="d-flex gap-1">' +
                        '<label class="btn btn-outline-info media-btn-label" title="رفع صورة"><i class="fas fa-image"></i><input type="file" name="choices[' + idx + '][image]" class="choice-image-input d-none" accept="image/*"></label>' +
                        '<label class="btn btn-outline-info media-btn-label" title="رفع فيديو"><i class="fas fa-video"></i><input type="file" name="choices[' + idx + '][video]" class="choice-video-input d-none" accept="video/*"></label>' +
                    '</div>' +
                    '<input type="hidden" name="choices[' + idx + '][is_correct]" value="0" class="choice-correct">' +
                    '<div><button type="button" class="btn-remove-choice remove-choice" title="إزالة هذا الخيار"><i class="fas fa-trash"></i></button></div>';

                choicesContainer.appendChild(div);

                var mediaPreviewDiv = document.createElement('div');
                mediaPreviewDiv.className = 'choice-media-preview mb-3 px-3 small text-muted font-weight-bold';
                div.after(mediaPreviewDiv);

                attachChoiceListeners();
                attachMediaListeners(div);
                div.querySelector('.remove-choice').addEventListener('click', function () {
                    removeChoiceRow(div);
                });
                updateSingleChoiceHint();
            });

            // Prizes & Walking Daily Points
            var winnersCountInput = document.getElementById('winners_count');
            var prizesContainer = document.getElementById('prizesContainer');
            var existingPrizes = @json(old('prize', $quizQuestion->prize ?? []));
            if (typeof existingPrizes === 'string') existingPrizes = [existingPrizes];
            var walkingDailyPointsContainer = document.getElementById('walkingDailyPointsContainer');
            var oldDailyPoints = @json(old('walking_settings.daily_points', $dailyPts ?? []));

            function updatePrizeInputs() {
                var count = parseInt(winnersCountInput.value) || 0;
                var currentInputValues = [];
                prizesContainer.querySelectorAll('input').forEach(function (input) {
                    currentInputValues.push(input.value);
                });
                prizesContainer.innerHTML = '';
                for (var i = 0; i < count; i++) {
                    var label = i === 0 ? 'المركز الأول' : (i === 1 ? 'المركز الثاني' : (i === 2 ? 'المركز الثالث' : 'المركز ' + (i + 1)));
                    var placeholder = i === 0 ? 'مثال: آيفون 15' : 'الجائزة';
                    var val = (currentInputValues[i] !== undefined) ? currentInputValues[i] : (existingPrizes[i] || '');
                    var div = document.createElement('div');
                    div.className = 'input-group mb-2';
                    div.innerHTML = '<div class="input-group-prepend"><span class="input-group-text font-weight-bold" style="border-top-right-radius: 10px; border-bottom-right-radius: 10px; min-width: 100px;">' + label + '</span></div>' +
                        '<input type="text" name="prize[]" class="form-control modern-input" style="border-top-right-radius: 0 !important; border-bottom-right-radius: 0 !important;" placeholder="' + placeholder + '" value="' + val + '">';
                    prizesContainer.appendChild(div);
                }
            }

            function updateWalkingDailyPointsInputs() {
                if (!walkingDailyPointsContainer || !winnersCountInput) return;
                var count = parseInt(winnersCountInput.value) || 1;
                if (count < 1) count = 1;

                var countBadge = document.getElementById('walkingPointsCountNumber');
                if (countBadge) countBadge.textContent = count;

                // Collect currently typed values before re-rendering
                var currentValues = {};
                walkingDailyPointsContainer.querySelectorAll('input').forEach(function(inp) {
                    var rank = inp.getAttribute('data-rank');
                    if (rank) currentValues[rank] = inp.value;
                });

                walkingDailyPointsContainer.innerHTML = '';

                for (var pos = 1; pos <= count; pos++) {
                    var label = pos === 1 ? 'الأول 🥇' : (pos === 2 ? 'الثاني 🥈' : (pos === 3 ? 'الثالث 🥉' : 'المركز ' + pos));
                    var badgeClass = pos === 1 ? 'badge-warning' : (pos === 2 ? 'badge-secondary' : (pos === 3 ? 'badge-light border text-dark' : 'badge-light border text-muted'));
                    
                    var defaultPt = Math.max(1, (count + 2) - pos);
                    var val = (currentValues[pos] !== undefined)
                        ? currentValues[pos]
                        : ((oldDailyPoints && oldDailyPoints[pos] !== undefined) ? oldDailyPoints[pos] : defaultPt);

                    var col = document.createElement('div');
                    col.className = 'col-6 col-md-3 col-lg-2 mb-2';
                    col.innerHTML = '<div class="p-2 bg-white rounded border text-center shadow-xs" style="border-radius:12px !important;">' +
                        '<span class="badge ' + badgeClass + ' mb-1 d-block font-weight-bold py-1" style="font-size:0.85rem;">' + label + '</span>' +
                        '<input type="number" name="walking_settings[daily_points][' + pos + ']" data-rank="' + pos + '" class="form-control text-center font-weight-bold modern-input" style="font-size:1.1rem; height:40px;" value="' + val + '" min="0">' +
                        '<small class="text-muted font-weight-bold">نقاط</small>' +
                        '</div>';
                    walkingDailyPointsContainer.appendChild(col);
                }
            }

            // Walking Competition Scope Toggle (Individuals vs Groups)
            var scopeRadios = document.querySelectorAll('.walking-scope-radio');
            var groupsConfigBox = document.getElementById('groupsConfigBox');
            var individualsConfigBox = document.getElementById('individualsConfigBox');

            scopeRadios.forEach(function(radio) {
                radio.addEventListener('change', function() {
                    var isGroup = this.value === 'groups';
                    if (groupsConfigBox) groupsConfigBox.style.display = isGroup ? 'block' : 'none';
                    if (individualsConfigBox) individualsConfigBox.style.display = isGroup ? 'none' : 'block';

                    document.querySelectorAll('.walking-scope-card').forEach(function(card) {
                        var r = card.querySelector('.walking-scope-radio');
                        if (r && r.checked) {
                            card.classList.add('bg-white', 'shadow-xs');
                            card.classList.remove('bg-light');
                            if (r.value === 'groups') {
                                card.classList.add('border-primary');
                                card.classList.remove('border-success');
                            } else {
                                card.classList.add('border-success');
                                card.classList.remove('border-primary');
                            }
                        } else {
                            card.classList.remove('bg-white', 'shadow-xs', 'border-success', 'border-primary');
                            card.classList.add('bg-light');
                        }
                    });

                    if (isGroup && $.fn.select2 && $('#competingGroupSelect').length) {
                        $('#competingGroupSelect').select2({
                            placeholder: 'اختر المجموعات المتنافسة...',
                            dir: 'rtl',
                            width: '100%'
                        });
                    }
                });
            });

            if ($.fn.select2 && $('#competingGroupSelect').length) {
                $('#competingGroupSelect').select2({
                    placeholder: 'اختر المجموعات المتنافسة...',
                    dir: 'rtl',
                    width: '100%'
                });
            }

            // Scoring method toggle for daily points scheme row
            var walkingScoringMethod = document.getElementById('walkingScoringMethod');
            var dailyPointsSchemeRow = document.getElementById('dailyPointsSchemeRow');
            if (walkingScoringMethod && dailyPointsSchemeRow) {
                walkingScoringMethod.addEventListener('change', function() {
                    dailyPointsSchemeRow.style.display = this.value === 'daily_rank_points' ? 'block' : 'none';
                });
                dailyPointsSchemeRow.style.display = walkingScoringMethod.value === 'daily_rank_points' ? 'block' : 'none';
            }

            winnersCountInput.addEventListener('input', function() {
                updatePrizeInputs();
                updateWalkingDailyPointsInputs();
            });
            updatePrizeInputs();
            updateWalkingDailyPointsInputs();

            // Walking condition switch toggle
            var candWalkingEnabled = document.getElementById('cand_walking_enabled');
            var walkingConditionConfigBox = document.getElementById('walkingConditionConfigBox');
            if (candWalkingEnabled && walkingConditionConfigBox) {
                candWalkingEnabled.addEventListener('change', function () {
                    walkingConditionConfigBox.style.display = this.checked ? 'block' : 'none';
                });
            }

            // Walking days preset buttons
            var walkingPresetBtns = document.querySelectorAll('.walking-preset-btn');
            var candWalkingDaysInput = document.getElementById('cand_walking_days');

            if (walkingPresetBtns && candWalkingDaysInput) {
                walkingPresetBtns.forEach(function (btn) {
                    btn.addEventListener('click', function (e) {
                        e.preventDefault();
                        walkingPresetBtns.forEach(function (b) {
                            b.classList.remove('btn-success', 'text-white');
                            b.classList.add('btn-outline-secondary');
                        });
                        this.classList.remove('btn-outline-secondary');
                        this.classList.add('btn-success', 'text-white');

                        var days = this.getAttribute('data-days');
                        candWalkingDaysInput.value = days;
                    });
                });

                candWalkingDaysInput.addEventListener('input', function () {
                    var val = this.value;
                    walkingPresetBtns.forEach(function (b) {
                        if (b.getAttribute('data-days') === val) {
                            b.classList.remove('btn-outline-secondary');
                            b.classList.add('btn-success', 'text-white');
                        } else {
                            b.classList.remove('btn-success', 'text-white');
                            b.classList.add('btn-outline-secondary');
                        }
                    });
                });
            }

            // Candidate Scope Presets & Exclusion Switches Sync
            var candScopeBtns = document.querySelectorAll('.cand-scope-btn');
            var candScopeInput = document.getElementById('cand_scope_input');
            var swOutside = document.getElementById('cand_exclude_outside_family');
            var swMaternal = document.getElementById('cand_exclude_maternal_children');
            var swBreastfed = document.getElementById('cand_exclude_breastfed');
            var swOriginal = document.getElementById('cand_exclude_original_family');

            function updateScopeButtons(scope) {
                if (candScopeInput) candScopeInput.value = scope;
                candScopeBtns.forEach(function (btn) {
                    if (btn.getAttribute('data-scope') === scope) {
                        btn.classList.remove('btn-outline-secondary');
                        btn.classList.add('btn-success', 'text-white');
                    } else {
                        btn.classList.remove('btn-success', 'text-white');
                        btn.classList.add('btn-outline-secondary');
                    }
                });
            }

            if (candScopeBtns.length > 0 && swOutside && swMaternal && swBreastfed && swOriginal) {
                candScopeBtns.forEach(function (btn) {
                    btn.addEventListener('click', function (e) {
                        e.preventDefault();
                        var scope = this.getAttribute('data-scope');
                        updateScopeButtons(scope);

                        if (scope === 'all') {
                            swOriginal.checked = false;
                            swMaternal.checked = false;
                            swBreastfed.checked = false;
                            swOutside.checked = false;
                        } else if (scope === 'family_and_ansab') {
                            swOriginal.checked = false;
                            swMaternal.checked = false;
                            swBreastfed.checked = true;
                            swOutside.checked = true;
                        } else if (scope === 'original_family_only') {
                            swOriginal.checked = false;
                            swMaternal.checked = true;
                            swBreastfed.checked = true;
                            swOutside.checked = true;
                        } else if (scope === 'maternal_only') {
                            swOriginal.checked = true;
                            swMaternal.checked = false;
                            swBreastfed.checked = true;
                            swOutside.checked = true;
                        } else if (scope === 'breastfed_only') {
                            swOriginal.checked = true;
                            swMaternal.checked = true;
                            swBreastfed.checked = false;
                            swOutside.checked = true;
                        }
                    });
                });

                [swOutside, swMaternal, swBreastfed, swOriginal].forEach(function (sw) {
                    sw.addEventListener('change', function () {
                        var orig = swOriginal.checked;
                        var mat = swMaternal.checked;
                        var bf = swBreastfed.checked;
                        var out = swOutside.checked;

                        if (!orig && !mat && !bf && !out) {
                            updateScopeButtons('all');
                        } else if (!orig && !mat && bf && out) {
                            updateScopeButtons('family_and_ansab');
                        } else if (!orig && mat && bf && out) {
                            updateScopeButtons('original_family_only');
                        } else if (orig && !mat && bf && out) {
                            updateScopeButtons('maternal_only');
                        } else if (orig && mat && !bf && out) {
                            updateScopeButtons('breastfed_only');
                        } else {
                            updateScopeButtons('custom');
                        }
                    });
                });
            }

            // Submit logic
            document.getElementById('questionForm').addEventListener('submit', function () {
                updateHiddenValues();
            });

            // Initial calls
            attachChoiceListeners();
            attachMediaListeners();
            toggleChoices();
            toggleSortable();
            
            // Loop through and bind survey rows on load
            document.querySelectorAll('#surveyItemsContainer .survey-item-row').forEach(function (row) {
                bindSurveyRow(row);
            });

            // Walking Prediction Helpers
            window.syncPredictionRankPointsWithVote = function(selectElem) {
                if (!selectElem) return;
                var selectedOption = selectElem.options[selectElem.selectedIndex];
                var maxSlots = 5;
                if (selectedOption && selectedOption.getAttribute('data-max-selections')) {
                    maxSlots = parseInt(selectedOption.getAttribute('data-max-selections'), 10);
                }
                if (isNaN(maxSlots) || maxSlots < 1) maxSlots = 5;

                var isPredType = getSelectedAnswerType() === 'walking_prediction';
                document.querySelectorAll('.dedicated-rank-point-col').forEach(function(col) {
                    var r = parseInt(col.getAttribute('data-rank'), 10);
                    var visible = r <= maxSlots;
                    col.style.display = visible ? '' : 'none';
                    col.querySelectorAll('input').forEach(function(inp) {
                        inp.disabled = !(isPredType && visible);
                    });
                });

                var badge = document.getElementById('predictionSlotsCountBadge');
                if (badge) {
                    badge.textContent = maxSlots + ' مراكز للتصويت';
                }

                var winnersCountInput = document.getElementById('winners_count');
                if (winnersCountInput && (!winnersCountInput.value || winnersCountInput.value == '1')) {
                    winnersCountInput.value = maxSlots;
                }
            };

            window.toggleWalkingPredictionBox = function(enabled) {
                var box = document.getElementById('walkingPredictionSettingsBox');
                var select = document.getElementById('linked_walking_question_id');
                if (box) {
                    box.style.display = enabled ? 'block' : 'none';
                    box.querySelectorAll('input[name], select[name]').forEach(function(el) {
                        var col = el.closest('.rank-point-col');
                        if (col && col.style.display === 'none') {
                            el.disabled = true;
                        } else {
                            el.disabled = !enabled;
                        }
                    });
                }
                if (!enabled && select) select.value = '';
            };

            window.handleLinkedWalkingChange = function(selectEl) {
                if (!selectEl) return;
                var selectedOpt = selectEl.options[selectEl.selectedIndex];
                var gender = selectedOpt ? selectedOpt.getAttribute('data-gender') : null;
                var alertContainer = document.getElementById('linkedWalkingSmartNotice');

                if (gender === 'female') {
                    var femaleCandRadio = document.getElementById('cand_gender_female');
                    if (femaleCandRadio && !femaleCandRadio.checked) {
                        femaleCandRadio.checked = true;
                        if (typeof $ !== 'undefined') {
                            $(femaleCandRadio).trigger('change');
                        }
                    }

                    if (!alertContainer) {
                        alertContainer = document.createElement('div');
                        alertContainer.id = 'linkedWalkingSmartNotice';
                        alertContainer.className = 'alert alert-info border border-pink-300 mt-2 text-dark shadow-sm';
                        alertContainer.style.borderRadius = '14px';
                        alertContainer.style.backgroundColor = '#fdf2f8';
                        var formGroup = selectEl.closest('.form-group');
                        if (formGroup) {
                            formGroup.appendChild(alertContainer);
                        } else {
                            selectEl.parentNode.appendChild(alertContainer);
                        }
                    }
                    alertContainer.className = 'alert alert-info border border-pink-300 mt-2 text-dark shadow-sm';
                    alertContainer.style.backgroundColor = '#fdf2f8';
                    alertContainer.innerHTML = '<div class="d-flex align-items-center gap-2">' +
                        '<i class="fas fa-magic text-pink-600 mr-2" style="font-size:1.3rem;"></i>' +
                        '<div>' +
                            '<strong class="text-pink-900" style="font-size:0.95rem;">مزامنة ذكية لتحدي المشي النسائي:</strong><br>' +
                            '<span class="text-pink-800 text-sm">تم ضبط فئة المرشحين تلقائياً إلى <b>(نساء العائلة فقط)</b>. يرجى مراجعة إعدادات "الفئة المسموح لها بالتصويت" واختيار ما إذا كان التصويت متاحاً لـ <b>(الجميع رجال ونساء)</b> أو محصوراً بـ <b>(نساء فقط - حجب كامل عن الرجال والزوار)</b>.</span>' +
                        '</div>' +
                    '</div>';
                    alertContainer.style.display = 'block';
                } else if (gender === 'male') {
                    var maleCandRadio = document.getElementById('cand_gender_male');
                    if (maleCandRadio && !maleCandRadio.checked) {
                        maleCandRadio.checked = true;
                        if (typeof $ !== 'undefined') {
                            $(maleCandRadio).trigger('change');
                        }
                    }

                    if (!alertContainer) {
                        alertContainer = document.createElement('div');
                        alertContainer.id = 'linkedWalkingSmartNotice';
                        alertContainer.className = 'alert alert-info border border-blue-300 mt-2 text-dark shadow-sm';
                        alertContainer.style.borderRadius = '14px';
                        alertContainer.style.backgroundColor = '#eff6ff';
                        var formGroup = selectEl.closest('.form-group');
                        if (formGroup) {
                            formGroup.appendChild(alertContainer);
                        } else {
                            selectEl.parentNode.appendChild(alertContainer);
                        }
                    }
                    alertContainer.className = 'alert alert-info border border-blue-300 mt-2 text-dark shadow-sm';
                    alertContainer.style.backgroundColor = '#eff6ff';
                    alertContainer.innerHTML = '<div class="d-flex align-items-center gap-2">' +
                        '<i class="fas fa-magic text-blue-600 mr-2" style="font-size:1.3rem;"></i>' +
                        '<div>' +
                            '<strong class="text-blue-900" style="font-size:0.95rem;">مزامنة ذكية لتحدي المشي الرجالي:</strong><br>' +
                            '<span class="text-blue-800 text-sm">تم ضبط فئة المرشحين تلقائياً إلى <b>(رجال العائلة فقط)</b>. يرجى مراجعة إعدادات "الفئة المسموح لها بالتصويت" واختيار ما إذا كان التصويت متاحاً لـ <b>(الجميع رجال ونساء)</b> أو محصوراً بـ <b>(رجال فقط - حجب كامل عن النساء والزوار)</b>.</span>' +
                        '</div>' +
                    '</div>';
                    alertContainer.style.display = 'block';
                } else if (alertContainer) {
                    alertContainer.style.display = 'none';
                }
            };

            if (typeof $ !== 'undefined') {
                $(document).on('change select2:select', '#linked_walking_question_id, #dedicated_linked_walking_question_id', function() {
                    window.handleLinkedWalkingChange(this);
                });
            }

            var dedicatedSrcSelect = document.getElementById('dedicated_source_vote_question_id') || document.getElementById('source_vote_question_id');
            if (dedicatedSrcSelect && dedicatedSrcSelect.value) {
                window.syncPredictionRankPointsWithVote(dedicatedSrcSelect);
            }

            window.updatePredictionRankPointsVisibility = function() {
                var maxSelInput = document.getElementById('vote_max_selections');
                var maxVal = maxSelInput ? (parseInt(maxSelInput.value) || 1) : 1;
                var predSwitch = document.getElementById('enableWalkingPredictionSwitch');
                var isVotePredActive = getSelectedAnswerType() === 'vote' && predSwitch && predSwitch.checked;
                var cols = document.querySelectorAll('.rank-point-col');
                cols.forEach(function(col) {
                    var r = parseInt(col.getAttribute('data-rank'));
                    var visible = r <= maxVal;
                    col.style.display = visible ? '' : 'none';
                    col.querySelectorAll('input').forEach(function(inp) {
                        inp.disabled = !(isVotePredActive && visible);
                    });
                });
            };

            var maxSelEl = document.getElementById('vote_max_selections');
            if (maxSelEl) {
                maxSelEl.addEventListener('input', window.updatePredictionRankPointsVisibility);
                maxSelEl.addEventListener('change', window.updatePredictionRankPointsVisibility);
            }
            window.updatePredictionRankPointsVisibility();

            // Initial call for Vote Edit Duration
            var initialDuration = {{ (int) old('vote_edit_duration_hours', $quizQuestion->vote_edit_duration_hours ?? 24) }};
            if ([-1, 0, 1, 6, 12, 24, 48].indexOf(initialDuration) !== -1) {
                window.setVoteDuration(initialDuration);
            } else {
                var customInput = document.getElementById('custom_duration_input');
                if (customInput) customInput.value = initialDuration;
                window.setVoteDuration('custom');
            }
        });
    </script>
@endpush
