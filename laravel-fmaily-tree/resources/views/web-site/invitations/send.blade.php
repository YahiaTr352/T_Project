@extends('web-site.invitations.layout')

@section('title', 'إرسال دعوة')

@section('extra_styles')
    @include('web-site.invitations.partials.typography-fonts')
    .invitation-typed-text {
        font-family: 'Invitation Amiri', Amiri, serif;
        font-weight: 700;
    }
@endsection

@section('content')
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-extrabold text-slate-900">إرسال دعوة</h1>
            <p class="text-slate-600 mt-1">اختر مجموعتك أو أشخاصاً من الشجرة، ثم أرسل واتساب و/أو إشعار التطبيق</p>
        </div>
    </div>

    <form id="invitationForm" action="{{ route('invitations.send.submit') }}" method="POST" enctype="multipart/form-data"
        class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        @csrf
        <div class="lg:col-span-8 space-y-6">
            <div class="glass-card rounded-3xl p-6">
                <h2 class="text-xl font-bold mb-4">١. قنوات الإرسال</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="relative flex flex-col items-center p-5 rounded-2xl border-2 border-emerald-400 bg-white cursor-pointer">
                        <input type="checkbox" name="channels[]" value="whatsapp" @checked(in_array('whatsapp', (array) old('channels', ['whatsapp']), true)) class="absolute top-4 right-4 w-5 h-5 accent-emerald-600">
                        <i class="fab fa-whatsapp text-4xl text-emerald-500 mb-2"></i>
                        <span class="font-bold">واتساب</span>
                    </label>
                    <label class="relative flex flex-col items-center p-5 rounded-2xl border-2 border-blue-300 bg-white cursor-pointer">
                        <input type="checkbox" name="channels[]" value="push" @checked(in_array('push', (array) old('channels', ['whatsapp']), true)) class="absolute top-4 right-4 w-5 h-5 accent-blue-600">
                        <i class="fas fa-bell text-4xl text-blue-500 mb-2"></i>
                        <span class="font-bold">إشعار التطبيق</span>
                        <span id="pushChannelHint" class="text-xs text-slate-500 mt-1"></span>
                    </label>
                </div>
            </div>

            <div class="glass-card rounded-3xl p-6">
                <h2 class="text-xl font-bold mb-4">٢. المستلمون</h2>
                <div class="flex flex-wrap gap-3 mb-4">
                    <label class="flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-50 hover:bg-slate-100 cursor-pointer transition">
                        <input type="radio" name="recipient_type" value="group" @checked(old('recipient_type', 'persons') === 'group') class="accent-emerald-600">
                        <span class="font-semibold text-sm">مجموعة جاهزة</span>
                    </label>
                    <label class="flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-50 hover:bg-slate-100 cursor-pointer transition">
                        <input type="radio" name="recipient_type" value="persons" @checked(old('recipient_type', 'persons') === 'persons') class="accent-emerald-600">
                        <span class="font-semibold text-sm">أشخاص من الشجرة</span>
                    </label>
                    <label class="flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-50/70 border border-emerald-200 hover:bg-emerald-100/70 cursor-pointer transition">
                        <input type="radio" name="recipient_type" value="external" @checked(old('recipient_type', 'persons') === 'external') class="accent-emerald-600">
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
                            <option value="{{ $g->id }}" @selected((string) old('group_id') === (string) $g->id)>{{ $g->name }} ({{ $g->persons_count }} شخص)</option>
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
                <fieldset id="templateVarSection" class="mb-4" hidden>
                    <legend class="mb-2 text-sm font-bold">تفاصيل القالب</legend>
                    <div id="templateVars" class="grid grid-cols-1 gap-3 sm:grid-cols-2"></div>
                </fieldset>
                <input type="text" name="title" id="title" value="{{ old('title') }}" placeholder="عنوان الإشعار" class="invitation-typed-text w-full px-4 py-3 border rounded-xl mb-3">
                <textarea name="body" id="body" rows="7" placeholder="النص اختياري عند اختيار صورة" class="invitation-typed-text w-full px-4 py-3 border rounded-xl">{{ old('body') }}</textarea>
                <p class="text-xs text-slate-400 mt-2">المتغيرات: {name} {full_name} {sender_name}</p>
            </div>

            <div class="glass-card rounded-3xl p-6">
                <h2 class="text-xl font-bold mb-4">الصورة والتصميم</h2>
                <input type="hidden" name="creative_id" id="creative_id" value="{{ old('creative_id') }}">
                <span id="preparedArtworkPreview" hidden></span>
                <input type="hidden" name="creative_edit_source" id="creativeArtworkSource"
                    value="{{ is_string(old('creative_edit_source')) ? old('creative_edit_source') : '' }}">
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
                    <button type="button" id="preparedArtworkOpen" aria-haspopup="dialog" aria-controls="preparedArtworkDialog"
                        class="rounded-xl border border-emerald-700 px-3 py-2 text-sm font-bold text-emerald-800" hidden>تعديل كتابة القالب</button>
                    <button type="button" id="creativeClear" class="text-xs underline" hidden>إزالة قالب الصورة</button>
                </div>

                <dialog id="creativeDialog" aria-labelledby="creativeDialogTitle" dir="rtl"
                    class="rounded-3xl p-6 shadow-xl" style="width:min(92vw,720px);max-height:85vh;overflow:auto">
                    <div class="flex items-center justify-between gap-4 mb-3">
                        <h3 id="creativeDialogTitle" class="font-bold text-xl">قوالب الصور</h3>
                        <button type="button" id="creativeClose" aria-label="إغلاق قوالب الصور"
                            class="px-3 py-2 rounded-xl border">إغلاق</button>
                    </div>
                    <p id="creativeLibraryLabel" class="text-sm text-slate-600 mb-4"></p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach($creatives as $creative)
                            <div class="creative-card rounded-xl border border-slate-200 p-2" data-gallery-card hidden>
                                <button type="button" class="creative-pick w-full rounded-lg border-2 border-transparent p-1"
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
                                    <img data-gallery-image="{{ route('invitations.creatives.preview-image', $creative->id, absolute: false) }}" alt="" class="w-full h-36 object-contain">
                                    <span class="text-xs font-bold">{{ $creative->name }}</span>
                                </button>
                                <button type="button" data-gallery-zoom class="mt-1 w-full rounded-lg border border-slate-200 py-2 text-xs font-bold text-emerald-800"
                                    aria-label="معاينة {{ $creative->name }}">معاينة الصورة</button>
                            </div>
                        @endforeach
                    </div>
                    <p id="creativeEmpty" class="text-xs text-slate-500 mt-2"></p>
                    <button type="button" id="creativeConfirm" disabled class="mt-4 rounded-xl bg-emerald-700 px-4 py-2 font-bold text-white disabled:opacity-50">استخدام القالب</button>
                </dialog>
                <dialog id="preparedArtworkDialog" aria-labelledby="preparedArtworkTitle" dir="rtl"
                    data-preview-url="{{ route('invitations.preview-artwork', absolute: false) }}"
                    data-svg-fonts="{{ asset('fonts') }}"
                    class="rounded-3xl p-5 shadow-xl" style="width:min(94vw,680px);max-height:90vh;overflow:auto">
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <h3 id="preparedArtworkTitle" class="font-bold text-xl">تعديل كتابة القالب</h3>
                        <button type="button" id="preparedArtworkCancel" class="rounded-xl border px-3 py-2">إلغاء</button>
                    </div>
                    <p class="text-sm mb-3">اضغط على النص في الصورة أو اختر الحقل أدناه.</p>
                    <div class="text-center">
                        <div dir="ltr" style="display:inline-block;position:relative;max-width:100%;line-height:0">
                            <img id="preparedArtworkOriginal" alt="صورة القالب" style="display:block;max-width:100%;max-height:48vh;width:auto;height:auto">
                            <img id="preparedArtworkLive" alt="معاينة الكتابة المعدّلة" hidden
                                style="position:absolute;inset:0;width:100%;height:100%;pointer-events:none">
                            <div id="preparedArtworkTargets"></div>
                        </div>
                    </div>
                    <div id="preparedArtworkFields" class="grid gap-3 mt-3"></div>
                    <p id="preparedArtworkStatus" class="text-sm mt-2" role="status"></p>
                    <button type="button" id="preparedArtworkRetry" class="text-sm underline" hidden>إعادة المعاينة</button>
                    <button type="button" id="preparedArtworkApply"
                        class="mt-4 rounded-xl bg-emerald-700 px-4 py-2 font-bold text-white disabled:opacity-50">تطبيق التعديلات</button>
                </dialog>
                @if($backgrounds->isNotEmpty())
                    <details class="mt-4">
                        <summary class="font-bold text-sm mb-2">خلفيات عامة</summary>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            @foreach($backgrounds as $bg)
                                @php($backgroundUrl = filter_var($bg->image_path, FILTER_VALIDATE_URL) ? $bg->image_url : asset('storage/'.$bg->image_path))
                                <button type="button" data-bg-id="{{ $bg->id }}" data-bg-url="{{ $backgroundUrl }}"
                                    data-name="{{ $bg->name }}" aria-pressed="false"
                                    class="bg-pick rounded-2xl overflow-hidden border-2 border-transparent" title="{{ $bg->name }}">
                                    <img src="{{ $backgroundUrl }}" class="w-full h-40 object-contain" alt="{{ $bg->name }}">
                                </button>
                            @endforeach
                        </div>
                    </details>
                @endif

                <label for="media" class="block font-bold text-sm mt-5 mb-2">مرفق اختياري من جهازك</label>
                <input type="file" id="media" name="media" accept="image/*,video/*,audio/*" class="w-full">
                <input type="hidden" name="media_type" id="media_type" value="">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <button type="submit" class="rounded-2xl bg-emerald-700 px-3 py-3 font-bold text-white hover:bg-emerald-800">إرسال الدعوة</button>
                <button type="button" id="previewInvitation" aria-haspopup="dialog" aria-controls="invitationPreviewDialog"
                    class="rounded-2xl border border-emerald-700 bg-white px-3 py-3 font-bold text-emerald-800 disabled:opacity-50">معاينة</button>
            </div>
            <p id="invitationPreviewStatus" role="status" class="text-sm text-rose-700"></p>
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
    <dialog id="invitationPreviewDialog" aria-labelledby="invitationPreviewTitle" dir="rtl"
        class="m-auto w-11/12 max-w-2xl rounded-3xl p-5 shadow-xl backdrop:bg-slate-900/50">
        <h2 id="invitationPreviewTitle" class="mb-3 text-xl font-bold">معاينة الدعوة</h2>
        <img id="invitationPreviewArtwork" class="mx-auto max-h-[50vh] max-w-full rounded-xl object-contain" alt="صورة معاينة الدعوة" hidden>
        <p id="invitationPreviewImageError" role="status" class="text-sm text-rose-700" hidden>تعذّر تحميل صورة المعاينة.</p>
        <p id="invitationPreviewText" class="invitation-typed-text my-4 whitespace-pre-wrap break-words"></p>
        <form method="dialog"><button class="w-full rounded-xl bg-emerald-700 p-3 font-bold text-white">إغلاق</button></form>
    </dialog>
    <dialog id="galleryPreviewDialog" aria-labelledby="galleryPreviewTitle" dir="rtl"
        class="m-auto w-11/12 max-w-4xl rounded-3xl p-4 shadow-xl backdrop:bg-slate-900/60">
        <div class="mb-3 flex items-center justify-between gap-2">
            <h2 id="galleryPreviewTitle" class="font-bold">معاينة القالب</h2>
            <form method="dialog"><button class="rounded-xl border px-3 py-2">إغلاق</button></form>
        </div>
        <img id="galleryPreviewArtwork" class="mx-auto max-h-[72vh] max-w-full rounded-xl object-contain" alt="صورة القالب">
        <a id="galleryOpenFull" target="_blank" rel="noopener" class="mt-2 inline-block text-sm font-bold text-emerald-800">عرض بالحجم الكامل</a>
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
        let pendingCreative = null;
        let renderedCreativeId = null;
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
            const type = document.getElementById('template_select').value;
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
            const type = document.getElementById('template_select').value;
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
            renderTemplateVars(document.getElementById('body').value);
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
            document.getElementById('media_type').value = { image: 'image', video: 'video', audio: 'voice' }[file?.type.split('/')[0]] || '';
            creativeInput.value = '';
            if (backgroundInput) backgroundInput.value = '';
            renderTemplateVars(document.getElementById('body').value);
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
            box.innerHTML = '';
            const prepared = creative?.dataset.preparedArtwork === 'true';
            document.getElementById('templateVarSection').hidden = !vars.some((key) => !prepared || !editableFields.has(key.slice(1, -1)));
            vars.forEach(function (v) {
                const key = v.replace(/[{}]/g, '');
                const wrap = document.createElement('div');
                const field = editableFields.get(key);
                const label = document.createElement('label');
                label.htmlFor = 'inv-var-' + key;
                label.className = 'text-xs font-bold text-slate-500 mb-1 block';
                label.textContent = field?.label || labels[key] || key;
                const input = document.createElement(prepared && field ? 'textarea' : 'input');
                wrap.hidden = Boolean(prepared && field);
                input.id = label.htmlFor;
                input.name = 'template_vars[' + key + ']';
                input.dataset.var = v;
                input.className = 'tpl-var w-full px-3 py-2 border rounded-xl';
                input.maxLength = 255;
                input.value = Object.hasOwn(values, key) ? (values[key] ?? '') : (field?.default_text ?? '');
                input.addEventListener('input', updatePreview);
                wrap.append(label, input);
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
        const previousValues = @json(old('template_vars', [])) || {};
        const currentRevision = getSelectedCreative()?.dataset.artworkRevision;
        const previousRevision = document.getElementById('creativeArtworkSource').value;
        if (currentRevision && previousRevision && currentRevision !== previousRevision) {
            for (const key of Object.keys(previousValues)) {
                if (key.startsWith('creative_text_')) delete previousValues[key];
            }
        }
        renderTemplateVars(document.getElementById('body').value, previousValues);
        refreshImages();
        syncRecipientType();
    </script>
@endsection
