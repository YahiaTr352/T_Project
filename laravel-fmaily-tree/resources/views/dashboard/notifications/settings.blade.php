@extends('layouts.app')

@section('title', 'إعدادات الإشعارات')

@push('styles')
    @include('dashboard.notifications.partials.styles')
    <style>
        .settings-channel-grid {
            display: grid;
            gap: 0.75rem;
        }
        @media (min-width: 768px) {
            .settings-channel-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        .settings-test-result {
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        body:not(.dark) .settings-test-result.is-success {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }
        body.dark .settings-test-result.is-success {
            background: rgba(6, 78, 59, 0.35);
            border: 1px solid #065f46;
            color: #a7f3d0;
        }
        body:not(.dark) .settings-test-result.is-error {
            background: #fff1f2;
            border: 1px solid #fecdd3;
            color: #9f1239;
        }
        body.dark .settings-test-result.is-error {
            background: rgba(127, 29, 29, 0.35);
            border: 1px solid #881337;
            color: #fecdd3;
        }
    </style>
@endpush

@section('content')
@php
    $tokenDisplay = $hasStoredToken ? '********' : '';
    $instanceDisplay = old('ultramsg.instance_id', $ultramsg['instance_id'] ?? '') ?: ($envInstanceId ?? '');
@endphp
<div class="container-fluid py-4" style="font-family: 'Tajawal', sans-serif;">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-0.5 text-xs font-bold bg-indigo-100 dark:bg-indigo-950/80 text-indigo-800 dark:text-indigo-300 rounded-lg">
                    <i class="fas fa-sliders-h text-xs text-indigo-600 dark:text-indigo-400" aria-hidden="true"></i> مركز التحكم
                </span>
            </div>
            <h1 class="h3 font-bold notif-title m-0">إعدادات الإشعارات</h1>
            <p class="text-xs notif-subtitle m-0 mt-0.5">إدارة القنوات، مفاتيح UltraMSG، وحدود الإرسال — القنوات المعطّلة لا تظهر في صفحة الإرسال.</p>
        </div>
        <a href="{{ route('dashboard.notifications.index') }}" class="btn btn-outline-secondary btn-sm px-3 rounded-lg text-xs font-bold">
            <i class="fas fa-arrow-right mr-1" aria-hidden="true"></i> العودة للرئيسية
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-xl shadow-sm border-0 mb-4" role="alert">
            <span class="text-xs font-bold">{{ session('success') }}</span>
            <button type="button" class="close" data-dismiss="alert" aria-label="إغلاق"><span>&times;</span></button>
        </div>
    @endif

    <form action="{{ route('dashboard.notifications.settings.update') }}" method="post" id="notificationSettingsForm">
        @csrf

        {{-- Status summary --}}
        @component('dashboard.notifications.partials._settings-card', [
            'title' => 'ملخص الحالة',
            'description' => 'نظرة سريعة على آخر تحديث وحالة الاتصال.',
            'icon' => 'fas fa-tachometer-alt',
            'iconClass' => 'text-purple-600 dark:text-purple-400',
        ])
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-900/40">
                    <div class="text-[10px] notif-muted font-bold mb-1">آخر تحديث</div>
                    <div class="text-xs font-bold notif-title">{{ $meta['updated_at'] ? \Illuminate\Support\Carbon::parse($meta['updated_at'])->format('Y-m-d H:i') : '—' }}</div>
                    @if($meta['updated_by_name'])
                        <div class="text-[10px] notif-subtitle mt-0.5">بواسطة: {{ $meta['updated_by_name'] }}</div>
                    @endif
                </div>
                <div class="p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-900/40">
                    <div class="text-[10px] notif-muted font-bold mb-1">UltraMSG</div>
                    @if($tokenConfigured && filled($effectiveInstanceId))
                        @include('dashboard.notifications.partials._status-badge', ['status' => 'sent'])
                        <div class="text-[10px] notif-subtitle mt-1 font-mono">{{ $effectiveInstanceId }}</div>
                    @else
                        @include('dashboard.notifications.partials._status-badge', ['status' => 'failed'])
                        <div class="text-[10px] notif-subtitle mt-1">المفاتيح غير مكتملة</div>
                    @endif
                </div>
                <div class="p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-900/40">
                    <div class="text-[10px] notif-muted font-bold mb-1">القنوات النشطة</div>
                    @include('dashboard.notifications.partials._channels-badges', ['channels' => array_keys(array_filter($channels))])
                </div>
            </div>
        @endcomponent

        {{-- Channels --}}
        @component('dashboard.notifications.partials._settings-card', [
            'title' => 'قنوات الإرسال',
            'description' => 'عطّل أي قناة لإخفائها من صفحة /dashboard/notifications/send.',
            'icon' => 'fas fa-broadcast-tower',
            'iconClass' => 'text-emerald-600 dark:text-emerald-400',
        ])
            <div class="settings-channel-grid">
                @include('dashboard.notifications.partials._form-toggle', [
                    'name' => 'channels[whatsapp]',
                    'id' => 'channelWhatsappToggle',
                    'label' => 'واتساب (UltraMSG)',
                    'description' => 'إرسال رسائل واتساب للأعضاء.',
                    'checked' => old('channels.whatsapp', $channels['whatsapp'] ?? false),
                    'icon' => 'fab fa-whatsapp',
                    'iconClass' => 'text-emerald-600 dark:text-emerald-400',
                ])
                @include('dashboard.notifications.partials._form-toggle', [
                    'name' => 'channels[push]',
                    'id' => 'channelPushToggle',
                    'label' => 'إشعارات Push (Expo)',
                    'description' => 'إشعارات التطبيق للأجهزة المسجّلة.',
                    'checked' => old('channels.push', $channels['push'] ?? false),
                    'icon' => 'fas fa-bell',
                    'iconClass' => 'text-blue-600 dark:text-blue-400',
                ])
            </div>
        @endcomponent

        {{-- UltraMSG API --}}
        @component('dashboard.notifications.partials._settings-card', [
            'title' => 'UltraMSG API',
            'description' => 'القيم المحفوظة هنا تتجاوز إعدادات .env. اترك الرمز فارغاً للإبقاء على القيمة الحالية.',
            'icon' => 'fab fa-whatsapp',
            'iconClass' => 'text-emerald-600 dark:text-emerald-400',
        ])
            <div class="row">
                <div class="col-md-6">
                    @include('dashboard.notifications.partials._settings-input', [
                        'name' => 'ultramsg[instance_id]',
                        'id' => 'ultramsgInstanceId',
                        'label' => 'Instance ID',
                        'value' => $instanceDisplay,
                        'placeholder' => 'instance123456',
                        'help' => $envInstanceId ? 'القيمة الافتراضية من .env: ' . $envInstanceId : null,
                    ])
                </div>
                <div class="col-md-6">
                    @include('dashboard.notifications.partials._settings-input', [
                        'name' => 'ultramsg[token]',
                        'id' => 'ultramsgToken',
                        'label' => 'Token',
                        'type' => 'password',
                        'value' => old('ultramsg.token', $tokenDisplay),
                        'placeholder' => $tokenConfigured ? '******** (محفوظ)' : 'أدخل رمز UltraMSG',
                        'help' => 'لن يُعرض الرمز بعد الحفظ. اتركه فارغاً أو ******** للإبقاء على القيمة الحالية.',
                        'autocomplete' => 'new-password',
                    ])
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2 mt-1">
                <button type="button" id="testUltramsgBtn" class="btn btn-outline-primary btn-sm rounded-lg text-xs font-bold px-3">
                    <i class="fas fa-plug mr-1" aria-hidden="true"></i> اختبار الاتصال
                </button>
                <button type="button" id="toggleTokenVisibility" class="btn btn-light btn-sm rounded-lg text-xs font-bold px-3 border">
                    <i class="fas fa-eye mr-1" aria-hidden="true"></i> إظهار/إخفاء الرمز
                </button>
            </div>
            <div id="ultramsgTestResult" class="settings-test-result mt-3 hidden" role="status" aria-live="polite"></div>
        @endcomponent

        {{-- Anti-Ban WhatsApp Protection & Limits --}}
        @component('dashboard.notifications.partials._settings-card', [
            'title' => 'حدود الإرسال وحماية واتساب من الحظر',
            'description' => 'ضوابط ذكية تمنع خوارزميات ميتا (واتساب) من تصنيف رقمك كبوت وحظره، مع فواصل عشوائية وفترات تبريد.',
            'icon' => 'fas fa-shield-alt',
            'iconClass' => 'text-amber-600 dark:text-amber-400',
        ])
            {{-- Daily Quota Progress --}}
            <div class="quota-box p-3.5 rounded-xl border border-slate-200 dark:border-slate-700 mb-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full {{ $quotaStats['is_exceeded'] ? 'bg-rose-500 animate-pulse' : ($quotaStats['percentage'] > 80 ? 'bg-amber-500' : 'bg-emerald-500') }}"></span>
                        <span class="text-xs font-bold notif-title">استهلاك الحصة اليومية لواتساب</span>
                        @if($quotaStats['is_exceeded'])
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300">تم استنفاد الحصة اليومية</span>
                        @endif
                    </div>
                    <div class="text-xs font-mono font-bold text-slate-800 dark:text-slate-200">
                        {{ $quotaStats['sent_today'] }} / {{ $quotaStats['has_limit'] ? $quotaStats['limit'] : '∞' }} رسالة أُرسلت اليوم
                        @if($quotaStats['has_limit'] && $quotaStats['remaining'] !== null)
                            <span class="text-[11px] font-sans font-bold text-emerald-700 dark:text-emerald-400 mr-1.5">(متبقي: {{ $quotaStats['remaining'] }})</span>
                        @endif
                    </div>
                </div>
                <div class="w-full bg-slate-200 dark:bg-slate-700 h-2 rounded-full overflow-hidden">
                    <div class="h-full transition-all duration-500 {{ $quotaStats['is_exceeded'] ? 'bg-rose-500' : ($quotaStats['percentage'] > 80 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                         style="width: {{ $quotaStats['percentage'] }}%;"></div>
                </div>
            </div>

            {{-- Presets Grid --}}
            <div class="mb-4">
                <label class="text-xs font-bold notif-subtitle mb-2 block">
                    نمط الأمان المعتمد (Anti-Ban Presets):
                </label>
                <input type="hidden" name="limits[preset]" id="selectedPresetInput" value="{{ old('limits.preset', $limits['preset'] ?? 'safe') }}">
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    @foreach($presets as $key => $preset)
                    @php
                        $isSelected = old('limits.preset', $limits['preset'] ?? 'safe') === $key;
                    @endphp
                    <div class="preset-card relative p-3.5 rounded-xl cursor-pointer transition-all duration-200 flex flex-col justify-between {{ $isSelected ? 'is-active' : '' }}"
                         data-active="{{ $isSelected ? 'true' : 'false' }}"
                         data-preset="{{ $key }}"
                         data-min="{{ $preset['send_delay_min'] }}"
                         data-max="{{ $preset['send_delay_max'] }}"
                         data-batch="{{ $preset['batch_size'] }}"
                         data-pause="{{ $preset['batch_pause_seconds'] }}"
                         data-limit="{{ $preset['daily_limit'] }}">
                        <div class="preset-check-icon absolute -top-2 -left-2 w-5 h-5 rounded-full bg-emerald-600 text-white flex items-center justify-center text-[10px] shadow-sm {{ $isSelected ? '' : 'hidden' }}">
                            <i class="fas fa-check"></i>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold notif-title flex items-center gap-1.5">
                                    @if($key === 'safe')
                                        <i class="fas fa-shield-alt text-emerald-600 dark:text-emerald-400"></i>
                                    @elseif($key === 'warmup')
                                        <i class="fas fa-seedling text-amber-600 dark:text-amber-400"></i>
                                    @else
                                        <i class="fas fa-sliders-h text-indigo-600 dark:text-indigo-400"></i>
                                    @endif
                                    {{ $preset['name'] }}
                                </span>
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-lg {{ $preset['badge_class'] }}">
                                    {{ $preset['badge'] }}
                                </span>
                            </div>
                            <p class="text-[11px] notif-muted leading-relaxed m-0 mb-3">
                                {{ $preset['description'] }}
                            </p>
                        </div>
                        <div class="pt-2 border-t border-slate-200 dark:border-slate-700/60 flex flex-wrap gap-1.5 text-[10px] font-mono">
                            <span class="preset-chip px-2 py-0.5 rounded">
                                <i class="far fa-clock mr-0.5 text-[9px]"></i> فاصل: {{ $preset['send_delay_min'] }}-{{ $preset['send_delay_max'] }}ث
                            </span>
                            <span class="preset-chip px-2 py-0.5 rounded">
                                <i class="fas fa-layer-group mr-0.5 text-[9px]"></i> دفعة: {{ $preset['batch_size'] }}
                            </span>
                            <span class="preset-chip px-2 py-0.5 rounded">
                                <i class="fas fa-pause-circle mr-0.5 text-[9px]"></i> استراحة: {{ round($preset['batch_pause_seconds'] / 60, 1) }}د
                            </span>
                            <span class="preset-chip px-2 py-0.5 rounded">
                                <i class="fas fa-hashtag mr-0.5 text-[9px]"></i> الحد: {{ $preset['daily_limit'] ?: 'غير محدود' }}
                            </span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Custom Parameters Container --}}
            <div id="customParametersWrapper" class="custom-params-box p-3.5 rounded-xl border border-slate-200 dark:border-slate-700 mb-4 transition-all">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-sliders-h text-xs text-indigo-600 dark:text-indigo-400"></i>
                        <h4 class="text-xs font-bold notif-title m-0">تفاصيل معايير الإرسال</h4>
                    </div>
                    <span id="presetLockBadge" class="text-[10px] font-bold px-2 py-0.5 rounded-lg {{ old('limits.preset', $limits['preset'] ?? 'safe') === 'custom' ? 'bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300' : 'bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300' }}">
                        {{ old('limits.preset', $limits['preset'] ?? 'safe') === 'custom' ? 'وضع مخصص يدوي' : 'مزامنة مع الوضع المختار' }}
                    </span>
                </div>

                <div class="row">
                    <div class="col-md-3">
                        @include('dashboard.notifications.partials._settings-input', [
                            'name' => 'limits[send_delay_min]',
                            'id' => 'limitsSendDelayMin',
                            'label' => 'أدنى فاصل زمني (ثوانٍ)',
                            'type' => 'number',
                            'value' => old('limits.send_delay_min', $limits['send_delay_min'] ?? 25),
                            'min' => 1,
                            'max' => 300,
                            'help' => 'عشوائي بين الأدنى والأقصى',
                        ])
                    </div>
                    <div class="col-md-3">
                        @include('dashboard.notifications.partials._settings-input', [
                            'name' => 'limits[send_delay_max]',
                            'id' => 'limitsSendDelayMax',
                            'label' => 'أقصى فاصل زمني (ثوانٍ)',
                            'type' => 'number',
                            'value' => old('limits.send_delay_max', $limits['send_delay_max'] ?? 45),
                            'min' => 1,
                            'max' => 300,
                            'help' => 'يحاكي تردد اليد البشرية',
                        ])
                    </div>
                    <div class="col-md-3">
                        @include('dashboard.notifications.partials._settings-input', [
                            'name' => 'limits[batch_size]',
                            'id' => 'limitsBatchSize',
                            'label' => 'حجم الدفعة قبل الاستراحة',
                            'type' => 'number',
                            'value' => old('limits.batch_size', $limits['batch_size'] ?? 20),
                            'min' => 0,
                            'max' => 500,
                            'help' => '0 = بدون استراحات تبريد',
                        ])
                    </div>
                    <div class="col-md-3">
                        @include('dashboard.notifications.partials._settings-input', [
                            'name' => 'limits[batch_pause_seconds]',
                            'id' => 'limitsBatchPauseSeconds',
                            'label' => 'مدة الاستراحة (ثوانٍ)',
                            'type' => 'number',
                            'value' => old('limits.batch_pause_seconds', $limits['batch_pause_seconds'] ?? 180),
                            'min' => 0,
                            'max' => 1800,
                            'help' => 'مثال: 180 = 3 دقائق راحة',
                        ])
                    </div>
                </div>

                <div class="row pt-2 border-t border-slate-200 dark:border-slate-700/60 mt-1">
                    <div class="col-md-6">
                        @include('dashboard.notifications.partials._settings-input', [
                            'name' => 'limits[daily_limit]',
                            'id' => 'limitsDailyLimit',
                            'label' => 'الحد اليومي الكلي لواتساب',
                            'type' => 'number',
                            'value' => old('limits.daily_limit', $limits['daily_limit'] ?? 250),
                            'min' => 0,
                            'help' => '0 = غير محدود (ينصح بـ 250 للشرائح المستقرة و 50 للجديدة)',
                        ])
                    </div>
                    <div class="col-md-6 d-flex align-items-center">
                        @include('dashboard.notifications.partials._form-toggle', [
                            'name' => 'limits[auto_retry]',
                            'id' => 'limitsAutoRetry',
                            'label' => 'إعادة المحاولة التلقائية عند الفشل',
                            'description' => 'إعادة جدولة الرسائل غير المستلمة بفواصل آمنة.',
                            'checked' => old('limits.auto_retry', $limits['auto_retry'] ?? false),
                            'icon' => 'fas fa-sync-alt',
                            'iconClass' => 'text-amber-600 dark:text-amber-400',
                        ])
                    </div>
                </div>
            </div>

            {{-- Golden Anti-Ban Rules Callout --}}
            <div class="antiban-advisory-box p-3.5 rounded-xl border mb-3">
                <div class="flex items-start gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/50 flex items-center justify-center shrink-0 text-amber-700 dark:text-amber-300">
                        <i class="fas fa-lightbulb text-sm"></i>
                    </div>
                    <div class="text-xs">
                        <h5 class="font-bold mb-1">دليل الحماية الذهبي لتفادي حظر الشريحة:</h5>
                        <ul class="list-disc pr-4 space-y-1 text-[11px] leading-relaxed mb-0">
                            <li><strong>فواصل عشوائية (Randomized Jitter):</strong> إرسال الرسائل بفواصل متغيرة (25-45 ثانية) يمنع خوارزميات ميتا من رصد التردد الآلي الدقيق.</li>
                            <li><strong>تخصيص الرسائل (Anti-Hash):</strong> استخدم دائماً وسم <code>{name}</code> في نص الرسالة ليصبح لكل رسالة بصمة نصية فريدة تمنع تصنيف الحملة كرسائل مزعجة (Spam).</li>
                            <li><strong>استراحات التبريد (Cooling Pauses):</strong> التوقف التلقائي لمدة 3 دقائق كل 20 رسالة يحاكي التفاعل الطبيعي للشخص الحقيقي.</li>
                            <li><strong>رسائل التحقق الفورية (OTP):</strong> الأكواد الحساسة تُرسل فوراً عبر مسار مستقل عالي الأولوية ولا تنتظر في هذا الطابور.</li>
                        </ul>
                    </div>
                </div>
            </div>
        @endcomponent

        <div class="flex justify-end gap-2">
            <a href="{{ route('dashboard.notifications.settings') }}" class="btn btn-light btn-sm px-4 rounded-xl border text-xs font-bold">إلغاء</a>
            <button type="submit" class="btn btn-primary btn-sm px-5 rounded-xl text-xs font-bold border-0">
                <i class="fas fa-save mr-1" aria-hidden="true"></i> حفظ الإعدادات
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
$(function() {
    var testUrl = @json(route('dashboard.notifications.settings.test-ultramsg'));
    var csrf = @json(csrf_token());

    $('#toggleTokenVisibility').on('click', function() {
        var $input = $('#ultramsgToken');
        $input.attr('type', $input.attr('type') === 'password' ? 'text' : 'password');
    });

    $('#testUltramsgBtn').on('click', function() {
        var $btn = $(this);
        var $result = $('#ultramsgTestResult');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> جاري الاختبار...');
        $result.removeClass('hidden is-success is-error').text('');

        $.ajax({
            url: testUrl,
            method: 'POST',
            data: {
                _token: csrf,
                instance_id: $('#ultramsgInstanceId').val(),
                token: $('#ultramsgToken').val()
            },
            success: function(res) {
                $result.removeClass('hidden')
                    .addClass(res.success ? 'is-success' : 'is-error')
                    .text(res.message + (res.details ? ' — ' + res.details : ''));
            },
            error: function(xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'فشل اختبار الاتصال.';
                $result.removeClass('hidden is-success').addClass('is-error').text(msg);
            },
            complete: function() {
                $btn.prop('disabled', false).html('<i class="fas fa-plug mr-1"></i> اختبار الاتصال');
            }
        });
    });

    // Anti-Ban Presets Interaction
    $('.preset-card').on('click', function() {
        var $card = $(this);
        var preset = $card.data('preset');

        $('#selectedPresetInput').val(preset);

        $('.preset-card')
            .removeClass('is-active')
            .attr('data-active', 'false');
        $('.preset-card .preset-check-icon').addClass('hidden');

        $card
            .addClass('is-active')
            .attr('data-active', 'true');
        $card.find('.preset-check-icon').removeClass('hidden');

        if (preset !== 'custom') {
            $('#limitsSendDelayMin').val($card.data('min'));
            $('#limitsSendDelayMax').val($card.data('max'));
            $('#limitsBatchSize').val($card.data('batch'));
            $('#limitsBatchPauseSeconds').val($card.data('pause'));
            $('#limitsDailyLimit').val($card.data('limit'));

            $('#presetLockBadge')
                .removeClass('bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300')
                .addClass('bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300')
                .text('مزامنة مع الوضع المختار');
        } else {
            $('#presetLockBadge')
                .removeClass('bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300')
                .addClass('bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300')
                .text('وضع مخصص يدوي');
        }
    });

    // Auto-detect custom override when editing inputs
    $('#limitsSendDelayMin, #limitsSendDelayMax, #limitsBatchSize, #limitsBatchPauseSeconds, #limitsDailyLimit').on('input change', function() {
        if ($('#selectedPresetInput').val() !== 'custom') {
            $('#selectedPresetInput').val('custom');
            $('.preset-card').each(function() {
                var isCustom = $(this).data('preset') === 'custom';
                $(this).toggleClass('is-active', isCustom).attr('data-active', isCustom ? 'true' : 'false');
                $(this).find('.preset-check-icon').toggleClass('hidden', !isCustom);
            });
            $('#presetLockBadge')
                .removeClass('bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300')
                .addClass('bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300')
                .text('وضع مخصص يدوي');
        }
    });
});
</script>
@endpush
