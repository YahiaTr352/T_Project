@extends('layouts.app')

@section('title', 'لوحة التحكم | عائلة السريّع')

@section('content')
    <div class="container-fluid py-2">

        @php
            $currentHour = \Carbon\Carbon::now()->hour;
            $greeting = ($currentHour >= 5 && $currentHour < 12) ? 'صباح الخير والبركة' : 'مساء الخير والمسرات';
            $adminName = auth()->user()->name ?? 'مدير النظام';
        @endphp

        {{-- ====================================================================
             1. PREMIUM HERO SECTION (الهيرو الزجاجي الفخم)
             ==================================================================== --}}
        <x-dashboard.glass-hero
            title="{{ $greeting }}، {{ $adminName }}"
            subtitle="لوحة القيادة المركزية لشجرة وعائلة السريّع — إحصائيات متكاملة، مراقبة صحة الشجرة، ومتابعة فورية للمحتوى والأنشطة.">

            <x-slot name="actions">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    {{-- التاريخ --}}
                    <span class="badge badge-pill glass-hero-badge px-3 py-2">
                        <i class="fe fe-calendar mr-1"></i> {{ \Carbon\Carbon::now()->translatedFormat('l، d F Y') }}
                    </span>

                    {{-- رابط الشجرة المباشر --}}
                    <a href="{{ safe_route('sila') }}" class="btn btn-primary shadow-sm px-3 font-weight-bold">
                        <i class="fe fe-share-2 mr-1"></i> استعراض الشجرة
                    </a>

                    {{-- زر تحديث الكاش الفوري --}}
                    <button type="button" onclick="clearSystemCache('all', this);" class="btn btn-outline-light px-3 shadow-sm" style="backdrop-filter: blur(8px);">
                        <i class="fe fe-refresh-cw mr-1"></i> تحديث الكاش
                    </button>
                </div>
            </x-slot>

            {{-- 4 مؤشرات هيرو أساسية مدمجة --}}
            <div class="row mt-4 pt-2">
                <div class="col-xl-3 col-sm-6 mb-3 mb-xl-0">
                    <div class="d-flex align-items-center text-white">
                        <div class="mr-3 glass-hero-icon shadow-sm">
                            <i class="fe fe-users fe-18"></i>
                        </div>
                        <div>
                            <div class="h4 mb-0 font-weight-bold text-white">{{ number_format($stats['totalPeople']) }}</div>
                            <div class="small opacity-75 text-white">إجمالي أفراد العائلة</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6 mb-3 mb-xl-0">
                    <div class="d-flex align-items-center text-white">
                        <div class="mr-3 glass-hero-icon shadow-sm">
                            <i class="fe fe-heart fe-18"></i>
                        </div>
                        <div>
                            <div class="h4 mb-0 font-weight-bold text-white">{{ number_format($stats['totalMarriages']) }}</div>
                            <div class="small opacity-75 text-white">إجمالي الزيجات الموثقة</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6 mb-3 mb-xl-0">
                    <div class="d-flex align-items-center text-white">
                        <div class="mr-3 glass-hero-icon shadow-sm">
                            <i class="fe fe-layers fe-18"></i>
                        </div>
                        <div>
                            <div class="h4 mb-0 font-weight-bold text-white">{{ number_format($stats['totalGenerations']) }} أجيال</div>
                            <div class="small opacity-75 text-white">عمق الشجرة التاريخي</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6">
                    <div class="d-flex align-items-center text-white">
                        <div class="mr-3 glass-hero-icon shadow-sm">
                            <i class="fe fe-image fe-18"></i>
                        </div>
                        <div>
                            <div class="h4 mb-0 font-weight-bold text-white">{{ number_format($stats['totalImages']) }}</div>
                            <div class="small opacity-75 text-white">أرشيف الصور والوسائط</div>
                        </div>
                    </div>
                </div>
            </div>
        </x-dashboard.glass-hero>

        {{-- ====================================================================
             2. ACTION CENTER: PENDING TASKS ALERT (مركز التنبيهات الإدارية)
             ==================================================================== --}}
        @if (($stats['pendingTotal'] ?? 0) > 0)
            <div class="action-center-banner p-3 p-md-4 mb-4">
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                    <div class="d-flex align-items-center">
                        <span class="pulse-dot mr-3"></span>
                        <div>
                            <h6 class="mb-1 font-weight-bold">
                                <i class="fe fe-bell text-warning mr-1"></i>
                                تنبيه إداري: لديك {{ $stats['pendingTotal'] }} مهمة تتطلب المراجعة واتخاذ الإجراء
                            </h6>
                            <p class="small text-muted mb-0">يرجى فحص الطلبات والأنشطة المعلقة لضمان تحديث بيانات الشجرة بشكل فوري.</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        @if ($stats['pendingProfileUpdates'] > 0)
                            <a href="{{ route('dashboard.profile-updates.index') }}" class="btn btn-sm btn-warning text-dark font-weight-bold rounded-pill px-3 shadow-sm">
                                <i class="fe fe-check-square mr-1"></i> طلبات تعديل البيانات ({{ $stats['pendingProfileUpdates'] }})
                            </a>
                        @endif

                        @if ($stats['pendingRentalRequests'] > 0)
                            <a href="{{ route('my-rentals.index') }}" class="btn btn-sm btn-info text-white font-weight-bold rounded-pill px-3 shadow-sm">
                                <i class="fe fe-box mr-1"></i> طلبات الاستعارة ({{ $stats['pendingRentalRequests'] }})
                            </a>
                        @endif

                        @if ($stats['pendingImportantLinks'] > 0)
                            <a href="{{ route('dashboard.important-links.index') }}" class="btn btn-sm btn-primary text-white font-weight-bold rounded-pill px-3 shadow-sm">
                                <i class="fe fe-link mr-1"></i> اقتراحات الروابط ({{ $stats['pendingImportantLinks'] }})
                            </a>
                        @endif

                        @if ($stats['pendingInvitations'] > 0)
                            <a href="{{ route('dashboard.notifications.pending') }}" class="btn btn-sm btn-danger text-white font-weight-bold rounded-pill px-3 shadow-sm">
                                <i class="fe fe-send mr-1"></i> الدعوات المعلقة ({{ $stats['pendingInvitations'] }})
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @else
            <div class="alert alert-success bg-soft-success border-0 rounded-2xl py-2 px-3 d-flex align-items-center mb-4">
                <i class="fe fe-check-circle text-success mr-2"></i>
                <span class="small font-weight-bold">النظام يعمل بكفاءة تامة: لا توجد طلبات معلقة بانتظار المراجعة.</span>
            </div>
        @endif

        {{-- ====================================================================
             3. PRIMARY STATISTICS GRID (المؤشرات الإحصائية الرئيسية)
             ==================================================================== --}}
        <div class="row mt-n2">
            <x-dashboard.stat-card
                title="الأحياء (النفوس الحية)"
                value="{{ number_format($stats['alivePeople']) }}"
                icon="fe-heartbeat"
                gradient="success"
                badge="{{ $stats['alivePercentage'] }}%"
                description="نفوس حية تنبض بالبر والصلة"
                href="{{ route('people.index') }}" />

            <x-dashboard.stat-card
                title="المتوفين (رحمهم الله)"
                value="{{ number_format($stats['deceasedPeople']) }}"
                icon="fe-cloud-off"
                gradient="danger"
                badge="{{ $stats['deceasedPercentage'] }}%"
                description="رحمهم الله وأسكنهم فسيح جناته"
                href="{{ route('people.index') }}" />

            <x-dashboard.stat-card
                title="المضافون هذا الشهر"
                value="+{{ number_format($stats['peopleAddedThisMonth']) }}"
                icon="fe-user-plus"
                gradient="primary"
                badge="الأسبوع: +{{ $stats['peopleAddedThisWeek'] }}"
                description="توثيق مستمر لأعضاء العائلة"
                href="{{ route('people.index') }}" />

            <x-dashboard.stat-card
                title="المكرمون بالأوسمة"
                value="{{ number_format($stats['peopleWithBadges']) }}"
                icon="fe-award"
                gradient="purple"
                badge="{{ $stats['totalBadges'] }} أوسمة معتمدة"
                description="أفراد مميزون بإنجازات عائلية"
                href="{{ route('padges.index') }}" />
        </div>

        {{-- ====================================================================
             4. DEMOGRAPHICS & TREE VITALITY (لوحة التوازن السكاني وصحة الشجرة)
             ==================================================================== --}}
        <div class="row mb-4">
            <div class="col-12">
                <div class="demographics-card p-4">
                    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm mr-3" style="background: rgba(59, 130, 246, 0.12); border-radius: 10px;">
                                <i class="fe fe-pie-chart text-primary fe-18"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 font-weight-bold">التوازن الديموغرافي وجودة توثيق الشجرة</h6>
                                <p class="small text-muted mb-0">قراءة تحليلية دقيقة لتوزيع النوع الاجتماعي ومستوى اكتمال السجلات</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge badge-traffic border px-3 py-2 font-weight-bold">
                                <i class="fe fe-eye text-primary mr-1"></i> زيارات اليوم: {{ number_format($stats['todayVisits']) }}
                            </span>
                            <span class="badge badge-traffic border px-3 py-2 font-weight-bold">
                                <i class="fe fe-activity text-success mr-1"></i> إجمالي الزيارات: {{ number_format($stats['totalVisits']) }}
                            </span>
                        </div>
                    </div>

                    <div class="row align-items-center">
                        {{-- توزيع الجنسين --}}
                        <div class="col-lg-6 mb-4 mb-lg-0 border-left-lg">
                            <div class="pr-lg-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="font-weight-bold small">توزيع الجنسين (ذكور / إناث)</span>
                                    <span class="small font-weight-bold text-muted">{{ $stats['malePercentage'] }}% ذكور — {{ $stats['femalePercentage'] }}% إناث</span>
                                </div>
                                <div class="dual-segment-bar mb-3">
                                    <div class="segment-male" style="width: {{ $stats['malePercentage'] }}%;" title="ذكور: {{ $stats['malePercentage'] }}%"></div>
                                    <div class="segment-female" style="width: {{ $stats['femalePercentage'] }}%;" title="إناث: {{ $stats['femalePercentage'] }}%"></div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div class="demographic-badge male">
                                        <i class="fe fe-user"></i>
                                        <span>ذكور: {{ number_format($stats['maleCount']) }} فرد</span>
                                    </div>
                                    <div class="demographic-badge female">
                                        <i class="fe fe-user"></i>
                                        <span>إناث: {{ number_format($stats['femaleCount']) }} فرد</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- جودة واكتمال السجلات --}}
                        <div class="col-lg-6">
                            <div class="pl-lg-3">
                                <span class="font-weight-bold small d-block mb-3">مؤشرات اكتمال البيانات التوثيقية</span>

                                {{-- نسبة الصور الشخصية --}}
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span class="text-muted"><i class="fe fe-camera mr-1"></i> أفراد بصور شخصية موثقة</span>
                                        <span class="font-weight-bold">{{ $stats['photoCompleteness'] }}% ({{ number_format($stats['peopleWithPhotos']) }} فرد)</span>
                                    </div>
                                    <div class="progress" style="height: 6px; border-radius: 10px; background-color: rgba(0,0,0,0.05);">
                                        <div class="progress-bar bg-info" role="progressbar" style="width: {{ $stats['photoCompleteness'] }}%" aria-valuenow="{{ $stats['photoCompleteness'] }}" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>

                                {{-- نسبة تواريخ الميلاد --}}
                                <div>
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span class="text-muted"><i class="fe fe-calendar mr-1"></i> أفراد بتواريخ ميلاد مسجلة</span>
                                        <span class="font-weight-bold">{{ $stats['birthDateCompleteness'] }}% ({{ number_format($stats['peopleWithBirthDate']) }} فرد)</span>
                                    </div>
                                    <div class="progress" style="height: 6px; border-radius: 10px; background-color: rgba(0,0,0,0.05);">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: {{ $stats['birthDateCompleteness'] }}%" aria-valuenow="{{ $stats['birthDateCompleteness'] }}" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ====================================================================
             5. SECONDARY AT-A-GLANCE METRICS (المصفوفة الإحصائية التكميلية)
             ==================================================================== --}}
        <div class="row mb-4">
            <x-dashboard.glance-card title="مقالات عائلية" value="{{ number_format($stats['totalArticles']) }}" icon="fe-file-text" color="info" />
            <x-dashboard.glance-card title="برامج نشطة" value="{{ number_format($stats['activePrograms']) }}" icon="fe-play-circle" color="success" />
            <x-dashboard.glance-card title="فعاليات ومناسبات" value="{{ number_format($stats['activeEvents']) }}" icon="fe-calendar" color="warning" />
            <x-dashboard.glance-card title="مجالس العائلة" value="{{ number_format($stats['totalCouncils']) }}" icon="fe-home" color="danger" />
            <x-dashboard.glance-card title="قصص وتاريخ" value="{{ number_format($stats['totalStories']) }}" icon="fe-book-open" color="primary" />
            <x-dashboard.glance-card title="زيجات هذا الشهر" value="{{ number_format($stats['marriagesThisMonth']) }}" icon="fe-heart" color="danger" />
        </div>

        {{-- ====================================================================
             6. MAIN CONTENT COLUMNS (العمود الرئيسي + الشريط الجانبي التفاعلي)
             ==================================================================== --}}
        <div class="row">
            {{-- العمود الرئيسي الأيمن (8 أعمدة) --}}
            <div class="col-lg-8">

                {{-- أ. حدث في مثل هذا اليوم في تاريخ العائلة --}}
                <div class="card shadow-sm border-0 rounded-2xl mb-4 overflow-hidden">
                    <div class="card-header bg-primary text-white border-0 py-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <h6 class="mb-0 font-weight-bold text-white">
                                <i class="fe fe-zap mr-2"></i> حدث في مثل هذا اليوم
                            </h6>
                            <span class="badge badge-pill bg-primary-subtle-badge px-3 py-1 font-weight-bold">
                                {{ \Carbon\Carbon::now()->translatedFormat('d F') }}
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-3">
                        @if ($events['birthsToday']->isEmpty() && $events['marriagesToday']->isEmpty())
                            <div class="dash-empty-state">
                                <div class="dash-empty-state-icon">
                                    <i class="fe fe-calendar"></i>
                                </div>
                                <h6 class="font-weight-bold mb-1">لا توجد مناسبات عائلية مسجلة لهذا اليوم</h6>
                                <p class="text-muted small mb-0">يتم فحص وتحديث هذا القسم يومياً بشكل تلقائي استناداً إلى تواريخ الميلاد وعقود الزواج.</p>
                            </div>
                        @else
                            @foreach ($events['birthsToday'] as $person)
                                <x-dashboard.activity-item
                                    title="ذكرى ميلاد: {{ $person->full_name }}"
                                    subtitle="وُلد في عام {{ $person->birth_date->year }} (قبل {{ $person->birth_date->age }} سنة)"
                                    icon="fe-gift"
                                    iconBg="warning"
                                    href="{{ route('people.show', $person) }}"
                                    badge="ذكرى ميلاد" />
                            @endforeach

                            @foreach ($events['marriagesToday'] as $marriage)
                                <x-dashboard.activity-item
                                    title="ذكرى زواج: {{ $marriage->husband->full_name ?? 'الزوج' }} و {{ $marriage->wife->full_name ?? 'الزوجة' }}"
                                    subtitle="عُقد القران في عام {{ $marriage->married_at->year ?? '—' }} (قبل {{ $marriage->married_at ? $marriage->married_at->diffInYears(\Carbon\Carbon::now()) : '—' }} سنة)"
                                    icon="fe-heart"
                                    iconBg="danger"
                                    badge="ذكرى زواج" />
                            @endforeach
                        @endif
                    </div>
                </div>

                {{-- ب. منصة الإطلاق السريعة (Launchpad) --}}
                <div class="card shadow-sm border-0 rounded-2xl mb-4">
                    <div class="card-header bg-transparent border-0 py-3 d-flex align-items-center justify-content-between">
                        <h6 class="mb-0 font-weight-bold">
                            <i class="fe fe-grid mr-2 text-primary"></i> منصة الإجراءات السريعة
                        </h6>
                        <span class="small text-muted font-weight-600">اختصارات فورية</span>
                    </div>
                    <div class="card-body pt-0">
                        <div class="row g-3">
                            {{-- إضافة شخص جديد --}}
                            <div class="col-md-4 col-6 mb-3">
                                <a href="{{ route('people.create') }}" class="dash-quick-action">
                                    <div class="action-icon-circle bg-soft-primary text-primary">
                                        <i class="fe fe-user-plus"></i>
                                    </div>
                                    <div class="action-label">إضافة شخص</div>
                                    <div class="action-desc">تسجيل فرد جديد في الشجرة</div>
                                </a>
                            </div>

                            {{-- إضافة خبر جديد --}}
                            <div class="col-md-4 col-6 mb-3">
                                <a href="{{ route('dashboard.family-news.create') }}" class="dash-quick-action">
                                    <div class="action-icon-circle bg-soft-success text-success">
                                        <i class="fe fe-plus-circle"></i>
                                    </div>
                                    <div class="action-label">نشر خبر</div>
                                    <div class="action-desc">إضافة خبر أو إعلان عائلي</div>
                                </a>
                            </div>

                            {{-- المعرض ومكتبة الصور --}}
                            <div class="col-md-4 col-6 mb-3">
                                <a href="{{ route('dashboard.images.index') }}" class="dash-quick-action">
                                    <div class="action-icon-circle bg-soft-info text-info">
                                        <i class="fe fe-camera"></i>
                                    </div>
                                    <div class="action-label">معرض الصور</div>
                                    <div class="action-desc">إدارة الألبومات والوسائط</div>
                                </a>
                            </div>

                            {{-- مركز الإشعارات والتعاميم --}}
                            <div class="col-md-4 col-6 mb-3">
                                <a href="{{ route('dashboard.notifications.index') }}" class="dash-quick-action">
                                    <div class="action-icon-circle bg-soft-warning text-warning">
                                        <i class="fe fe-bell"></i>
                                    </div>
                                    <div class="action-label">مركز الإشعارات</div>
                                    <div class="action-desc">إرسال تعاميم ورسائل</div>
                                </a>
                            </div>

                            {{-- سجل الزيارات والنشاط --}}
                            <div class="col-md-4 col-6 mb-3">
                                <a href="{{ route('dashboard.visit-logs.index') }}" class="dash-quick-action">
                                    <div class="action-icon-circle bg-soft-danger text-danger">
                                        <i class="fe fe-activity"></i>
                                    </div>
                                    <div class="action-label">سجل الزيارات</div>
                                    <div class="action-desc">تحليل النشاط والمشاهدات</div>
                                </a>
                            </div>

                            {{-- تحديث الكاش الذكي --}}
                            <div class="col-md-4 col-6 mb-3">
                                <a href="{{ route('dashboard.clear-cache') }}" onclick="event.preventDefault(); clearSystemCache('all', this);" class="dash-quick-action">
                                    <div class="action-icon-circle bg-soft-secondary text-secondary">
                                        <i class="fe fe-refresh-cw"></i>
                                    </div>
                                    <div class="action-label">تفريغ الكاش</div>
                                    <div class="action-desc">تحسين الأداء وتحديث العرض</div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ج. أرقام قياسية عائلية (هل تعلم؟) --}}
                <div class="mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="mb-0 font-weight-bold">
                            <i class="fe fe-award mr-2 text-warning"></i> أرقام قياسية وشخصيات مميزة (هل تعلم؟)
                        </h6>
                    </div>
                    <div class="row">
                        @if ($funFact['oldestPerson'])
                            <div class="col-md-4 mb-3">
                                <div class="bento-record highlight">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <span class="small font-weight-bold opacity-80">أكبر معمر سناً</span>
                                        <i class="fe fe-sun h4 mb-0 opacity-75"></i>
                                    </div>
                                    <div>
                                        <h5 class="font-weight-bold mb-1">{{ $funFact['oldestPerson']->full_name }}</h5>
                                        <p class="small mb-3 opacity-80">
                                            @if($funFact['oldestPerson']->birth_date)
                                                مواليد {{ $funFact['oldestPerson']->birth_date->format('Y/m/d') }}
                                            @endif
                                        </p>
                                    </div>
                                    <div>
                                        <span class="badge badge-pill bento-highlight-badge font-weight-bold">
                                            {{ $funFact['oldestPerson']->birth_date ? $funFact['oldestPerson']->birth_date->age . ' عاماً — حفظه الله' : 'مبارك' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if ($funFact['personWithMostChildren'])
                            <div class="col-md-4 mb-3">
                                <div class="bento-record">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <span class="small font-weight-bold text-muted">الأكثر ذريّة</span>
                                        <i class="fe fe-users h4 mb-0 text-info opacity-75"></i>
                                    </div>
                                    <div>
                                        <h5 class="font-weight-bold mb-1">{{ $funFact['personWithMostChildren']->full_name }}</h5>
                                        <p class="small text-muted mb-3">بارك الله في نسله وذريته</p>
                                    </div>
                                    <div>
                                        <span class="badge badge-pill badge-soft-info font-weight-bold px-3 py-2">
                                            {{ $funFact['personWithMostChildren']->children_count }} ابناً وابنة
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if (!empty($funFact['marriageFact']))
                            @php $mf = $funFact['marriageFact']; @endphp
                            <div class="col-md-4 mb-3">
                                <div class="bento-record">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <span class="small font-weight-bold text-muted">{{ $mf['title'] }}</span>
                                        <i class="fe {{ $mf['icon'] }} h4 mb-0 {{ $mf['iconColor'] }} opacity-75"></i>
                                    </div>
                                    <div>
                                        <h5 class="font-weight-bold mb-1 text-truncate">{{ $mf['husband'] }}</h5>
                                        <p class="small text-muted mb-3 text-truncate">و {{ $mf['wife'] }}</p>
                                    </div>
                                    <div>
                                        <span class="badge badge-pill {{ $mf['badgeClass'] }} font-weight-bold px-3 py-2">
                                            {{ $mf['badge'] }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @elseif ($funFact['latestMarriage'])
                            <div class="col-md-4 mb-3">
                                <div class="bento-record">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <span class="small font-weight-bold text-muted">عقد قران</span>
                                        <i class="fe fe-heart h4 mb-0 text-danger opacity-75"></i>
                                    </div>
                                    <div>
                                        <h5 class="font-weight-bold mb-1 text-truncate">{{ $funFact['latestMarriage']->husband->full_name ?? 'العريس' }}</h5>
                                        <p class="small text-muted mb-3 text-truncate">و {{ $funFact['latestMarriage']->wife->full_name ?? 'العروس' }}</p>
                                    </div>
                                    <div>
                                        <span class="badge badge-pill badge-soft-danger font-weight-bold px-3 py-2">
                                            {{ $funFact['latestMarriage']->married_at ? $funFact['latestMarriage']->married_at->diffForHumans() : 'مبارك' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- العمود الأيسر الجانبي (4 أعمدة) --}}
            <div class="col-lg-4">

                {{-- 1. أضيفوا مؤخراً إلى الشجرة --}}
                <div class="card shadow-sm border-0 rounded-2xl mb-4 overflow-hidden">
                    <div class="card-header bg-transparent border-0 py-3 d-flex align-items-center justify-content-between">
                        <h6 class="mb-0 font-weight-bold">
                            <i class="fe fe-user-check mr-2 text-success"></i> أضيفوا مؤخراً
                        </h6>
                        <a href="{{ route('people.index') }}" class="small text-primary font-weight-bold">استعراض الكل</a>
                    </div>
                    <div class="card-body p-2 pt-0">
                        @forelse ($recentlyAdded as $person)
                            <x-dashboard.activity-item
                                title="{{ $person->full_name }}"
                                subtitle="{{ $person->gender === 'female' ? 'أنثى' : 'ذكر' }} — الجيل {{ $person->depth ?? 1 }}"
                                time="{{ $person->created_at ? $person->created_at->diffForHumans() : '' }}"
                                icon="fe-user"
                                iconBg="{{ $person->gender === 'female' ? 'warning' : 'info' }}"
                                href="{{ route('people.show', $person) }}" />
                        @empty
                            <div class="text-center py-4 text-muted small">لم تُضف بيانات مؤخراً</div>
                        @endforelse
                    </div>
                </div>

                {{-- 2. أيام ميلاد قادمة هذا الشهر --}}
                <div class="card shadow-sm border-0 rounded-2xl mb-4 overflow-hidden border-top-warning">
                    <div class="card-header bg-transparent border-0 py-3 d-flex align-items-center justify-content-between">
                        <h6 class="mb-0 font-weight-bold text-warning">
                            <i class="fe fe-gift mr-2"></i> أعياد ميلاد قادمة
                        </h6>
                        <span class="badge badge-pill badge-soft-warning font-weight-bold">هذا الشهر</span>
                    </div>
                    <div class="card-body p-2 pt-0">
                        @forelse ($upcomingBirthdays as $person)
                            @php
                                $badgeText = '';
                                if ($person->birth_date->isToday()) {
                                    $badgeText = 'اليوم!';
                                } elseif ($person->birth_date->isTomorrow()) {
                                    $badgeText = 'غداً';
                                } else {
                                    $daysLeft = \Carbon\Carbon::now()->diffInDays($person->birth_date->copy()->year(\Carbon\Carbon::now()->year), false);
                                    $badgeText = $daysLeft > 0 ? 'خلال ' . $daysLeft . ' يوم' : '';
                                }
                            @endphp
                            <x-dashboard.activity-item
                                title="{{ $person->full_name }}"
                                subtitle="{{ $person->birth_date->translatedFormat('d F') }} (يكمل {{ $person->birth_date->age + 1 }} سنة)"
                                icon="fe-gift"
                                iconBg="warning"
                                href="{{ route('people.show', $person) }}"
                                badge="{{ $badgeText }}" />
                        @empty
                            <div class="text-center py-4 text-muted small">لا توجد أعياد ميلاد قادمة هذا الشهر</div>
                        @endforelse
                    </div>
                </div>

                {{-- 3. حالة وجاهزية النظام (System Status) --}}
                <div class="card shadow-sm border-0 rounded-2xl mb-4 p-4 status-card">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="mb-0 font-weight-bold">
                            <i class="fe fe-shield text-success mr-2"></i> حالة وجاهزية النظام
                        </h6>
                        <span class="badge badge-pill badge-soft-success font-weight-bold">مستقر 100%</span>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">صحة اتصال قاعدة البيانات</span>
                            <span class="text-success font-weight-bold">متصلة (MySQL)</span>
                        </div>
                        <div class="progress" style="height: 5px; border-radius: 10px; background-color: rgba(0,0,0,0.05);">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">أداء واستجابة التخزين المؤقت (Cache)</span>
                            <span class="text-primary font-weight-bold">نشط ومحدث</span>
                        </div>
                        <div class="progress" style="height: 5px; border-radius: 10px; background-color: rgba(0,0,0,0.05);">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: 95%" aria-valuenow="95" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>

                    <div class="pt-2 border-top mt-3">
                        <a href="{{ route('dashboard.settings.index') }}" class="btn btn-sm btn-outline-primary btn-block rounded-pill font-weight-bold">
                            <i class="fe fe-settings mr-1"></i> إعدادات المنصة المتقدمة
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- Script مسح التخزين المؤقت التفاعلي --}}
    <script>
    function clearSystemCache(type, btn) {
        if (!confirm('هل أنت متأكد من رغبتك في تفريغ وتحديث التخزين المؤقت للنظام؟')) return;

        const originalBtnContent = btn.innerHTML;
        btn.innerHTML = '<i class="fe fe-loader fe-spin mr-1"></i> جاري التحديث...';
        btn.style.pointerEvents = 'none';

        fetch('{{ route("dashboard.clear-cache") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ type: type })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                window.location.reload();
            } else {
                alert(data.message || 'حدث خطأ أثناء تفريغ الكاش');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('حدث خطأ في الاتصال بالخادم');
        })
        .finally(() => {
            btn.innerHTML = originalBtnContent;
            btn.style.pointerEvents = 'auto';
        });
    }
    </script>
@endsection

