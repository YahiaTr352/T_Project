@extends('layouts.app')

@section('title', 'إعدادات الذكاء الاصطناعي')

@push('styles')
    @include('dashboard.ai.partials.styles')
@endpush

@section('content')
@php
    $budgetLimit = (float) ($budget['monthly_usd'] ?? 0);
    $spendPct = $budgetLimit > 0 ? min(100, round(($monthSpend / $budgetLimit) * 100)) : 0;
    $activeTab = request('tab', $setupComplete ? 'usage' : 'providers');
@endphp

<div class="container-fluid py-4 ai-settings-shell">
    {{-- Header --}}
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between mb-4 gap-3">
        <div>
            <span class="badge badge-light text-violet-800 px-3 py-2 rounded-lg mb-2">
                <i class="fas fa-robot mr-1"></i> مركز الذكاء الاصطناعي
            </span>
            <h1 class="h3 font-weight-bold mb-1">إعدادات الذكاء الاصطناعي</h1>
            <p class="text-muted small mb-0">إعداد بسيط — مفتاح API، تفعيل المساعد، ومتابعة التكلفة.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <a href="{{ route('ai.ask') }}" target="_blank" class="btn btn-outline-success btn-sm rounded-lg font-weight-bold">
                <i class="fas fa-external-link-alt mr-1"></i> تجربة المساعد
            </a>
            @can('ai.settings')
            <a href="{{ route('dashboard.ai.data-quality') }}" class="btn btn-outline-secondary btn-sm rounded-lg font-weight-bold">
                <i class="fas fa-shield-alt mr-1"></i> جودة البيانات
            </a>
            @endcan
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-xl border-0 shadow-sm mb-4">
            <span class="font-weight-bold small">{{ session('success') }}</span>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger rounded-xl mb-4">
            <ul class="mb-0 small pl-3">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    @include('dashboard.ai.partials.setup-status')

    <div class="row">
        {{-- Sidebar nav --}}
        <div class="col-lg-3 mb-4 mb-lg-0">
            <div class="dashboard-notif-card sticky-top" style="top: 1rem; z-index: 10;">
                <div class="text-xs font-weight-bold notif-muted mb-3">الأقسام</div>
                <nav class="nav flex-column gap-1" role="tablist">
                    @foreach([
                        'providers' => ['icon' => 'fa-plug', 'label' => 'المزودون والمفاتيح'],
                        'features' => ['icon' => 'fa-toggle-on', 'label' => 'المميزات والميزانية'],
                        'usage' => ['icon' => 'fa-chart-line', 'label' => 'التكلفة والاستهلاك'],
                        'prompts' => ['icon' => 'fa-file-alt', 'label' => 'قوالب الرد (متقدم)'],
                    ] as $tab => $meta)
                        <a href="#tab-{{ $tab }}" role="tab"
                           class="ai-nav-link {{ $activeTab === $tab ? 'active' : '' }}"
                           aria-controls="tab-{{ $tab }}"
                           aria-selected="{{ $activeTab === $tab ? 'true' : 'false' }}">
                            <i class="fas {{ $meta['icon'] }} w-4 text-center"></i>
                            {{ $meta['label'] }}
                        </a>
                    @endforeach
                    @can('ai.generate.use')
                    <a href="#tab-tools" role="tab"
                       class="ai-nav-link {{ $activeTab === 'tools' ? 'active' : '' }}"
                       aria-controls="tab-tools"
                       aria-selected="{{ $activeTab === 'tools' ? 'true' : 'false' }}">
                        <i class="fas fa-magic w-4 text-center"></i>
                        أدوات المحتوى
                    </a>
                    @endcan
                </nav>

                <hr class="my-3">
                <div class="text-[10px] notif-muted font-bold mb-1">إنفاق الشهر</div>
                <div class="text-lg font-weight-bold notif-title">${{ number_format($monthSpend, 2) }}</div>
                <div class="text-[10px] notif-muted">من ${{ number_format($budgetLimit, 0) }}</div>
                <div class="progress mt-2" style="height:8px;border-radius:999px;">
                    <div class="progress-bar {{ $spendPct >= 90 ? 'bg-danger' : ($spendPct >= 70 ? 'bg-warning' : 'bg-success') }}"
                         style="width:{{ $spendPct }}%"></div>
                </div>
            </div>
        </div>

        {{-- Content --}}
        <div class="col-lg-9">
            <div class="tab-content">
                {{-- Providers --}}
                <div class="tab-pane fade {{ $activeTab === 'providers' ? 'show active' : '' }}" id="tab-providers">
                    <form method="post" action="{{ route('dashboard.ai.providers.update') }}">
                        @csrf
                        @component('dashboard.notifications.partials._settings-card', [
                            'title' => 'مزودو الذكاء الاصطناعي',
                            'description' => 'فعّل مزوداً واحداً على الأقل. عند فشل أحدهم ينتقل النظام تلقائياً للتالي.',
                            'icon' => 'fas fa-server',
                            'iconClass' => 'text-blue-600',
                        ])
                            @forelse($providers as $index => $provider)
                                @include('dashboard.ai.partials.provider-card', [
                                    'provider' => $provider,
                                    'index' => $index,
                                    'modelPresets' => $modelPresets,
                                    'knownDrivers' => $knownDrivers,
                                ])
                            @empty
                                <p class="text-muted small mb-0">لا يوجد مزودون. شغّل migrations أولاً.</p>
                            @endforelse
                        @endcomponent

                        @if($providers->isNotEmpty())
                            <button type="submit" class="btn btn-success btn-lg rounded-xl font-weight-bold px-5 shadow-sm">
                                <i class="fas fa-save mr-1"></i> حفظ إعدادات المزودين
                            </button>
                        @endif
                    </form>
                </div>

                {{-- Features --}}
                <div class="tab-pane fade {{ $activeTab === 'features' ? 'show active' : '' }}" id="tab-features">
                    <form method="post" action="{{ route('dashboard.ai.features.update') }}">
                        @csrf
                        @component('dashboard.notifications.partials._settings-card', [
                            'title' => 'ما الذي تريد تفعيله؟',
                            'description' => 'فعّل فقط ما تحتاجه. المساعد الذكي هو الأهم للزوار والتطبيق.',
                            'icon' => 'fas fa-sliders-h',
                            'iconClass' => 'text-emerald-600',
                        ])
                            @php
                                $primaryFeatures = ['chat', 'semantic_search', 'biography', 'story_assist', 'news_draft', 'transcription'];
                                $secondaryFeatures = array_diff(array_keys($featureMeta), $primaryFeatures);
                            @endphp
                            <div class="settings-channel-grid">
                                @foreach($primaryFeatures as $key)
                                    @if(isset($featureMeta[$key]))
                                        @include('dashboard.notifications.partials._form-toggle', [
                                            'name' => "features[{$key}][enabled]",
                                            'id' => "feat_{$key}",
                                            'label' => $featureMeta[$key]['label'],
                                            'description' => $key === 'chat' ? 'صفحة /ask + API التطبيق' : null,
                                            'checked' => (bool) ($features[$key]['enabled'] ?? false),
                                            'icon' => match($key) {
                                                'chat' => 'fas fa-comments',
                                                'semantic_search' => 'fas fa-search',
                                                'biography' => 'fas fa-user-edit',
                                                'story_assist' => 'fas fa-book-open',
                                                'news_draft' => 'fas fa-newspaper',
                                                'transcription' => 'fas fa-microphone',
                                                default => 'fas fa-star',
                                            },
                                            'iconClass' => 'text-emerald-600',
                                        ])
                                        <input type="hidden" name="features[{{ $key }}][model]" value="">
                                    @endif
                                @endforeach
                            </div>

                            @if($secondaryFeatures !== [])
                                <details class="mt-3">
                                    <summary class="text-xs font-bold notif-subtitle cursor-pointer">مميزات إضافية</summary>
                                    <div class="settings-channel-grid mt-3">
                                        @foreach($secondaryFeatures as $key)
                                            @include('dashboard.notifications.partials._form-toggle', [
                                                'name' => "features[{$key}][enabled]",
                                                'id' => "feat_{$key}",
                                                'label' => $featureMeta[$key]['label'] ?? $key,
                                                'checked' => (bool) ($features[$key]['enabled'] ?? false),
                                                'icon' => 'fas fa-cog',
                                                'iconClass' => 'text-slate-500',
                                            ])
                                            <input type="hidden" name="features[{{ $key }}][model]" value="">
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        @endcomponent

                        @component('dashboard.notifications.partials._settings-card', [
                            'title' => 'الميزانية الشهرية',
                            'description' => 'اختر سقفاً جاهزاً أو اكتب مبلغاً. عند التجاوز يتوقف AI تلقائياً.',
                            'icon' => 'fas fa-wallet',
                            'iconClass' => 'text-amber-600',
                        ])
                            <div class="d-flex flex-wrap gap-2 mb-3" id="budgetPresets">
                                @foreach($budgetPresets as $preset)
                                    <button type="button" class="ai-budget-preset {{ (float)$budget['monthly_usd'] === (float)$preset ? 'is-active' : '' }}"
                                            data-value="{{ $preset }}">${{ $preset }}</button>
                                @endforeach
                            </div>
                            @include('dashboard.notifications.partials._settings-input', [
                                'name' => 'budget[monthly_usd]',
                                'id' => 'budgetMonthlyUsd',
                                'label' => 'السقف الشهري (دولار)',
                                'type' => 'number',
                                'value' => $budget['monthly_usd'],
                                'min' => 0,
                                'step' => '0.01',
                                'help' => '0 = بدون حد (غير موصى به)',
                            ])
                            @include('dashboard.notifications.partials._form-toggle', [
                                'name' => 'budget[soft_stop]',
                                'id' => 'softStop',
                                'label' => 'إيقاف تلقائي عند تجاوز السقف',
                                'description' => 'موصى به — يحميك من فاتورة مفاجئة',
                                'checked' => (bool) ($budget['soft_stop'] ?? true),
                                'icon' => 'fas fa-hand-paper',
                                'iconClass' => 'text-rose-600',
                            ])
                            <div class="row mt-2">
                                <div class="col-md-6">
                                    @include('dashboard.notifications.partials._settings-input', [
                                        'name' => 'rate_limit[per_user_per_minute]',
                                        'id' => 'rateUser',
                                        'label' => 'حد المستخدم / دقيقة',
                                        'type' => 'number',
                                        'value' => $rateLimit['per_user_per_minute'],
                                        'min' => 1,
                                    ])
                                </div>
                                <div class="col-md-6">
                                    @include('dashboard.notifications.partials._settings-input', [
                                        'name' => 'rate_limit[per_ip_per_minute]',
                                        'id' => 'rateIp',
                                        'label' => 'حد IP / دقيقة',
                                        'type' => 'number',
                                        'value' => $rateLimit['per_ip_per_minute'],
                                        'min' => 1,
                                    ])
                                </div>
                            </div>
                        @endcomponent

                        <button type="submit" class="btn btn-success btn-lg rounded-xl font-weight-bold px-5">
                            <i class="fas fa-save mr-1"></i> حفظ المميزات والميزانية
                        </button>
                    </form>
                </div>

                {{-- Usage --}}
                <div class="tab-pane fade {{ $activeTab === 'usage' ? 'show active' : '' }}" id="tab-usage">
                    <div class="row mb-4">
                        <x-dashboard.stat-card class="col-xl-4 col-md-6" title="إنفاق الشهر" :value="'$'.number_format($monthSpend, 2)"
                            icon="fe-dollar-sign" gradient="success" :description="'من $'.number_format($budgetLimit, 0)" />
                        <x-dashboard.stat-card class="col-xl-4 col-md-6" title="الميزان المتبقي"
                            :value="'$'.number_format(max(0, $budgetLimit - $monthSpend), 2)" icon="fe-pie-chart" gradient="info" />
                        <x-dashboard.stat-card class="col-xl-4 col-md-6" title="المزودون النشطون"
                            :value="$activeProviders->count()" icon="fe-zap" gradient="purple"
                            :badge="$hasActiveProviderWithKey ? 'جاهز' : 'ناقص'" />
                    </div>

                    @component('dashboard.notifications.partials._settings-card', [
                        'title' => 'حسب الميزة (هذا الشهر)',
                        'icon' => 'fas fa-table',
                        'iconClass' => 'text-indigo-600',
                    ])
                        <div class="dashboard-table-container">
                            <table class="dashboard-table">
                                <thead><tr><th>الميزة</th><th>طلبات</th><th>توكنز</th><th>تكلفة</th></tr></thead>
                                <tbody>
                                @forelse($byFeature as $row)
                                    <tr>
                                        <td>{{ $featureMeta[$row['feature']]['label'] ?? $row['feature'] }}</td>
                                        <td>{{ $row['requests'] }}</td>
                                        <td>{{ number_format($row['tokens']) }}</td>
                                        <td>${{ number_format($row['cost'], 4) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center notif-muted py-4">لا يوجد استهلاك بعد.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endcomponent

                    @component('dashboard.notifications.partials._settings-card', [
                        'title' => 'آخر 30 يوماً',
                        'icon' => 'fas fa-calendar',
                        'iconClass' => 'text-slate-600',
                    ])
                        <div class="dashboard-table-container" style="max-height:360px;overflow:auto;">
                            <table class="dashboard-table">
                                <thead><tr><th>اليوم</th><th>توكنز</th><th>تكلفة</th></tr></thead>
                                <tbody>
                                @forelse($daily as $row)
                                    <tr>
                                        <td>{{ $row['day'] }}</td>
                                        <td>{{ number_format($row['tokens']) }}</td>
                                        <td>${{ number_format($row['cost'], 4) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center notif-muted py-4">لا بيانات.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endcomponent
                </div>

                {{-- Prompts --}}
                <div class="tab-pane fade {{ $activeTab === 'prompts' ? 'show active' : '' }}" id="tab-prompts">
                    <form method="post" action="{{ route('dashboard.ai.prompts.update') }}">
                        @csrf
                        @component('dashboard.notifications.partials._settings-card', [
                            'title' => 'قوالب الرد (للمتقدمين)',
                            'description' => 'لا تغيّرها إلا إذا كنت تعرف ما تفعل. الافتراضيات مناسبة لمعظم الحالات.',
                            'icon' => 'fas fa-file-alt',
                            'iconClass' => 'text-slate-600',
                        ])
                            @foreach($prompts as $key => $text)
                                <details class="mb-3" {{ $loop->first ? 'open' : '' }}>
                                    <summary class="text-sm font-bold notif-title cursor-pointer py-2">
                                        {{ $featureMeta[$key]['label'] ?? $key }}
                                    </summary>
                                    <textarea name="prompts[{{ $key }}]" rows="6"
                                              class="dashboard-input text-xs mt-2" dir="rtl">{{ $text }}</textarea>
                                </details>
                            @endforeach
                        @endcomponent
                        <button type="submit" class="btn btn-success rounded-xl font-weight-bold px-4">
                            <i class="fas fa-save mr-1"></i> حفظ القوالب
                        </button>
                    </form>
                </div>

                {{-- Tools --}}
                @can('ai.generate.use')
                <div class="tab-pane fade {{ $activeTab === 'tools' ? 'show active' : '' }}" id="tab-tools">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            @component('dashboard.notifications.partials._settings-card', [
                                'title' => 'مساعد القصص',
                                'description' => 'اكتب نقاطاً فقط — AI يحوّلها لقصة.',
                                'icon' => 'fas fa-book-open',
                                'iconClass' => 'text-emerald-600',
                            ])
                                <textarea id="aiStoryNotes" class="dashboard-input text-xs mb-3" rows="6"
                                          placeholder="• حدث في سنة 1980&#10;• مكان: القاهرة&#10;• الشخصيات: ..."></textarea>
                                <button type="button" class="btn btn-success btn-block rounded-lg font-weight-bold"
                                        id="aiStoryAssistBtn" data-url="{{ route('dashboard.ai.generate.story') }}">
                                    <i class="fas fa-magic mr-1"></i> توليد القصة
                                </button>
                                <div id="aiStoryOut" class="mt-3 p-3 rounded-xl border text-xs whitespace-pre-wrap d-none"></div>
                            @endcomponent
                        </div>
                        <div class="col-md-6 mb-4">
                            @component('dashboard.notifications.partials._settings-card', [
                                'title' => 'صياغة خبر أو دعوة',
                                'icon' => 'fas fa-newspaper',
                                'iconClass' => 'text-blue-600',
                            ])
                                <textarea id="aiNewsNotes" class="dashboard-input text-xs mb-3" rows="5"
                                          placeholder="نقاط الخبر أو الدعوة..."></textarea>
                                <select id="aiNewsKind" class="dashboard-input text-xs mb-3">
                                    <option value="news">خبر عائلي</option>
                                    <option value="whatsapp">دعوة واتساب قصيرة</option>
                                </select>
                                <button type="button" class="btn btn-primary btn-block rounded-lg font-weight-bold"
                                        id="aiNewsDraftBtn" data-url="{{ route('dashboard.ai.generate.news') }}">
                                    <i class="fas fa-pen mr-1"></i> صياغة النص
                                </button>
                                <div id="aiNewsOut" class="mt-3 p-3 rounded-xl border text-xs whitespace-pre-wrap d-none"></div>
                            @endcomponent
                        </div>
                    </div>
                </div>
                @endcan
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    function showAiTab(targetId) {
        if (!targetId || targetId.charAt(0) !== '#') {
            return;
        }

        document.querySelectorAll('.ai-nav-link').forEach(function (link) {
            link.classList.toggle('active', link.getAttribute('href') === targetId);
        });

        document.querySelectorAll('.ai-settings-shell .tab-content > .tab-pane').forEach(function (pane) {
            pane.classList.remove('show', 'active');
        });

        var pane = document.querySelector(targetId);
        if (pane) {
            pane.classList.add('show', 'active');
        }

        var tabKey = targetId.replace('#tab-', '');
        if (window.history && window.history.replaceState && tabKey) {
            var url = new URL(window.location.href);
            url.searchParams.set('tab', tabKey);
            window.history.replaceState(null, '', url.pathname + url.search);
        }
    }

    document.querySelectorAll('.ai-nav-link').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            showAiTab(link.getAttribute('href'));
        });
    });

    var initialTab = document.querySelector('.ai-nav-link.active');
    if (initialTab) {
        showAiTab(initialTab.getAttribute('href'));
    }
})();

document.querySelectorAll('.ai-test-btn').forEach(function (btn) {
    btn.addEventListener('click', async function () {
        const card = btn.closest('.ai-provider-card');
        const out = card.querySelector('.ai-test-result');
        btn.disabled = true;
        out.textContent = 'جاري الاختبار...';
        out.className = 'ai-test-result small mt-2 text-muted';
        try {
            const res = await fetch(btn.dataset.url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: '{}',
            });
            const data = await res.json();
            out.textContent = data.message || (data.ok ? 'نجح الاتصال' : 'فشل');
            out.className = 'ai-test-result small mt-2 ' + (data.ok ? 'is-success' : 'is-error');
        } catch (e) {
            out.textContent = 'تعذر الاتصال.';
            out.className = 'ai-test-result small mt-2 is-error';
        } finally {
            btn.disabled = false;
        }
    });
});

document.querySelectorAll('.ai-budget-preset').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('budgetMonthlyUsd').value = btn.dataset.value;
        document.querySelectorAll('.ai-budget-preset').forEach(b => b.classList.remove('is-active'));
        btn.classList.add('is-active');
    });
});

async function aiPostJson(url, body) {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(body || {}),
    });
    return res.json();
}

function showAiOutput(el, text, isError) {
    el.textContent = text;
    el.classList.remove('d-none');
    el.classList.toggle('border-danger', isError);
    el.classList.toggle('text-danger', isError);
}

document.getElementById('aiStoryAssistBtn')?.addEventListener('click', async function () {
    const out = document.getElementById('aiStoryOut');
    const notes = document.getElementById('aiStoryNotes').value.trim();
    if (!notes) { alert('اكتب نقاط القصة أولاً'); return; }
    showAiOutput(out, 'جاري التوليد...', false);
    const data = await aiPostJson(this.dataset.url, { notes });
    showAiOutput(out, data.ok ? data.generation.output : (data.message || 'فشل'), !data.ok);
});

document.getElementById('aiNewsDraftBtn')?.addEventListener('click', async function () {
    const out = document.getElementById('aiNewsOut');
    const notes = document.getElementById('aiNewsNotes').value.trim();
    if (!notes) { alert('اكتب نقاط الخبر أولاً'); return; }
    showAiOutput(out, 'جاري الصياغة...', false);
    const data = await aiPostJson(this.dataset.url, {
        notes,
        kind: document.getElementById('aiNewsKind').value,
    });
    showAiOutput(out, data.ok ? data.generation.output : (data.message || 'فشل'), !data.ok);
});

document.getElementById('aiIndexNowBtn')?.addEventListener('click', async function () {
    const btn = this;
    const msg = document.getElementById('aiIndexNowMsg');
    btn.disabled = true;
    msg.textContent = 'جاري بدء الفهرسة...';
    msg.className = 'text-xs mt-2 text-muted';
    try {
        const data = await aiPostJson(btn.dataset.url, {});
        msg.textContent = data.message || (data.ok ? 'بدأت الفهرسة.' : 'فشل البدء.');
        msg.className = 'text-xs mt-2 ' + (data.ok ? 'text-success' : 'text-danger');
    } catch (e) {
        msg.textContent = 'تعذر الاتصال.';
        msg.className = 'text-xs mt-2 text-danger';
    } finally {
        btn.disabled = false;
    }
});
</script>
@endpush
