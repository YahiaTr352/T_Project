@extends('web-site.invitations.layout')

@section('title', 'إرسال دعوة')

@section('extra_styles')
    @include('web-site.invitations.partials.typography-fonts')
    .invitation-typed-text {
        font-family: 'Invitation Amiri', Amiri, serif;
        font-weight: 700;
    }
    .inv-tpl-card {
        position: relative;
        height: 8.5rem;
        border-radius: 1.15rem;
        overflow: hidden;
        border: 1.5px solid var(--inv-border);
        transition: transform 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
    }
    .inv-tpl-card:hover {
        transform: translateY(-2px);
    }
    .inv-tpl-card.is-active {
        border: 2.5px solid var(--inv-primary);
        box-shadow: 0 10px 24px rgba(25, 132, 74, 0.22);
    }
    .inv-tpl-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(10,36,24,0.08) 0%, rgba(10,36,24,0.34) 45%, rgba(9,31,21,0.88) 100%);
        padding: 0.6rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .inv-format-pill {
        display: inline-flex;
        align-items: center;
        padding: 0.22rem;
        border-radius: 9999px;
        background: var(--inv-toolbar-bg);
        gap: 0.15rem;
    }
    .inv-format-btn {
        width: 2rem;
        height: 1.7rem;
        border-radius: 9999px;
        color: #ffffff;
        font-size: 0.85rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: background 0.15s ease;
    }
    .inv-format-btn.is-active,
    .inv-format-btn:hover {
        background: var(--inv-toolbar-active);
    }
@endsection

@section('content')
    @php
        $visualMeta = [
            'event_invitation' => ['img' => 'images/invitations/event-invitation.jpg', 'icon' => 'fa-calendar-days'],
            'wedding'          => ['img' => 'images/invitations/wedding.jpg',          'icon' => 'fa-ring'],
            'reminder'         => ['img' => 'images/invitations/reminder.jpg',         'icon' => 'fa-bell'],
            'greeting'         => ['img' => 'images/invitations/greeting.jpg',         'icon' => 'fa-wand-magic-sparkles'],
        ];
        $pillDots = [
            'announcement' => '#19844a',
            'general'      => '#3b82f6',
            'condolences'  => '#64748b',
            'urgent_alert' => '#ef4444',
        ];
        $visualTemplates = collect($templates)->filter(fn ($t) => isset($visualMeta[$t['key']]))->values();
        $secondaryTemplates = collect($templates)->reject(fn ($t) => isset($visualMeta[$t['key']]))->values();
    @endphp

    <form id="invitationForm" action="{{ route('invitations.send.submit') }}" method="POST" enctype="multipart/form-data"
        class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        @csrf

        <div class="lg:col-span-8 space-y-5">
            {{-- ١. نوع الدعوة --}}
            <section class="space-y-3">
                <div class="inv-section-heading">
                    <span class="inv-section-title"><span class="inv-dot"></span>نوع الدعوة</span>
                    <span class="text-xs inv-muted font-medium">اختر القالب المناسب</span>
                </div>

                <select id="template_select" class="sr-only" aria-label="نوع الدعوة">
                    <option value="">— رسالة مخصصة —</option>
                    @foreach($templates as $tpl)
                        <option value="{{ $tpl['key'] }}" @selected(old('template_key') === $tpl['key'])>{{ $tpl['name'] }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="template_key" id="template_key" value="{{ old('template_key') }}">

                @if($visualTemplates->isNotEmpty())
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                        @foreach($visualTemplates as $tpl)
                            @php $meta = $visualMeta[$tpl['key']]; @endphp
                            <button type="button" data-tpl-card="{{ $tpl['key'] }}"
                                class="inv-tpl-card text-center cursor-pointer {{ old('template_key') === $tpl['key'] ? 'is-active' : '' }}">
                                <img src="{{ asset($meta['img']) }}" alt="{{ $tpl['name'] }}" class="w-full h-full object-cover">
                                <span class="inv-tpl-overlay">
                                    <span class="flex items-center justify-between">
                                        <span class="w-7 h-7 rounded-xl flex items-center justify-center text-xs shadow-sm"
                                              data-tpl-icon
                                              style="background: rgba(255,255,255,0.92); color: #0f241c;">
                                            <i class="fas {{ $meta['icon'] }}" aria-hidden="true"></i>
                                        </span>
                                        <span class="w-5 h-5 rounded-full bg-white text-emerald-700 items-center justify-center text-[10px] shadow {{ old('template_key') === $tpl['key'] ? 'inline-flex' : 'hidden' }}"
                                              data-tpl-check>
                                            <i class="fas fa-check" aria-hidden="true"></i>
                                        </span>
                                    </span>
                                    <span class="text-white font-extrabold text-xs sm:text-sm leading-tight">{{ $tpl['name'] }}</span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                @endif

                @if($secondaryTemplates->isNotEmpty())
                    <div class="flex flex-wrap gap-2">
                        @foreach($secondaryTemplates as $tpl)
                            @php $dotColor = $pillDots[$tpl['key']] ?? '#19844a'; @endphp
                            <button type="button" data-tpl-card="{{ $tpl['key'] }}"
                                class="inline-flex items-center gap-2 px-3.5 py-2 rounded-full text-xs font-bold inv-card hover:opacity-90 transition cursor-pointer">
                                <span class="w-2 h-2 rounded-full shrink-0" style="background: {{ $dotColor }}"></span>
                                <span>{{ $tpl['name'] }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- ٢. تفاصيل الدعوة --}}
            <section class="inv-card rounded-3xl p-5 sm:p-6 relative overflow-hidden">
                <div class="absolute top-0 inset-x-8 h-1 rounded-b-full" style="background: var(--inv-primary-dark);"></div>

                <div class="inv-section-heading mt-1">
                    <span class="inv-section-title"><span class="inv-dot"></span>تفاصيل الدعوة</span>
                </div>

                <div class="space-y-4">
                    <div>
                        <label for="title" class="block text-xs font-bold inv-muted mb-1.5">العنوان</label>
                        <input type="text" name="title" id="title" value="{{ old('title') }}"
                            placeholder="دعوة لحضور مناسبة عائلية"
                            class="invitation-typed-text inv-input w-full px-4 py-3 text-sm sm:text-base">
                    </div>

                    <div>
                        <div class="flex items-center justify-between gap-2 mb-1.5">
                            <label for="body" class="text-xs font-bold inv-muted">نص الرسالة · تنسيق النص</label>
                            <div class="inv-format-pill" dir="ltr">
                                <button type="button" data-format-mark="*" class="inv-format-btn is-active" title="عريض">B</button>
                                <button type="button" data-format-mark="_" class="inv-format-btn italic" title="مائل">I</button>
                                <button type="button" data-format-mark="~" class="inv-format-btn line-through" title="يتوسطه خط">S</button>
                            </div>
                        </div>
                        <textarea name="body" id="body" rows="6" placeholder="النص اختياري عند اختيار صورة"
                            class="invitation-typed-text inv-input w-full px-4 py-3 text-sm sm:text-base">{{ old('body') }}</textarea>
                        <div class="flex flex-wrap gap-1.5 mt-2">
                            @foreach(['{name}', '{full_name}', '{sender_name}'] as $tok)
                                <button type="button" data-insert-token="{{ $tok }}" class="inv-pill cursor-pointer">{{ $tok }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>

            {{-- ٣. تفاصيل القالب --}}
            <fieldset id="templateVarSection" class="space-y-3" hidden>
                <div class="inv-section-heading mb-1">
                    <legend class="inv-section-title"><span class="inv-dot"></span>تفاصيل القالب</legend>
                    <span id="templateVarBadge" class="inv-pill">
                        <i class="fas fa-sync-alt text-[11px]" aria-hidden="true"></i>
                        <span>متغيرات القالب</span>
                    </span>
                </div>
                <div id="templateVars" class="grid grid-cols-1 gap-3 sm:grid-cols-2"></div>
            </fieldset>

            {{-- ٤. الصورة والتصميم --}}
            <section class="inv-card rounded-3xl p-5 sm:p-6 space-y-4">
                <div class="inv-section-heading mb-1">
                    <span class="inv-section-title"><span class="inv-dot"></span>الصورة والتصميم</span>
                </div>

                <input type="hidden" name="creative_id" id="creative_id" value="{{ old('creative_id') }}">
                <span id="preparedArtworkPreview" hidden></span>
                <input type="hidden" name="creative_edit_source" id="creativeArtworkSource"
                    value="{{ is_string(old('creative_edit_source')) ? old('creative_edit_source') : '' }}">
                <input type="hidden" name="background_id" id="background_id" value="{{ old('background_id') }}">

                <button type="button" id="creativeOpen" aria-haspopup="dialog" aria-controls="creativeDialog" style="display:none"
                    class="inv-btn-primary w-full flex items-center gap-3 p-4 text-right cursor-pointer">
                    <span class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0">
                        <i class="fas fa-palette text-lg text-white" aria-hidden="true"></i>
                    </span>
                    <span class="flex-1">
                        <span class="block font-extrabold text-sm">قوالب الصور</span>
                        <span id="creativeSelectionLabel" class="block text-xs text-emerald-50/90 mt-0.5">اختر قالبًا للدعوة</span>
                    </span>
                    <i class="fas fa-chevron-left text-sm" aria-hidden="true"></i>
                </button>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" id="preparedArtworkOpen" aria-haspopup="dialog" aria-controls="preparedArtworkDialog"
                        class="inv-pill px-4 py-2 text-xs cursor-pointer" hidden>
                        <i class="fas fa-pen ml-1" aria-hidden="true"></i> تعديل كتابة القالب
                    </button>
                    <button type="button" id="creativeClear" class="text-xs font-bold underline inv-muted cursor-pointer" hidden>إزالة قالب الصورة</button>
                </div>

                <dialog id="creativeDialog" aria-labelledby="creativeDialogTitle" dir="rtl"
                    class="inv-dialog rounded-3xl p-6 shadow-2xl m-auto" style="width:min(92vw,720px);max-height:85vh;overflow:auto">
                    <div class="flex items-center justify-between gap-4 mb-3">
                        <h3 id="creativeDialogTitle" class="font-extrabold text-xl" style="color: var(--inv-text)">قوالب الصور</h3>
                        <button type="button" id="creativeClose" aria-label="إغلاق قوالب الصور"
                            class="px-3.5 py-2 rounded-xl inv-inset text-xs font-bold cursor-pointer">إغلاق</button>
                    </div>
                    <p id="creativeLibraryLabel" class="text-sm inv-muted mb-4"></p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach($creatives as $creative)
                            <div class="creative-card rounded-2xl inv-inset p-2" data-gallery-card hidden>
                                <button type="button" class="creative-pick w-full rounded-xl border-2 border-transparent p-1 cursor-pointer"
                                    data-id="{{ $creative->id }}" data-type="{{ $creative->invitation_type }}"
                                    data-prepared-artwork="{{ $creative->hasPreparedArtwork() || $creative->designMode() === \App\Models\CreativeTemplate::MODE_SVG ? 'true' : 'false' }}"
                                    data-artwork-revision="{{ $creative->artworkRevision() }}"
                                    @if ($creative->designMode() === \App\Models\CreativeTemplate::MODE_SVG)
                                        data-svg-source="{{ route('invitations.creatives.svg-source', $creative->id, absolute: false) }}"
                                    @endif
                                    data-variables="{{ json_encode($creative->variableKeys()) }}" data-editable-fields="{{ json_encode($creative->editableTextFields(), JSON_UNESCAPED_UNICODE) }}"
                                    data-name="{{ $creative->name }}"
                                    data-url="{{ $creative->designMode() === \App\Models\CreativeTemplate::MODE_WEB_ARTWORK && $creative->hasPreparedArtwork()
                                        ? route('invitations.creatives.preview-image', $creative->id, absolute: false)
                                        : (filter_var($creative->image_path, FILTER_VALIDATE_URL) ? $creative->image_url : ($creative->image_path ? asset('storage/'.$creative->image_path) : '')) }}"
                                    aria-pressed="false">
                                    <img data-gallery-image="{{ route('invitations.creatives.preview-image', $creative->id, absolute: false) }}" alt="" class="w-full h-36 object-contain rounded-lg">
                                    <span class="block mt-1 text-xs font-bold" style="color: var(--inv-text)">{{ $creative->name }}</span>
                                </button>
                                <button type="button" data-gallery-zoom class="mt-1.5 w-full rounded-xl inv-card py-2 text-xs font-bold inv-text-primary cursor-pointer"
                                    aria-label="معاينة {{ $creative->name }}">معاينة الصورة</button>
                            </div>
                        @endforeach
                    </div>
                    <p id="creativeEmpty" class="text-xs inv-muted mt-2"></p>
                    <button type="button" id="creativeConfirm" disabled class="inv-btn-primary mt-4 px-5 py-2.5 text-sm cursor-pointer">استخدام القالب</button>
                </dialog>

                <dialog id="preparedArtworkDialog" aria-labelledby="preparedArtworkTitle" dir="rtl"
                    data-preview-url="{{ route('invitations.preview-artwork', absolute: false) }}"
                    data-svg-fonts="{{ asset('fonts') }}"
                    class="inv-dialog rounded-3xl p-5 shadow-2xl m-auto" style="width:min(94vw,680px);max-height:90vh;overflow:auto">
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <h3 id="preparedArtworkTitle" class="font-extrabold text-xl" style="color: var(--inv-text)">تعديل كتابة القالب</h3>
                        <button type="button" id="preparedArtworkCancel" class="rounded-xl inv-inset px-3.5 py-2 text-xs font-bold cursor-pointer">إلغاء</button>
                    </div>
                    <p class="text-sm inv-muted mb-3">اضغط على النص في الصورة أو اختر الحقل أدناه.</p>
                    <div class="text-center">
                        <div dir="ltr" style="display:inline-block;position:relative;max-width:100%;line-height:0">
                            <img id="preparedArtworkOriginal" alt="صورة القالب" style="display:block;max-width:100%;max-height:48vh;width:auto;height:auto">
                            <img id="preparedArtworkLive" alt="معاينة الكتابة المعدّلة" hidden
                                style="position:absolute;inset:0;width:100%;height:100%;pointer-events:none">
                            <div id="preparedArtworkTargets"></div>
                        </div>
                    </div>
                    <div id="preparedArtworkFields" class="grid gap-3 mt-3"></div>
                    <p id="preparedArtworkStatus" class="text-sm mt-2 inv-text-primary font-bold" role="status"></p>
                    <button type="button" id="preparedArtworkRetry" class="text-sm underline inv-text-primary cursor-pointer" hidden>إعادة المعاينة</button>
                    <button type="button" id="preparedArtworkApply"
                        class="inv-btn-primary mt-4 px-5 py-2.5 text-sm cursor-pointer">تطبيق التعديلات</button>
                </dialog>

                @if($backgrounds->isNotEmpty())
                    <details class="mt-2">
                        <summary class="font-bold text-sm mb-2 cursor-pointer inv-text-sec">خلفيات عامة</summary>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-2">
                            @foreach($backgrounds as $bg)
                                @php $backgroundUrl = filter_var($bg->image_path, FILTER_VALIDATE_URL) ? $bg->image_url : asset('storage/'.$bg->image_path); @endphp
                                <button type="button" data-bg-id="{{ $bg->id }}" data-bg-url="{{ $backgroundUrl }}"
                                    data-name="{{ $bg->name }}" aria-pressed="false"
                                    class="bg-pick rounded-2xl overflow-hidden border-2 border-transparent inv-inset cursor-pointer" title="{{ $bg->name }}">
                                    <img src="{{ $backgroundUrl }}" class="w-full h-36 object-contain" alt="{{ $bg->name }}">
                                </button>
                            @endforeach
                        </div>
                    </details>
                @endif

                <div class="pt-2 border-t" style="border-color: var(--inv-border);">
                    <label for="media" class="block font-bold text-xs inv-muted mb-2">مرفق اختياري من جهازك (صورة / فيديو / صوت)</label>
                    <div class="rounded-2xl p-4 text-center" style="background: var(--inv-surface-soft); border: 1.5px dashed var(--inv-border-strong);">
                        <input type="file" id="media" name="media" accept="image/*,video/*,audio/*" class="w-full text-xs inv-text-sec cursor-pointer">
                        <input type="hidden" name="media_type" id="media_type" value="">
                    </div>
                </div>
            </section>

            {{-- ٥. قنوات الإرسال --}}
            <section class="inv-card rounded-3xl p-5 sm:p-6">
                <div class="inv-section-heading">
                    <span class="inv-section-title"><span class="inv-dot"></span>قنوات الإرسال</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <label class="relative flex items-center justify-center gap-3 p-4 rounded-2xl inv-inset cursor-pointer hover:opacity-90 transition" style="border-color: rgba(37, 211, 102, 0.45);">
                        <input type="checkbox" name="channels[]" value="whatsapp" @checked(in_array('whatsapp', (array) old('channels', ['whatsapp']), true)) class="w-5 h-5 accent-emerald-600">
                        <i class="fab fa-whatsapp text-2xl text-emerald-500" aria-hidden="true"></i>
                        <span class="font-extrabold text-sm" style="color: var(--inv-text)">واتساب</span>
                    </label>
                    <label class="relative flex items-center justify-center gap-3 p-4 rounded-2xl inv-inset cursor-pointer hover:opacity-90 transition" style="border-color: rgba(59, 130, 246, 0.45);">
                        <input type="checkbox" name="channels[]" value="push" @checked(in_array('push', (array) old('channels', ['whatsapp']), true)) class="w-5 h-5 accent-blue-600">
                        <i class="fas fa-bell text-2xl text-blue-500" aria-hidden="true"></i>
                        <div class="text-right">
                            <span class="font-extrabold text-sm block" style="color: var(--inv-text)">إشعار التطبيق</span>
                            <span id="pushChannelHint" class="text-[11px] inv-muted block"></span>
                        </div>
                    </label>
                </div>
            </section>

            {{-- ٦. المستلمون --}}
            <section class="inv-card rounded-3xl p-5 sm:p-6">
                <div class="inv-section-heading">
                    <span class="inv-section-title"><span class="inv-dot"></span>المستلمون</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 mb-4">
                    <label class="flex items-center justify-center gap-2 px-4 py-3 rounded-2xl inv-inset cursor-pointer transition">
                        <input type="radio" name="recipient_type" value="group" @checked(old('recipient_type', 'persons') === 'group') class="accent-emerald-600">
                        <span class="font-bold text-sm" style="color: var(--inv-text)">مجموعة جاهزة</span>
                    </label>
                    <label class="flex items-center justify-center gap-2 px-4 py-3 rounded-2xl inv-inset cursor-pointer transition">
                        <input type="radio" name="recipient_type" value="persons" @checked(old('recipient_type', 'persons') === 'persons') class="accent-emerald-600">
                        <span class="font-bold text-sm" style="color: var(--inv-text)">أشخاص من الشجرة</span>
                    </label>
                    <label class="flex items-center justify-center gap-2 px-4 py-3 rounded-2xl inv-inset cursor-pointer transition">
                        <input type="radio" name="recipient_type" value="external" @checked(old('recipient_type', 'persons') === 'external') class="accent-emerald-600">
                        <span class="font-bold text-sm flex items-center gap-1.5" style="color: var(--inv-text)">
                            <i class="fab fa-whatsapp text-emerald-500" aria-hidden="true"></i>
                            شخص من خارج العائلة
                        </span>
                    </label>
                </div>

                <div id="wrapperGroup">
                    <select name="group_id" class="inv-input w-full px-4 py-3 text-sm font-medium">
                        <option value="">— اختر مجموعة —</option>
                        @foreach($groups as $g)
                            <option value="{{ $g->id }}" @disabled($g->persons_count === 0) @selected($g->persons_count > 0 && (string) old('group_id') === (string) $g->id)>{{ $g->name }} ({{ $g->persons_count }} شخص)</option>
                        @endforeach
                    </select>
                    @if($groups->isEmpty())
                        <p class="text-sm inv-muted mt-2">لا توجد مجموعات. <a href="{{ route('invitations.groups.create') }}" class="inv-text-primary font-bold">أنشئ مجموعة</a></p>
                    @endif
                </div>

                <div id="wrapperPersons" class="hidden">
                    @include('web-site.invitations.partials.tree-picker', ['pickerId' => 'sendPicker', 'inputName' => 'person_ids[]', 'excludedPersonId' => $person->id])
                </div>

                <div id="wrapperExternal" class="hidden">
                    <div class="p-4 inv-inset rounded-2xl space-y-3" style="border-color: var(--inv-border-strong);">
                        <div class="flex items-center gap-2.5">
                            <span class="w-9 h-9 rounded-xl inv-btn-primary flex items-center justify-center text-sm shrink-0">
                                <i class="fab fa-whatsapp text-white" aria-hidden="true"></i>
                            </span>
                            <div>
                                <h3 class="font-bold text-sm" style="color: var(--inv-text)">دعوة ضيف من خارج العائلة عبر واتساب</h3>
                                <p class="text-xs inv-muted m-0">أدخل اسم الضيف ورقم الواتساب لإرسال الدعوة باسمه الشخصي مباشرة.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                            <div>
                                <label for="external_name" class="block text-xs font-bold inv-muted mb-1">اسم الضيف / المدعو <span class="text-rose-500">*</span></label>
                                <input type="text" name="external_name" id="external_name" value="{{ old('external_name') }}" class="inv-input w-full px-4 py-2.5 text-sm" placeholder="مثال: أبو فهد أو عبدالله الشمري">
                            </div>
                            <div>
                                <label for="external_phone" class="block text-xs font-bold inv-muted mb-1">رقم الواتساب <span class="text-rose-500">*</span></label>
                                <div class="flex items-center gap-2" dir="ltr">
                                    <select name="external_country_code" id="external_country_code" class="inv-input px-3 py-2.5 text-xs font-mono">
                                        <option value="+966" {{ old('external_country_code', '+966') === '+966' ? 'selected' : '' }}>🇸🇦 +966 (السعودية)</option>
                                        <option value="+971" {{ old('external_country_code') === '+971' ? 'selected' : '' }}>🇦🇪 +971 (الإمارات)</option>
                                        <option value="+965" {{ old('external_country_code') === '+965' ? 'selected' : '' }}>🇰🇼 +965 (الكويت)</option>
                                        <option value="+974" {{ old('external_country_code') === '+974' ? 'selected' : '' }}>🇶🇦 +974 (قطر)</option>
                                        <option value="+973" {{ old('external_country_code') === '+973' ? 'selected' : '' }}>🇧🇭 +973 (البحرين)</option>
                                        <option value="+968" {{ old('external_country_code') === '+968' ? 'selected' : '' }}>🇴🇲 +968 (عُمان)</option>
                                        <option value="+20" {{ old('external_country_code') === '+20' ? 'selected' : '' }}>🇪🇬 +20 (مصر)</option>
                                        <option value="+962" {{ old('external_country_code') === '+962' ? 'selected' : '' }}>🇯🇴 +962 (الأردن)</option>
                                        <option value="+964" {{ old('external_country_code') === '+964' ? 'selected' : '' }}>🇮🇶 +964 (العراق)</option>
                                        <option value="+963" {{ old('external_country_code') === '+963' ? 'selected' : '' }}>🇸🇾 +963 (سوريا)</option>
                                        <option value="+961" {{ old('external_country_code') === '+961' ? 'selected' : '' }}>🇱🇧 +961 (لبنان)</option>
                                        <option value="+967" {{ old('external_country_code') === '+967' ? 'selected' : '' }}>🇾🇪 +967 (اليمن)</option>
                                        <option value="+212" {{ old('external_country_code') === '+212' ? 'selected' : '' }}>🇲🇦 +212 (المغرب)</option>
                                        <option value="+213" {{ old('external_country_code') === '+213' ? 'selected' : '' }}>🇩🇿 +213 (الجزائر)</option>
                                        <option value="+216" {{ old('external_country_code') === '+216' ? 'selected' : '' }}>🇹🇳 +216 (تونس)</option>
                                        <option value="+249" {{ old('external_country_code') === '+249' ? 'selected' : '' }}>🇸🇩 +249 (السودان)</option>
                                        <option value="+90" {{ old('external_country_code') === '+90' ? 'selected' : '' }}>🇹🇷 +90 (تركيا)</option>
                                        <option value="+44" {{ old('external_country_code') === '+44' ? 'selected' : '' }}>🇬🇧 +44 (بريطانيا)</option>
                                        <option value="+1" {{ old('external_country_code') === '+1' ? 'selected' : '' }}>🇺🇸 +1 (أمريكا/كندا)</option>
                                    </select>
                                    <input type="text" name="external_phone" id="external_phone" value="{{ old('external_phone') }}" class="inv-input flex-1 px-4 py-2.5 text-sm font-mono text-left" placeholder="501234567">
                                </div>
                            </div>
                        </div>

                        <div class="inv-pill mt-1">
                            <i class="fas fa-circle-check" aria-hidden="true"></i>
                            <span>سيتم استبدال {name} باسم الضيف عند إرسال الدعوة عبر واتساب.</span>
                        </div>
                    </div>
                </div>

                <div id="deceasedWrapper">
                    <label class="mt-4 flex items-center justify-between pt-4 border-t cursor-pointer" style="border-color: var(--inv-border);">
                        <span class="text-sm font-bold" style="color: var(--inv-text)">استبعاد المتوفين</span>
                        <input type="checkbox" name="exclude_deceased" checked class="w-5 h-5 accent-emerald-600">
                    </label>
                </div>
            </section>

            <div class="grid grid-cols-2 gap-3">
                <button type="button" id="previewInvitation" aria-haspopup="dialog" aria-controls="invitationPreviewDialog"
                    class="inv-btn-outline py-3.5 px-4 text-sm sm:text-base cursor-pointer disabled:opacity-50">
                    <i class="fas fa-eye ml-1.5" aria-hidden="true"></i> معاينة
                </button>
                <button type="submit" class="inv-btn-primary py-3.5 px-4 text-sm sm:text-base cursor-pointer">
                    <i class="fas fa-paper-plane ml-1.5" aria-hidden="true"></i> إرسال الدعوة
                </button>
            </div>
            <p id="invitationPreviewStatus" role="status" class="text-sm font-bold" style="color: var(--inv-danger-text)"></p>
        </div>

        {{-- المعاينة المباشرة --}}
        <aside class="lg:col-span-4">
            <div class="inv-card rounded-3xl p-5 sticky top-24">
                <div class="inv-section-heading mb-3">
                    <span class="inv-section-title"><span class="inv-dot"></span>معاينة مباشرة</span>
                </div>
                <div class="rounded-2xl p-4 min-h-[280px] border" style="background: linear-gradient(155deg, #0b3a27 0%, #072519 100%); border-color: rgba(52, 211, 153, 0.25);">
                    <img id="simBg" class="hidden w-full rounded-xl mb-3 max-h-56 object-contain" alt="">
                    <p id="simTitle" class="invitation-typed-text text-base mb-2 text-white">تنبيه عائلي</p>
                    <p id="simBody" class="invitation-typed-text text-sm whitespace-pre-wrap text-emerald-100/90 leading-relaxed">السلام عليكم...</p>
                </div>
            </div>
        </aside>
    </form>

    <dialog id="invitationPreviewDialog" aria-labelledby="invitationPreviewTitle" dir="rtl"
        class="inv-dialog m-auto w-11/12 max-w-2xl rounded-3xl p-5 shadow-2xl">
        <h2 id="invitationPreviewTitle" class="mb-3 text-xl font-extrabold" style="color: var(--inv-text)">معاينة الدعوة</h2>
        <img id="invitationPreviewArtwork" class="mx-auto max-h-[50vh] max-w-full rounded-2xl object-contain" alt="صورة معاينة الدعوة" hidden>
        <p id="invitationPreviewImageError" role="status" class="text-sm font-bold" style="color: var(--inv-danger-text)" hidden>تعذّر تحميل صورة المعاينة.</p>
        <p id="invitationPreviewText" class="invitation-typed-text my-4 whitespace-pre-wrap break-words text-sm sm:text-base" style="color: var(--inv-text)"></p>
        <form method="dialog"><button class="inv-btn-primary w-full p-3 text-sm cursor-pointer">إغلاق</button></form>
    </dialog>

    <dialog id="galleryPreviewDialog" aria-labelledby="galleryPreviewTitle" dir="rtl"
        class="inv-dialog m-auto w-11/12 max-w-4xl rounded-3xl p-5 shadow-2xl">
        <div class="mb-3 flex items-center justify-between gap-2">
            <h2 id="galleryPreviewTitle" class="font-extrabold" style="color: var(--inv-text)">معاينة القالب</h2>
            <form method="dialog"><button class="rounded-xl inv-inset px-3.5 py-2 text-xs font-bold cursor-pointer">إغلاق</button></form>
        </div>
        <img id="galleryPreviewArtwork" class="mx-auto max-h-[72vh] max-w-full rounded-2xl object-contain" alt="صورة القالب">
        <a id="galleryOpenFull" target="_blank" rel="noopener" class="mt-3 inline-block text-sm font-bold inv-text-primary">عرض بالحجم الكامل</a>
    </dialog>
@endsection

@section('scripts')
    @include('web-site.invitations.partials.tree-picker-script')
    <script src="{{ asset('js/invitations/editable-svg.js') }}?v={{ filemtime(public_path('js/invitations/editable-svg.js')) }}" defer></script>
    <script src="{{ asset('js/invitations/prepared-artwork.js') }}?v={{ filemtime(public_path('js/invitations/prepared-artwork.js')) }}" defer></script>
    <script>
        const templates = @json($templates);
        const senderName = @json($person->full_name ?: $person->first_name);
        const invitationForm = document.getElementById('invitationForm');
        const creativeInput = document.getElementById('creative_id');
        const preparedArtworkPreview = document.getElementById('preparedArtworkPreview');
        const backgroundInput = document.getElementById('background_id');
        const attachment = document.getElementById('media');
        const previewImage = document.getElementById('simBg');
        let attachmentUrl = null;
        const creativeDialog = document.getElementById('creativeDialog');
        const creativeCards = Array.from(creativeDialog.querySelectorAll('.creative-pick'));
        const creativeOpen = document.getElementById('creativeOpen');
        const creativeConfirm = document.getElementById('creativeConfirm');
        const galleryPreview = document.getElementById('galleryPreviewDialog');
        const templateSelect = document.getElementById('template_select');
        const tplCards = Array.from(document.querySelectorAll('[data-tpl-card]'));
        let pendingCreative = null;
        let renderedCreativeId = null;

        function syncTemplateVisualCards() {
            const key = templateSelect.value;
            tplCards.forEach((btn) => {
                const active = btn.dataset.tplCard === key;
                if (btn.classList.contains('inv-tpl-card')) {
                    btn.classList.toggle('is-active', active);
                    btn.querySelector('[data-tpl-check]')?.classList.toggle('hidden', !active);
                    btn.querySelector('[data-tpl-check]')?.classList.toggle('inline-flex', active);
                    const iconBox = btn.querySelector('[data-tpl-icon]');
                    if (iconBox) {
                        iconBox.style.background = active ? 'var(--inv-primary)' : 'rgba(255,255,255,0.92)';
                        iconBox.style.color = active ? '#ffffff' : '#0f241c';
                    }
                } else {
                    btn.style.borderColor = active ? 'var(--inv-primary)' : 'var(--inv-border)';
                    btn.style.background = active ? 'var(--inv-pill-bg)' : 'var(--inv-surface)';
                    btn.style.color = active ? 'var(--inv-pill-text)' : 'var(--inv-text-secondary)';
                }
            });
            const badge = document.getElementById('templateVarBadge');
            const activeTpl = templates.find((t) => t.key === key);
            if (badge) {
                const span = badge.querySelector('span');
                if (span) span.textContent = 'متغيرات: ' + (activeTpl ? activeTpl.name : 'مخصص');
            }
        }

        tplCards.forEach((btn) => {
            btn.addEventListener('click', () => {
                const next = templateSelect.value === btn.dataset.tplCard ? '' : btn.dataset.tplCard;
                templateSelect.value = next;
                templateSelect.dispatchEvent(new Event('change'));
            });
        });

        // أزرار تنسيق النص (B / I / S) وإدراج المتغيرات
        const bodyEl = document.getElementById('body');
        document.querySelectorAll('[data-format-mark]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const mark = btn.dataset.formatMark;
                document.querySelectorAll('[data-format-mark]').forEach((b) => b.classList.toggle('is-active', b === btn));
                const start = bodyEl.selectionStart ?? bodyEl.value.length;
                const end = bodyEl.selectionEnd ?? bodyEl.value.length;
                const before = bodyEl.value.slice(0, start);
                const mid = bodyEl.value.slice(start, end);
                const after = bodyEl.value.slice(end);
                bodyEl.value = mid ? `${before}${mark}${mid}${mark}${after}` : `${before}${mark}${mark}${after}`;
                const cursor = mid ? end + mark.length * 2 : start + mark.length;
                bodyEl.focus();
                bodyEl.setSelectionRange(cursor, cursor);
                bodyEl.dispatchEvent(new Event('input'));
            });
        });

        document.querySelectorAll('[data-insert-token]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const token = btn.dataset.insertToken;
                if (bodyEl.value.includes(token)) return;
                const start = bodyEl.selectionStart ?? bodyEl.value.length;
                const end = bodyEl.selectionEnd ?? bodyEl.value.length;
                const before = bodyEl.value.slice(0, start);
                const after = bodyEl.value.slice(end);
                const spacer = before && !before.endsWith(' ') && !before.endsWith('\n') ? ' ' : '';
                bodyEl.value = `${before}${spacer}${token}${after}`;
                bodyEl.focus();
                bodyEl.dispatchEvent(new Event('input'));
            });
        });

        creativeOpen.addEventListener('click', function () {
            pendingCreative = getSelectedCreative();
            refreshGallery();
            creativeDialog.showModal();
        });
        document.getElementById('creativeClose').addEventListener('click', function () {
            creativeDialog.close();
        });

        function getSelectedCreative() {
            return creativeCards.find((button) => button.dataset.id === creativeInput.value);
        }

        function refreshGallery() {
            const type = templateSelect.value;
            let count = 0;
            creativeCards.forEach((button) => {
                const visible = button.dataset.type === type;
                button.closest('[data-gallery-card]').hidden = !visible;
                if (visible) {
                    count++;
                    const image = button.querySelector('[data-gallery-image]');
                    if (!image.getAttribute('src')) image.src = image.dataset.galleryImage;
                }
                button.classList.toggle('ring-2', button === pendingCreative);
                button.setAttribute('aria-pressed', String(button === pendingCreative));
            });
            document.getElementById('creativeEmpty').textContent = !count ? 'لا توجد قوالب معتمدة لهذا النوع من الدعوات.' : '';
            document.getElementById('creativeLibraryLabel').textContent = templates.find((item) => item.key === type)?.name || '';
            creativeConfirm.disabled = !pendingCreative;
        }

        function selectedArtworkUrl() {
            const background = Array.from(document.querySelectorAll('.bg-pick')).find((button) => button.dataset.bgId === backgroundInput.value);
            const edited = preparedArtworkPreview.dataset.templateId === creativeInput.value ? preparedArtworkPreview.dataset.previewImage : null;
            return attachmentUrl || edited || getSelectedCreative()?.dataset.url || background?.dataset.bgUrl;
        }

        function refreshImages() {
            const type = templateSelect.value;
            creativeOpen.style.display = type ? '' : 'none';
            document.getElementById('creativeClear').hidden = !creativeInput.value && !backgroundInput.value;
            const creative = getSelectedCreative();
            document.querySelectorAll('.bg-pick').forEach(function (button) {
                button.setAttribute('aria-pressed', String(backgroundInput?.value === button.dataset.bgId));
                button.classList.toggle('ring-2', backgroundInput?.value === button.dataset.bgId);
                button.classList.toggle('ring-emerald-500', backgroundInput?.value === button.dataset.bgId);
            });
            const background = Array.from(document.querySelectorAll('.bg-pick')).find((button) => button.dataset.bgId === backgroundInput.value);
            document.getElementById('creativeSelectionLabel').textContent = creative || background
                ? 'القالب المختار: ' + (creative || background).dataset.name
                : 'اختر قالبًا للدعوة';
            const url = selectedArtworkUrl();
            previewImage.classList.toggle('hidden', !url);
            if (url) previewImage.src = url;
            else previewImage.removeAttribute('src');
            updatePreview();
            creativeInput.dispatchEvent(new Event('change'));
        }
        preparedArtworkPreview.addEventListener('change', refreshImages);
        function clearAttachment() {
            attachment.value = '';
            if (attachmentUrl) URL.revokeObjectURL(attachmentUrl);
            attachmentUrl = null;
            document.getElementById('media_type').value = '';
        }
        creativeCards.forEach((button) => {
            button.addEventListener('click', () => {
                pendingCreative = button;
                refreshGallery();
            });
            button.closest('[data-gallery-card]').querySelector('[data-gallery-zoom]').addEventListener('click', () => {
                const url = button.querySelector('[data-gallery-image]').dataset.galleryImage;
                document.getElementById('galleryPreviewTitle').textContent = button.dataset.name;
                document.getElementById('galleryPreviewArtwork').src = url;
                document.getElementById('galleryOpenFull').href = url;
                galleryPreview.showModal();
            });
        });
        creativeConfirm.addEventListener('click', () => {
            if (!pendingCreative) return;
            creativeInput.value = pendingCreative.dataset.id;
            backgroundInput.value = '';
            clearAttachment();
            renderTemplateVars(bodyEl.value);
            refreshImages();
            creativeDialog.close();
            if (pendingCreative.dataset.preparedArtwork !== 'true' && JSON.parse(pendingCreative.dataset.editableFields).length) {
                document.getElementById('inv-var-creative_text_1')?.focus();
            }
        });

        const previewButton = document.getElementById('previewInvitation');
        const previewStatus = document.getElementById('invitationPreviewStatus');
        const previewDialog = document.getElementById('invitationPreviewDialog');
        const dialogImage = document.getElementById('invitationPreviewArtwork');
        const imageError = document.getElementById('invitationPreviewImageError');
        dialogImage.addEventListener('error', () => {
            dialogImage.hidden = true;
            imageError.hidden = false;
        });
        previewButton.addEventListener('click', async function () {
            const data = new FormData();
            for (const [name, value] of new FormData(invitationForm)) {
                if (name.startsWith('template_vars[') || ['_token', 'title', 'body', 'template_key', 'creative_id', 'creative_edit_source', 'svg_card', 'background_id', 'recipient_type'].includes(name)) {
                    data.append(name, value);
                }
                if (name === 'person_ids[]' && !data.has('person_id')) data.set('person_id', value);
            }
            const recipientType = data.get('recipient_type');
            if (recipientType !== 'persons') data.delete('person_id');
            if (recipientType === 'group') data.set('group_id', invitationForm.elements.group_id.value);
            if (recipientType === 'external') data.set('external_name', invitationForm.elements.external_name.value);
            const fallback = selectedArtworkUrl();
            previewButton.disabled = true;
            previewStatus.textContent = 'جارٍ تجهيز المعاينة…';
            try {
                const response = await fetch(@json(route('invitations.preview-message', absolute: false)), { method: 'POST', headers: { Accept: 'application/json' }, body: data });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'تعذّر تجهيز المعاينة.');
                const artwork = result.web_card_image_url || fallback;
                if (!artwork && !result.preview) {
                    previewStatus.textContent = 'أضف نص الدعوة أو اختر صورة للمعاينة.';
                    return;
                }
                imageError.hidden = true;
                dialogImage.hidden = !artwork;
                if (artwork) dialogImage.src = artwork;
                else dialogImage.removeAttribute('src');
                document.getElementById('invitationPreviewText').textContent = result.preview || '';
                previewStatus.textContent = '';
                previewDialog.showModal();
            } catch (error) {
                previewStatus.textContent = error.message || 'تعذّر تجهيز المعاينة.';
            } finally {
                previewButton.disabled = false;
            }
        });
        document.querySelectorAll('.bg-pick').forEach(function (button) {
            button.addEventListener('click', function () {
                backgroundInput.value = button.dataset.bgId;
                creativeInput.value = '';
                clearAttachment();
                renderTemplateVars(bodyEl.value);
                refreshImages();
                creativeDialog.close();
            });
        });
        document.getElementById('creativeClear').addEventListener('click', function () {
            creativeInput.value = '';
            backgroundInput.value = '';
            renderTemplateVars(bodyEl.value);
            refreshImages();
        });
        attachment.addEventListener('change', function () {
            if (attachmentUrl) URL.revokeObjectURL(attachmentUrl);
            const file = attachment.files[0];
            attachmentUrl = file?.type.startsWith('image/') ? URL.createObjectURL(file) : null;
            document.getElementById('media_type').value = { image: 'image', video: 'video', audio: 'voice' }[file?.type.split('/')[0]] || '';
            creativeInput.value = '';
            if (backgroundInput) backgroundInput.value = '';
            renderTemplateVars(bodyEl.value);
            refreshImages();
        });

        function syncRecipientType() {
            const type = invitationForm.elements.recipient_type.value;
            const external = type === 'external';
            document.getElementById('wrapperGroup').classList.toggle('hidden', type !== 'group');
            document.getElementById('wrapperPersons').classList.toggle('hidden', type !== 'persons');
            document.getElementById('wrapperExternal').classList.toggle('hidden', !external);
            document.getElementById('deceasedWrapper').classList.toggle('hidden', external);
            const push = invitationForm.querySelector('[name="channels[]"][value="push"]');
            push.disabled = external;
            if (external) {
                push.checked = false;
                invitationForm.querySelector('[name="channels[]"][value="whatsapp"]').checked = true;
            }
            document.getElementById('pushChannelHint').textContent = external ? 'متاح للأعضاء المسجّلين في التطبيق' : '';
            updatePreview();
        }
        invitationForm.querySelectorAll('[name="recipient_type"]').forEach((radio) => radio.addEventListener('change', syncRecipientType));

        function extractVars(body) {
            const found = body.match(/\{[a-z_]+\}/g) || [];
            return [...new Set(found)].filter(function (v) {
                return ['{name}','{first_name}','{full_name}','{sender_name}'].indexOf(v) === -1;
            });
        }

        function renderTemplateVars(body, values) {
            const box = document.getElementById('templateVars');
            const creative = getSelectedCreative();
            const creativeId = creative?.dataset.id ?? null;
            const creativeChanged = renderedCreativeId !== creativeId;
            renderedCreativeId = creativeId;
            const designVars = JSON.parse(creative?.dataset.variables || '[]');
            const editableFields = new Map(JSON.parse(creative?.dataset.editableFields || '[]').map((field) => [field.key, field]));
            const vars = [...new Set([...extractVars(body), ...designVars.map((name) => '{' + name + '}')])];
            const inputs = Array.from(box.querySelectorAll('.tpl-var'));
            if (!values && !creativeChanged && inputs.map((input) => input.dataset.var).join() === vars.join()) return;
            values ??= Object.fromEntries(inputs.map((input) => [input.dataset.var.slice(1, -1), input.value])
                .filter(([key]) => !creativeChanged || !key.startsWith('creative_text_')));
            const labels = { hosts: 'أصحاب الدعوة', groom: 'اسم العريس', date: 'التاريخ', time: 'الوقت', location: 'القاعة أو المكان',
                event_name: 'اسم المناسبة', occasion: 'المناسبة', announcement_title: 'عنوان الإعلان', announcement_details: 'تفاصيل الإعلان',
                topic: 'الموضوع', deceased_name: 'اسم المتوفى', urgent_matter: 'موضوع التنبيه' };
            const icons = { date: 'fa-calendar-days', time: 'fa-clock', location: 'fa-location-dot', event_name: 'fa-wand-magic-sparkles',
                occasion: 'fa-wand-magic-sparkles', hosts: 'fa-user', groom: 'fa-user', deceased_name: 'fa-user' };
            box.innerHTML = '';
            const prepared = creative?.dataset.preparedArtwork === 'true';
            document.getElementById('templateVarSection').hidden = !vars.some((key) => !prepared || !editableFields.has(key.slice(1, -1)));
            vars.forEach(function (v) {
                const key = v.replace(/[{}]/g, '');
                const wrap = document.createElement('div');
                wrap.className = 'inv-card rounded-2xl p-3 flex items-center gap-3';
                const field = editableFields.get(key);
                const iconBox = document.createElement('span');
                iconBox.className = 'w-9 h-9 rounded-xl flex items-center justify-center shrink-0 inv-text-primary';
                iconBox.style.background = 'var(--inv-surface-soft)';
                iconBox.innerHTML = `<i class="fas ${icons[key] || 'fa-pen'} text-sm" aria-hidden="true"></i>`;
                const inner = document.createElement('div');
                inner.className = 'flex-1 min-w-0';
                const label = document.createElement('label');
                label.htmlFor = 'inv-var-' + key;
                label.className = 'text-[11px] font-bold inv-muted mb-0.5 block';
                label.textContent = field?.label || labels[key] || key;
                const input = document.createElement(prepared && field ? 'textarea' : 'input');
                wrap.hidden = Boolean(prepared && field);
                input.id = label.htmlFor;
                input.name = 'template_vars[' + key + ']';
                input.dataset.var = v;
                input.className = 'tpl-var w-full bg-transparent font-bold text-sm focus:outline-none';
                input.style.color = 'var(--inv-text)';
                input.placeholder = label.textContent;
                input.maxLength = 255;
                input.value = Object.hasOwn(values, key) ? (values[key] ?? '') : (field?.default_text ?? '');
                input.addEventListener('input', updatePreview);
                inner.append(label, input);
                wrap.append(iconBox, inner);
                box.appendChild(wrap);
            });
        }

        function applyVars(text) {
            const rType = document.querySelector('input[name="recipient_type"]:checked')?.value || 'group';
            let targetName = 'أحمد';
            let targetFullName = 'أحمد بن محمد';

            if (rType === 'external') {
                const extName = (document.getElementById('external_name')?.value || '').trim();
                targetName = extName || 'ضيفنا العزيز';
                targetFullName = extName || 'زائرنا الكريم';
            }

            let out = text.replaceAll('@{{name}}', targetName)
                .replaceAll('@{{first_name}}', targetName)
                .replaceAll('@{{full_name}}', targetFullName)
                .replaceAll('@{{sender_name}}', senderName)
                .replaceAll('{name}', targetName)
                .replaceAll('{first_name}', targetName)
                .replaceAll('{full_name}', targetFullName)
                .replaceAll('{sender_name}', senderName)
                .replaceAll(':name', targetName)
                .replaceAll(':first_name', targetName)
                .replaceAll(':full_name', targetFullName)
                .replaceAll(':sender_name', senderName);

            out = out.replace(/(مرحباً|أهلاً|عزيزي|عزيزتي|الغالي\/ة|الغالي|الغالية|حياك)\s+name\b/gu, '$1 ' + targetName);
            out = out.replace(/\bname\b/gu, targetName);

            document.querySelectorAll('.tpl-var').forEach(function (input) {
                out = out.replaceAll(input.dataset.var, input.value || input.dataset.var);
            });
            return out;
        }

        function updatePreview() {
            document.getElementById('simTitle').textContent = document.getElementById('title').value || 'تنبيه عائلي';
            document.getElementById('simBody').textContent = applyVars(bodyEl.value || '');
        }

        const extNameInput = document.getElementById('external_name');
        if (extNameInput) {
            extNameInput.addEventListener('input', updatePreview);
        }

        templateSelect.addEventListener('change', function () {
            const key = this.value;
            creativeInput.value = '';
            refreshImages();
            document.getElementById('template_key').value = key;
            syncTemplateVisualCards();
            if (!key) { renderTemplateVars(bodyEl.value); updatePreview(); return; }
            const tpl = templates.find(function (t) { return t.key === key; });
            if (!tpl) return;
            document.getElementById('title').value = tpl.title || '';
            bodyEl.value = tpl.body || '';
            renderTemplateVars(tpl.body || '', {});
            updatePreview();
        });

        document.getElementById('title').addEventListener('input', updatePreview);
        bodyEl.addEventListener('input', function () {
            renderTemplateVars(this.value);
            updatePreview();
        });
        const previousValues = @json(old('template_vars', [])) || {};
        const currentRevision = getSelectedCreative()?.dataset.artworkRevision;
        const previousRevision = document.getElementById('creativeArtworkSource').value;
        if (currentRevision && previousRevision && currentRevision !== previousRevision) {
            for (const key of Object.keys(previousValues)) {
                if (key.startsWith('creative_text_')) delete previousValues[key];
            }
        }
        renderTemplateVars(bodyEl.value, previousValues);
        syncTemplateVisualCards();
        refreshImages();
        syncRecipientType();
    </script>
@endsection
