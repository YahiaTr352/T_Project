@extends('web-site.invitations.layout')

@section('title', 'إرسال دعوة')

@section('extra_styles')
    @include('web-site.invitations.partials.typography-fonts')
    #invitationForm {
        --invitation-font: 'Invitation Tajawal', Tajawal, sans-serif;
        --invitation-weight: 400;
    }
    #invitationForm[data-invitation-font="amiri"] {
        --invitation-font: 'Invitation Amiri', Amiri, serif;
    }
    #invitationForm[data-invitation-weight="bold"] {
        --invitation-weight: 700;
    }
    .invitation-typed-text {
        font-family: var(--invitation-font);
        font-weight: var(--invitation-weight);
    }
    .invitation-font-tajawal { font-family: 'Invitation Tajawal', Tajawal, sans-serif; }
    .invitation-font-amiri { font-family: 'Invitation Amiri', Amiri, serif; }
    .invitation-weight-sample { font-family: var(--invitation-font); }
@endsection

@section('content')
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-extrabold text-slate-900">إرسال دعوة</h1>
            <p class="text-slate-600 mt-1">اختر مجموعتك أو أشخاصاً من الشجرة، ثم أرسل واتساب و/أو إشعار التطبيق</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('invitations.index') }}" class="px-4 py-2 rounded-xl bg-white border font-medium">الرئيسية</a>
            <a href="{{ route('invitations.groups.index') }}" class="px-4 py-2 rounded-xl bg-white border font-medium">المجموعات</a>
        </div>
    </div>

    <form id="invitationForm" action="{{ route('invitations.send.submit') }}" method="POST" enctype="multipart/form-data"
        data-invitation-font="{{ old('font_family', 'tajawal') }}" data-invitation-weight="{{ old('font_weight', 'regular') }}"
        class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        @csrf
        <div class="lg:col-span-8 space-y-6">
            <div class="glass-card rounded-3xl p-6">
                <h2 class="text-xl font-bold mb-4">١. قنوات الإرسال</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="relative flex flex-col items-center p-5 rounded-2xl border-2 border-emerald-400 bg-white cursor-pointer">
                        <input type="checkbox" name="channels[]" value="whatsapp" checked class="absolute top-4 right-4 w-5 h-5 accent-emerald-600">
                        <i class="fab fa-whatsapp text-4xl text-emerald-500 mb-2"></i>
                        <span class="font-bold">واتساب</span>
                    </label>
                    <label class="relative flex flex-col items-center p-5 rounded-2xl border-2 border-blue-300 bg-white cursor-pointer">
                        <input type="checkbox" name="channels[]" value="push" class="absolute top-4 right-4 w-5 h-5 accent-blue-600">
                        <i class="fas fa-bell text-4xl text-blue-500 mb-2"></i>
                        <span class="font-bold">إشعار التطبيق</span>
                        <span class="text-xs text-slate-500 mt-1">اختياري مع الدعوة</span>
                    </label>
                </div>
            </div>

            <div class="glass-card rounded-3xl p-6">
                <h2 class="text-xl font-bold mb-4">٢. المستلمون</h2>
                <div class="flex flex-wrap gap-3 mb-4">
                    <label class="flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-50 hover:bg-slate-100 cursor-pointer transition">
                        <input type="radio" name="recipient_type" value="group" checked class="accent-emerald-600">
                        <span class="font-semibold text-sm">مجموعة جاهزة</span>
                    </label>
                    <label class="flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-50 hover:bg-slate-100 cursor-pointer transition">
                        <input type="radio" name="recipient_type" value="persons" class="accent-emerald-600">
                        <span class="font-semibold text-sm">أشخاص من الشجرة</span>
                    </label>
                    <label class="flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-50/70 border border-emerald-200 hover:bg-emerald-100/70 cursor-pointer transition">
                        <input type="radio" name="recipient_type" value="external" class="accent-emerald-600">
                        <span class="font-semibold text-sm text-emerald-800 flex items-center gap-1.5">
                            <i class="fab fa-whatsapp text-emerald-600"></i>
                            شخص من خارج العائلة
                        </span>
                    </label>
                </div>
                <div id="wrapperGroup">
                    <select name="group_id" class="w-full px-4 py-3 border rounded-xl">
                        <option value="">— اختر مجموعة —</option>
                        @foreach($groups as $g)
                            <option value="{{ $g->id }}">{{ $g->name }} ({{ $g->persons_count }} شخص)</option>
                        @endforeach
                    </select>
                    @if($groups->isEmpty())
                        <p class="text-sm text-slate-500 mt-2">لا توجد مجموعات. <a href="{{ route('invitations.groups.create') }}" class="text-emerald-600 font-bold">أنشئ مجموعة</a></p>
                    @endif
                </div>
                <div id="wrapperPersons" class="hidden">
                    @include('web-site.invitations.partials.tree-picker', ['pickerId' => 'sendPicker', 'inputName' => 'person_ids[]'])
                </div>
                <div id="wrapperExternal" class="hidden">
                    <div class="p-4 bg-emerald-50/70 border border-emerald-200 rounded-2xl space-y-3">
                        <div class="flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-xl bg-emerald-600 text-white flex items-center justify-center text-sm shadow-sm">
                                <i class="fab fa-whatsapp"></i>
                            </span>
                            <div>
                                <h3 class="font-bold text-sm text-slate-900">دعوة ضيف من خارج العائلة عبر واتساب</h3>
                                <p class="text-xs text-slate-600 m-0">أدخل اسم الضيف ورقم الواتساب لإرسال الدعوة باسمه الشخصي مباشرة.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                            <div>
                                <label for="external_name" class="block text-xs font-bold text-slate-700 mb-1">اسم الضيف / المدعو <span class="text-rose-500">*</span></label>
                                <input type="text" name="external_name" id="external_name" value="{{ old('external_name') }}" class="w-full px-4 py-2.5 bg-white border border-slate-300 rounded-xl text-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500" placeholder="مثال: أبو فهد أو عبدالله الشمري">
                            </div>
                            <div>
                                <label for="external_phone" class="block text-xs font-bold text-slate-700 mb-1">رقم الواتساب <span class="text-rose-500">*</span></label>
                                <div class="flex items-center gap-2" dir="ltr">
                                    <select name="external_country_code" id="external_country_code" class="px-3 py-2.5 bg-white border border-slate-300 rounded-xl text-xs font-mono focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
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
                                    <input type="text" name="external_phone" id="external_phone" value="{{ old('external_phone') }}" class="flex-1 px-4 py-2.5 bg-white border border-slate-300 rounded-xl text-sm font-mono text-left focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500" placeholder="501234567">
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 text-[11px] text-emerald-800 font-medium pt-1">
                            <i class="fas fa-circle-check text-emerald-600"></i>
                            <span>سيتم استبدال {name} باسم الضيف عند إرسال الدعوة عبر واتساب.</span>
                        </div>
                    </div>
                </div>
                <div id="deceasedWrapper">
                    <label class="mt-4 flex items-center justify-between pt-4 border-t">
                        <span class="text-sm font-bold">استبعاد المتوفين</span>
                        <input type="checkbox" name="exclude_deceased" checked class="w-5 h-5 accent-rose-500">
                    </label>
                </div>
            </div>

            <div class="glass-card rounded-3xl p-6">
                <h2 class="text-xl font-bold mb-4">٣. محتوى الرسالة</h2>
                <label for="template_select" class="block text-sm font-bold mb-2">نوع الدعوة</label>
                <select id="template_select" class="w-full px-4 py-3 border rounded-xl mb-4">
                    <option value="">— رسالة مخصصة —</option>
                    @foreach($templates as $tpl)
                        <option value="{{ $tpl['key'] }}" @selected(old('template_key') === $tpl['key'])>{{ $tpl['name'] }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="template_key" id="template_key" value="{{ old('template_key') }}">
                <div class="max-w-md space-y-2 mb-4 rounded-2xl border border-slate-200 bg-slate-50 p-3">
                    <fieldset class="min-w-0">
                        <legend class="text-sm font-bold mb-1">نوع الخط</legend>
                        <div class="grid grid-cols-2 rounded-xl border border-slate-200 bg-white p-1">
                            @foreach(['tajawal' => 'عادي', 'amiri' => 'نسخ'] as $value => $label)
                                <label class="cursor-pointer">
                                    <input type="radio" name="font_family" value="{{ $value }}" class="sr-only peer"
                                        @checked(old('font_family', 'tajawal') === $value)>
                                    <span class="invitation-font-{{ $value }} flex items-center justify-center min-h-9 px-2 rounded-lg text-sm peer-checked:bg-emerald-50 peer-checked:text-emerald-800 peer-checked:ring-1 peer-checked:ring-emerald-300 peer-focus-visible:outline peer-focus-visible:outline-emerald-700">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <fieldset class="min-w-0">
                        <legend class="text-sm font-bold mb-1">سماكة الخط</legend>
                        <div class="grid grid-cols-2 rounded-xl border border-slate-200 bg-white p-1">
                            @foreach(['regular' => 'عادي', 'bold' => 'عريض'] as $value => $label)
                                <label class="cursor-pointer">
                                    <input type="radio" name="font_weight" value="{{ $value }}" class="sr-only peer"
                                        @checked(old('font_weight', 'regular') === $value)>
                                    <span class="invitation-weight-sample {{ $value === 'bold' ? 'font-bold' : 'font-normal' }} flex items-center justify-center min-h-9 px-2 rounded-lg text-sm peer-checked:bg-emerald-50 peer-checked:text-emerald-800 peer-checked:ring-1 peer-checked:ring-emerald-300 peer-focus-visible:outline peer-focus-visible:outline-emerald-700">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                </div>
                <div id="templateVars" class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4 hidden"></div>
                <input type="text" name="title" id="title" value="{{ old('title') }}" placeholder="عنوان الإشعار" class="invitation-typed-text w-full px-4 py-3 border rounded-xl mb-3">
                <textarea name="body" id="body" rows="7" placeholder="النص اختياري عند اختيار صورة" class="invitation-typed-text w-full px-4 py-3 border rounded-xl">{{ old('body') }}</textarea>
                <p class="text-xs text-slate-400 mt-2">المتغيرات: {name} {full_name} {sender_name}</p>
            </div>

            <div class="glass-card rounded-3xl p-6">
                <h2 class="text-xl font-bold mb-4">الصورة والتصميم</h2>
                <input type="hidden" name="creative_id" id="creative_id" value="{{ old('creative_id') }}">
                <input type="hidden" name="background_id" id="background_id" value="{{ old('background_id') }}">
                <button type="button" id="creativeOpen" aria-haspopup="dialog" aria-controls="creativeDialog" style="display:none"
                    class="w-full flex items-center gap-3 rounded-2xl bg-emerald-600 p-4 text-white text-right">
                    <i class="fas fa-palette text-xl" aria-hidden="true"></i>
                    <span class="flex-1">
                        <span class="block font-bold">قوالب الصور</span>
                        <span id="creativeSelectionLabel" class="block text-xs mt-1">اختر قالبًا للدعوة</span>
                    </span>
                    <i class="fas fa-chevron-left" aria-hidden="true"></i>
                </button>
                <div class="flex flex-wrap gap-4 mt-2">
                    <button type="button" id="creativeClear" class="text-xs underline" hidden>إزالة قالب الصورة</button>
                    <button type="button" id="previewCreative" class="text-xs underline" hidden>معاينة صورة الدعوة</button>
                    <button type="button" id="designSubmissionOpen" aria-haspopup="dialog" aria-controls="designSubmission" class="text-sm font-bold text-emerald-700">مساحة إبداع</button>
                </div>
                <p id="previewCreativeStatus" role="status" class="text-sm text-slate-600"></p>

                <dialog id="creativeDialog" aria-labelledby="creativeDialogTitle" dir="rtl"
                    class="rounded-3xl p-6 shadow-xl" style="width:min(92vw,720px);max-height:85vh;overflow:auto">
                    <div class="flex items-center justify-between gap-4 mb-3">
                        <h3 id="creativeDialogTitle" class="font-bold text-xl">قوالب الصور</h3>
                        <button type="button" id="creativeClose" aria-label="إغلاق قوالب الصور"
                            class="px-3 py-2 rounded-xl border">إغلاق</button>
                    </div>
                    <p id="creativeLibraryLabel" class="text-sm text-slate-600 mb-4"></p>
                    <label class="block text-sm font-bold mb-2" for="creativeModeSelect">طريقة عرض القالب</label>
                    <select id="creativeModeSelect" class="w-full rounded-xl border p-2 mb-4">
                        <option value="">اختر طريقة العرض</option>
                        @foreach (\App\Models\CreativeTemplate::MODES as $mode => $label)
                            <option value="{{ $mode }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach($creatives as $creative)
                            <button type="button" class="creative-pick border-2 rounded-xl p-1"
                                data-id="{{ $creative->id }}" data-type="{{ $creative->invitation_type }}"
                                data-mode="{{ $creative->designMode() }}" data-variables="{{ json_encode($creative->variableKeys()) }}"
                                data-name="{{ $creative->name }}" data-url="{{ $creative->image_url }}" aria-pressed="false">
                                <img src="{{ $creative->image_url }}" alt="{{ $creative->name }}" class="w-full h-40 object-contain">
                                <span class="text-xs">{{ $creative->name }}</span>
                            </button>
                        @endforeach
                    </div>
                    <p id="creativeEmpty" class="text-xs text-slate-500 mt-2"></p>
                </dialog>
                @if($backgrounds->isNotEmpty())
                    <details class="mt-4">
                        <summary class="font-bold text-sm mb-2">خلفيات عامة</summary>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            @foreach($backgrounds as $bg)
                                <button type="button" data-bg-id="{{ $bg->id }}" data-bg-url="{{ $bg->image_url }}"
                                    data-name="{{ $bg->name }}" aria-pressed="false"
                                    class="bg-pick rounded-2xl overflow-hidden border-2 border-transparent" title="{{ $bg->name }}">
                                    <img src="{{ $bg->image_url }}" class="w-full h-40 object-contain" alt="{{ $bg->name }}">
                                </button>
                            @endforeach
                        </div>
                    </details>
                @endif

                <label for="media" class="block font-bold text-sm mt-5 mb-2">مرفق اختياري من جهازك</label>
                <input type="file" id="media" name="media" accept="image/*,video/*,audio/*" class="w-full">
                <input type="hidden" name="media_type" id="media_type" value="">
            </div>

            <button type="submit" class="w-full py-4 rounded-2xl bg-emerald-600 text-white font-bold text-lg hover:bg-emerald-700">
                إرسال الدعوة
            </button>
        </div>

        <div class="lg:col-span-4">
            <div class="glass-card rounded-3xl p-6 sticky top-24">
                <h3 class="font-bold mb-3">معاينة</h3>
                <div class="rounded-[28px] bg-slate-900 p-3 text-white min-h-[280px]">
                    <img id="simBg" class="hidden w-full rounded-2xl mb-2" alt="">
                    <p id="simTitle" class="invitation-typed-text text-base mb-2">تنبيه عائلي</p>
                    <p id="simBody" class="invitation-typed-text text-sm whitespace-pre-wrap text-slate-200">السلام عليكم...</p>
                </div>
            </div>
        </div>
    </form>
    <dialog id="designSubmission" aria-labelledby="designSubmissionTitle" dir="rtl"
        class="rounded-3xl p-6 shadow-xl" style="width:min(92vw,560px);max-height:85vh;overflow:auto">
        <div class="flex items-center justify-between gap-4 mb-3">
            <h2 id="designSubmissionTitle" class="font-bold text-xl">مساحة إبداع</h2>
            <button type="button" id="designSubmissionClose" class="px-3 py-2 rounded-xl border">إغلاق</button>
        </div>
        <p class="text-sm text-slate-600 mb-4">تراجع الإدارة التصميم قبل إضافته إلى قوالب الصور.</p>
        @if (session('creative_success'))
            <p role="status" class="mb-3 text-emerald-700">{{ session('creative_success') }}</p>
        @endif
        @foreach ($errors->creative->all() as $error)
            <p role="alert" class="mb-2 text-rose-700">{{ $error }}</p>
        @endforeach
        <form action="{{ route('invitations.creatives.submit') }}" method="POST" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <label class="block text-sm font-bold" for="submissionMode">طريقة عرض القالب</label>
            <select id="submissionMode" name="design_mode" required class="w-full rounded-xl border p-2" data-creative-design-mode>
                <option value="">اختر طريقة العرض</option>
                @foreach (\App\Models\CreativeTemplate::MODES as $mode => $label)
                    <option value="{{ $mode }}" @selected(old('design_mode') === $mode)>{{ $label }}</option>
                @endforeach
            </select>
            <label class="block text-sm font-bold" for="submissionType">نوع الدعوة</label>
            <select id="submissionType" name="invitation_type" required class="w-full rounded-xl border p-2" data-visual-invitation-type>
                <option value="">اختر نوع الدعوة</option>
                @foreach($templates as $tpl)
                    <option value="{{ $tpl['key'] }}" @selected(old('invitation_type') === $tpl['key'])>{{ $tpl['name'] }}</option>
                @endforeach
            </select>
            <label class="block text-sm font-bold" for="submissionName">اسم التصميم</label>
            <input id="submissionName" name="name" value="{{ old('name') }}" maxlength="255" class="w-full rounded-xl border p-2">
            <label class="block text-sm font-bold" for="submissionImage">صورة التصميم</label>
            <input id="submissionImage" name="image" type="file" accept="image/*" required class="w-full" data-visual-file-input>
            <x-dashboard.visual.text-layout-editor
                :fields-by-type="collect($templates)->mapWithKeys(fn ($tpl) => [$tpl['key'] => \App\Services\Invitations\CreativeTextLayout::editableFields($tpl['key'])])->all()"
            />
            <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" name="publication_consent" value="1" required @checked(old('publication_consent'))>
                <span>أوافق على عرض تصميمي للأعضاء في قوالب الصور إذا اعتمدته الإدارة.</span>
            </label>
            <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 font-bold text-white">إرسال للمراجعة</button>
        </form>
        @if ($myCreatives->isNotEmpty())
            <h3 class="font-bold mt-5 mb-2">تصاميمي</h3>
            <ul class="space-y-2 text-sm">
                @foreach($myCreatives as $design)
                    <li class="rounded-xl border p-2">
                        <strong>{{ $design->name }}</strong> · {{ $design->status_label }}
                        @if ($design->awaitsPublicationConsent())
                            <form method="POST" action="{{ route('invitations.creatives.consent', $design->id) }}" class="mt-2 space-y-2">
                                @csrf
                                <label class="flex items-start gap-2">
                                    <input type="checkbox" name="publication_consent" value="1" required>
                                    <span>أوافق على عرض هذا التصميم في قوالب الصور بعد اعتماده.</span>
                                </label>
                                <button type="submit" class="font-bold text-emerald-700">حفظ الموافقة</button>
                            </form>
                        @endif
                        @if ($design->rejection_reason)
                            <p class="text-rose-700">{{ $design->rejection_reason }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </dialog>
@endsection

@section('scripts')
    @include('web-site.invitations.partials.tree-picker-script')
    <script>
        const templates = @json($templates);
        const senderName = @json($person->full_name ?: $person->first_name);
        const invitationForm = document.getElementById('invitationForm');
        invitationForm.querySelectorAll('input[name="font_family"], input[name="font_weight"]').forEach(function (option) {
            option.addEventListener('change', function () {
                if (option.name === 'font_family') {
                    invitationForm.dataset.invitationFont = option.value;
                } else {
                    invitationForm.dataset.invitationWeight = option.value;
                }
            });
        });
        const creativeInput = document.getElementById('creative_id');
        const backgroundInput = document.getElementById('background_id');
        const attachment = document.getElementById('media');
        const previewImage = document.getElementById('simBg');
        let attachmentUrl = null;
        const creativeDialog = document.getElementById('creativeDialog');
        const creativeMode = document.getElementById('creativeModeSelect');
        const creativeOpen = document.getElementById('creativeOpen');
        const previewCreative = document.getElementById('previewCreative');
        const creativeStatus = document.getElementById('previewCreativeStatus');
        const submissionDialog = document.getElementById('designSubmission');
        document.getElementById('designSubmissionOpen').addEventListener('click', () => submissionDialog.showModal());
        document.getElementById('designSubmissionClose').addEventListener('click', () => submissionDialog.close());
        if (@json((bool) session('creative_success') || $errors->creative->any())) submissionDialog.showModal();
        creativeOpen.addEventListener('click', function () {
            creativeMode.value = getSelectedCreative()?.dataset.mode || '';
            refreshImages();
            creativeDialog.showModal();
        });
        document.getElementById('creativeClose').addEventListener('click', function () {
            creativeDialog.close();
        });
        creativeMode.addEventListener('change', refreshImages);

        function getSelectedCreative() {
            return Array.from(document.querySelectorAll('.creative-pick')).find((button) => button.dataset.id === creativeInput.value);
        }

        function refreshImages() {
            const type = document.getElementById('template_select').value;
            creativeOpen.style.display = type ? '' : 'none';
            const creative = getSelectedCreative();
            previewCreative.hidden = creative?.dataset.mode !== 'overlay';
            document.getElementById('creativeClear').hidden = !creativeInput.value && !backgroundInput.value;
            let count = 0;
            document.querySelectorAll('.creative-pick').forEach(function (button) {
                const show = Boolean(type) && button.dataset.type === type && button.dataset.mode === creativeMode.value;
                button.classList.toggle('hidden', !show);
                if (show) count++;
                const selected = creativeInput.value === button.dataset.id;
                button.classList.toggle('ring-2', selected);
                button.setAttribute('aria-pressed', String(selected));
            });
            document.getElementById('creativeEmpty').textContent = creativeMode.value && !count ? 'لا توجد قوالب معتمدة لهذا الوضع ونوع الدعوة.' : '';
            const selectedType = templates.find(function (item) {
                return item.key === document.getElementById('template_select').value;
            });
            document.getElementById('creativeLibraryLabel').textContent = selectedType
                ? 'صور ' + selectedType.name
                : '';
            document.querySelectorAll('.bg-pick').forEach(function (button) {
                button.setAttribute('aria-pressed', String(backgroundInput?.value === button.dataset.bgId));
                button.classList.toggle('ring-2', backgroundInput?.value === button.dataset.bgId);
                button.classList.toggle('ring-emerald-500', backgroundInput?.value === button.dataset.bgId);
            });
            const background = backgroundInput?.value
                ? document.querySelector('.bg-pick[data-bg-id="' + backgroundInput.value + '"]') : null;
            document.getElementById('creativeSelectionLabel').textContent = creative || background
                ? 'القالب المختار: ' + (creative || background).dataset.name
                : selectedType ? 'صور ' + selectedType.name : 'اختر قالبًا للدعوة';
            const url = attachmentUrl || creative?.dataset.url || background?.dataset.bgUrl;
            previewImage.classList.toggle('hidden', !url);
            if (url) previewImage.src = url;
            else previewImage.removeAttribute('src');
            updatePreview();
        }
        function clearAttachment() {
            attachment.value = '';
            if (attachmentUrl) URL.revokeObjectURL(attachmentUrl);
            attachmentUrl = null;
        }
        document.querySelectorAll('.creative-pick').forEach(function (button) {
            button.addEventListener('click', function () {
                creativeInput.value = button.dataset.id;
                if (backgroundInput) backgroundInput.value = '';
                clearAttachment();
                renderTemplateVars(document.getElementById('body').value);
                refreshImages();
                creativeDialog.close();
            });
        });
        previewCreative.addEventListener('click', async function () {
            const data = new FormData();
            for (const [name, value] of new FormData(invitationForm)) {
                if (name.startsWith('template_vars[') || ['_token', 'title', 'body', 'template_key', 'creative_id', 'font_family', 'font_weight'].includes(name)) {
                    data.append(name, value);
                }
            }
            previewCreative.disabled = true;
            creativeStatus.textContent = 'جاري تجهيز الصورة…';
            try {
                const response = await fetch(@json(route('invitations.preview-message')), { method: 'POST', headers: { Accept: 'application/json' }, body: data });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'تعذّر تجهيز الصورة.');
                if (data.get('creative_id') === creativeInput.value && result.card_image_url) previewImage.src = result.card_image_url;
                creativeStatus.textContent = '';
            } catch (error) {
                creativeStatus.textContent = error.message || 'تعذّر تجهيز الصورة.';
            } finally {
                previewCreative.disabled = false;
            }
        });
        document.querySelectorAll('.bg-pick').forEach(function (button) {
            button.addEventListener('click', function () {
                backgroundInput.value = button.dataset.bgId;
                creativeInput.value = '';
                clearAttachment();
                renderTemplateVars(document.getElementById('body').value);
                refreshImages();
                creativeDialog.close();
            });
        });
        document.getElementById('creativeClear').addEventListener('click', function () {
            creativeInput.value = '';
            backgroundInput.value = '';
            renderTemplateVars(document.getElementById('body').value);
            refreshImages();
        });
        attachment.addEventListener('change', function () {
            if (attachmentUrl) URL.revokeObjectURL(attachmentUrl);
            const file = attachment.files[0];
            attachmentUrl = file?.type.startsWith('image/') ? URL.createObjectURL(file) : null;
            creativeInput.value = '';
            if (backgroundInput) backgroundInput.value = '';
            renderTemplateVars(document.getElementById('body').value);
            refreshImages();
        });

        document.querySelectorAll('input[name="recipient_type"]').forEach(function (el) {
            el.addEventListener('change', function () {
                const val = this.value;
                document.getElementById('wrapperGroup').classList.toggle('hidden', val !== 'group');
                document.getElementById('wrapperPersons').classList.toggle('hidden', val !== 'persons');
                document.getElementById('wrapperExternal').classList.toggle('hidden', val !== 'external');
                document.getElementById('deceasedWrapper').classList.toggle('hidden', val === 'external');

                if (val === 'external') {
                    const waInput = document.querySelector('input[name="channels[]"][value="whatsapp"]');
                    if (waInput) waInput.checked = true;
                }
                updatePreview();
            });
        });

        function extractVars(body) {
            const found = body.match(/\{[a-z_]+\}/g) || [];
            return [...new Set(found)].filter(function (v) {
                return ['{name}','{first_name}','{full_name}','{sender_name}'].indexOf(v) === -1;
            });
        }

        function renderTemplateVars(body, values) {
            const box = document.getElementById('templateVars');
            const key = document.getElementById('template_key').value;
            const wedding = key === 'wedding' ? templates.find((item) => item.key === key)?.body || '' : '';
            const designVars = JSON.parse(getSelectedCreative()?.dataset.variables || '[]');
            const vars = [...new Set([...extractVars(body + '\n' + wedding), ...designVars.map((name) => '{' + name + '}')])];
            const inputs = Array.from(box.querySelectorAll('.tpl-var'));
            if (!values && inputs.map((input) => input.dataset.var).join() === vars.join()) return;
            values ??= Object.fromEntries(inputs.map((input) => [input.dataset.var.slice(1, -1), input.value]));
            const labels = { hosts: 'أصحاب الدعوة', groom: 'اسم العريس', date: 'التاريخ', time: 'الوقت', location: 'القاعة أو المكان' };
            box.innerHTML = '';
            if (!vars.length) { box.classList.add('hidden'); return; }
            box.classList.remove('hidden');
            vars.forEach(function (v) {
                const key = v.replace(/[{}]/g, '');
                const wrap = document.createElement('div');
                wrap.innerHTML = '<label for="inv-var-' + key + '" class="text-xs font-bold text-slate-500 mb-1 block">' + (labels[key] || key) + '</label>' +
                    '<input id="inv-var-' + key + '" name="template_vars[' + key + ']" data-var="' + v + '" class="tpl-var w-full px-3 py-2 border rounded-xl" maxlength="255">';
                wrap.querySelector('input').value = values[key] || '';
                box.appendChild(wrap);
            });
            box.querySelectorAll('.tpl-var').forEach(function (input) {
                input.addEventListener('input', updatePreview);
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
            document.getElementById('simBody').textContent = applyVars(document.getElementById('body').value || '');
        }

        const extNameInput = document.getElementById('external_name');
        if (extNameInput) {
            extNameInput.addEventListener('input', updatePreview);
        }

        document.getElementById('template_select').addEventListener('change', function () {
            const key = this.value;
            creativeInput.value = '';
            refreshImages();
            document.getElementById('template_key').value = key;
            if (!key) { renderTemplateVars(document.getElementById('body').value); updatePreview(); return; }
            const tpl = templates.find(function (t) { return t.key === key; });
            if (!tpl) return;
            document.getElementById('title').value = tpl.title || '';
            document.getElementById('body').value = tpl.body || '';
            renderTemplateVars(tpl.body || '', {});
            updatePreview();
        });

        document.getElementById('title').addEventListener('input', updatePreview);
        document.getElementById('body').addEventListener('input', function () {
            renderTemplateVars(this.value);
            updatePreview();
        });
        renderTemplateVars(document.getElementById('body').value, @json(old('template_vars', [])));
        refreshImages();
    </script>
    <script src="{{ asset('js/dashboard/creative-text-layout.js') }}?v={{ filemtime(public_path('js/dashboard/creative-text-layout.js')) }}" defer></script>
@endsection
