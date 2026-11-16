@php
    $steps = [
        [
            'done' => $hasActiveProviderWithKey,
            'icon' => 'fa-key',
            'title' => '1. فعّل مزوداً وأضف المفتاح',
            'hint' => 'اختر OpenAI أو Gemini، الصق المفتاح، واضغط «اختبار الاتصال».',
        ],
        [
            'done' => $chatEnabled,
            'icon' => 'fa-comments',
            'title' => '2. فعّل المساعد الذكي',
            'hint' => 'من تبويب «المميزات» فعّل «اسأل شجرة العائلة».',
        ],
        [
            'done' => $setupComplete,
            'icon' => 'fa-check-circle',
            'title' => '3. جاهز للاستخدام',
            'hint' => $setupComplete
                ? 'يمكن للزوار استخدام /ask والتطبيق يتصل بالـ API.'
                : 'أكمل الخطوتين السابقتين.',
        ],
        [
            'done' => $embeddingsCount > 0,
            'icon' => 'fa-database',
            'title' => '4. فهرسة المحتوى (للبحث الأدق)',
            'hint' => $embeddingsCount > 0
                ? "تم فهرسة {$embeddingsCount} عنصراً."
                : 'اختياري: حسّن جودة الإجابات بالبحث الدلالي.',
        ],
    ];
@endphp

@component('dashboard.notifications.partials._settings-card', [
    'title' => $setupComplete ? 'الإعداد مكتمل — كل شيء جاهز' : 'الإعداد السريع (4 خطوات)',
    'description' => $setupComplete
        ? 'المساعد الذكي يعمل. راقب الاستهلاك من تبويب «التكلفة».'
        : 'اتبع الخطوات بالترتيب. لا تحتاج خبرة تقنية — فقط مفتاح API من المزود.',
    'icon' => $setupComplete ? 'fas fa-check-circle' : 'fas fa-magic',
    'iconClass' => $setupComplete ? 'text-emerald-600' : 'text-amber-600',
])
    <div class="row">
        @foreach($steps as $step)
            <div class="col-md-6 col-lg-3 mb-3">
                <div class="ai-setup-step {{ $step['done'] ? 'is-done' : ($loop->first && !$step['done'] ? 'is-pending' : '') }}">
                    <div class="d-flex align-items-start gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width:36px;height:36px;background:{{ $step['done'] ? '#10b981' : '#94a3b8' }};color:#fff;">
                            <i class="fas {{ $step['done'] ? 'fa-check' : $step['icon'] }} text-sm"></i>
                        </div>
                        <div>
                            <div class="text-sm font-bold notif-title">{{ $step['title'] }}</div>
                            <p class="text-[11px] notif-muted mb-0 mt-1 leading-relaxed">{{ $step['hint'] }}</p>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    @unless($setupComplete)
        <div class="mt-3 p-3 rounded-xl border border-amber-200 bg-amber-50 text-xs">
            <strong>تلميح:</strong> للمزودين الجدد استخدم
            <code>gemini-3.6-flash</code> أو
            <code>gpt-5.6-luna</code> أو
            <code>claude-haiku-4-5</code>.
            لو ظهر خطأ حصة (429) جرّب موديلاً اقتصادياً أو فعّل مزوداً احتياطياً.
        </div>
    @endunless

    @if($setupComplete && $embeddingsCount === 0)
        <div class="mt-3 p-3 rounded-xl border border-blue-200 bg-blue-50 text-xs d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
            <div>
                <strong>تحسين دقة الإجابات:</strong>
                المساعد يعمل الآن، لكن فهرسة المحتوى تضيف «بحثاً دلالياً» دقيقاً.
                (سيستخدم حتى بدونها البحث النصي والإحصائيات.)
            </div>
            <button type="button" id="aiIndexNowBtn" class="btn btn-sm btn-outline-primary rounded-lg font-bold shrink-0"
                    data-url="{{ route('dashboard.ai.index-now') }}">
                <i class="fas fa-sync-alt mr-1"></i> بدء الفهرسة الآن
            </button>
        </div>
        <div id="aiIndexNowMsg" class="text-xs mt-2"></div>
    @endif
@endcomponent
