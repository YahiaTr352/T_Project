{{-- ================================================================
CONTACT & SUGGESTIONS FORM (Matching Mobile App contact.tsx)
================================================================ --}}
@php
    $currentPersonId = session('site_person_id');
    $currentAuthMethod = session('site_auth_method');
    $currentPerson = null;
    $hasVerifiedPerson = false;

    if ($currentPersonId && in_array($currentAuthMethod, ['face', 'whatsapp'], true)) {
        $currentPerson = \App\Models\Person::find($currentPersonId);
        $hasVerifiedPerson = $currentPerson !== null;
    }

    // Default WhatsApp Number
    $defaultWhatsappNumber = '966539895800';
    if (isset($channels)) {
        $waChannel = $channels->firstWhere('type', \App\Models\SupportChannel::TYPE_WHATSAPP);
        if ($waChannel && !empty($waChannel->value)) {
            $cleaned = preg_replace('/\D+/', '', $waChannel->value);
            if (!empty($cleaned)) {
                $defaultWhatsappNumber = $cleaned;
            }
        }
    }
@endphp

<div class="glass-card rounded-3xl p-6 sm:p-10 shadow-xl border border-emerald-100/80 relative overflow-hidden">
    {{-- Top Gradient Line --}}
    <div class="absolute top-0 right-0 left-0 h-2 bg-gradient-to-r from-emerald-600 via-teal-500 to-emerald-400"></div>

    {{-- Form Header --}}
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-600 mb-3 shadow-inner">
            <i class="fas fa-paper-plane text-2xl"></i>
        </div>
        <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-800 tracking-tight mb-2">
            صندوق الاقتراحات والملاحظات
        </h2>
        <p class="text-slate-600 text-sm sm:text-base max-w-xl mx-auto leading-relaxed">
            تواصل مباشر مع إدارة ومجلس العائلة، نسعد باستقبال أفكاركم وملاحظاتكم أو بلاغاتكم التقنية.
        </p>
    </div>

    {{-- Identity Status Banner --}}
    @if($hasVerifiedPerson)
        <div class="mb-6 p-4 rounded-2xl bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-200/80 flex items-center gap-3.5 shadow-sm">
            <div class="w-10 h-10 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-base flex-shrink-0 shadow-sm">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="font-bold text-emerald-900 text-sm sm:text-base truncate">
                    أهلاً بك، {{ $currentPerson->first_name }} {{ $currentPerson->last_name }}
                </div>
                <div class="text-xs sm:text-sm text-emerald-700">
                    سيتم توثيق مشاركتك واقتراحك برقمك المسجل ({{ $currentPerson->phone ?: 'مسجل' }}) لدى إدارة العائلة.
                </div>
            </div>
        </div>
    @else
        <div class="mb-6 p-4 rounded-2xl bg-amber-50/90 border border-amber-200/80 flex items-start sm:items-center gap-3.5 shadow-sm">
            <div class="w-9 h-9 rounded-full bg-amber-500 text-white flex items-center justify-center text-sm flex-shrink-0 mt-0.5 sm:mt-0">
                <i class="fas fa-info-circle"></i>
            </div>
            <div class="flex-1">
                <div class="font-bold text-amber-900 text-sm mb-0.5">الدخول كزائر</div>
                <div class="text-xs sm:text-sm text-amber-800 leading-relaxed">
                    يرجى إدخال اسمك ورقم جوالك لنتمكن من متابعة رسالتك والتواصل معك بسهولة.
                </div>
            </div>
        </div>
    @endif

    {{-- Form --}}
    <form id="supportContactForm" action="{{ route('contact-messages.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="source" value="web">
        <input type="hidden" name="media_type" id="contact_media_type" value="none">

        {{-- Guest Inputs --}}
        @if(!$hasVerifiedPerson)
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
                <div>
                    <label class="block text-xs sm:text-sm font-semibold text-slate-700 mb-1.5 text-right">
                        الاسم الكامل <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-slate-400">
                            <i class="fas fa-user text-sm"></i>
                        </span>
                        <input type="text" name="name" id="contact_name" required maxlength="150"
                            placeholder="مثال: صالح السريّع"
                            class="w-full pr-10 pl-3.5 py-2.5 rounded-xl border border-slate-200 text-slate-800 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition">
                    </div>
                </div>

                <div>
                    <label class="block text-xs sm:text-sm font-semibold text-slate-700 mb-1.5 text-right">
                        رقم الجوال <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-slate-400">
                            <i class="fas fa-phone-alt text-sm"></i>
                        </span>
                        <input type="tel" name="phone" id="contact_phone" required maxlength="30"
                            placeholder="05xxxxxxxx" dir="ltr"
                            class="w-full pr-10 pl-3.5 py-2.5 rounded-xl border border-slate-200 text-slate-800 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition text-right">
                    </div>
                </div>
            </div>
        @else
            <input type="hidden" name="name" id="contact_name" value="{{ $currentPerson->first_name }} {{ $currentPerson->last_name }}">
            <input type="hidden" name="phone" id="contact_phone" value="{{ $currentPerson->phone }}">
        @endif

        {{-- Message Type Selection --}}
        <div class="mb-5">
            <label class="block text-xs sm:text-sm font-semibold text-slate-700 mb-2.5 text-right">
                تصنيف الرسالة أو المشاركة:
            </label>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                <label class="cursor-pointer">
                    <input type="radio" name="type" value="suggestion" checked class="peer sr-only">
                    <div class="p-3 rounded-xl border border-slate-200 text-center text-xs sm:text-sm font-semibold text-slate-600 peer-checked:bg-emerald-50 peer-checked:border-emerald-500 peer-checked:text-emerald-700 hover:bg-slate-50 transition flex items-center justify-center gap-2 shadow-sm">
                        <i class="fas fa-lightbulb text-emerald-500 text-base"></i>
                        <span>اقتراح وتطوير</span>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="type" value="problem" class="peer sr-only">
                    <div class="p-3 rounded-xl border border-slate-200 text-center text-xs sm:text-sm font-semibold text-slate-600 peer-checked:bg-rose-50 peer-checked:border-rose-500 peer-checked:text-rose-700 hover:bg-slate-50 transition flex items-center justify-center gap-2 shadow-sm">
                        <i class="fas fa-exclamation-triangle text-rose-500 text-base"></i>
                        <span>مشكلة تقنية</span>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="type" value="inquiry" class="peer sr-only">
                    <div class="p-3 rounded-xl border border-slate-200 text-center text-xs sm:text-sm font-semibold text-slate-600 peer-checked:bg-blue-50 peer-checked:border-blue-500 peer-checked:text-blue-700 hover:bg-slate-50 transition flex items-center justify-center gap-2 shadow-sm">
                        <i class="fas fa-question-circle text-blue-500 text-base"></i>
                        <span>استفسار عام</span>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="type" value="complaint" class="peer sr-only">
                    <div class="p-3 rounded-xl border border-slate-200 text-center text-xs sm:text-sm font-semibold text-slate-600 peer-checked:bg-amber-50 peer-checked:border-amber-500 peer-checked:text-amber-700 hover:bg-slate-50 transition flex items-center justify-center gap-2 shadow-sm">
                        <i class="fas fa-comment-dots text-amber-500 text-base"></i>
                        <span>شكوى أو ملاحظة</span>
                    </div>
                </label>
            </div>
        </div>

        {{-- Message Textarea --}}
        <div class="mb-5">
            <div class="flex items-center justify-between mb-1.5">
                <label class="block text-xs sm:text-sm font-semibold text-slate-700 text-right">
                    نص الاقتراح أو الملاحظة:
                </label>
                <span id="contact_char_counter" class="text-xs text-slate-400 font-medium">0 حرفاً</span>
            </div>
            <textarea name="message" id="contact_message" rows="5" maxlength="4000"
                placeholder="اكتب هنا فكرتك، اقتراحك، أو ملاحظاتك وتوجيهاتك لمجلس الإدارة..."
                class="w-full p-4 rounded-xl border border-slate-200 text-slate-800 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition leading-relaxed"></textarea>
        </div>

        {{-- Section: Media Attachments --}}
        <div class="mb-6">
            <label class="block text-xs sm:text-sm font-semibold text-slate-700 mb-2 text-right">
                إرفاق وسائط توضيحية (اختياري):
            </label>

            {{-- Hidden File Inputs --}}
            <input type="file" id="contact_media_image" accept="image/jpeg,image/png,image/webp,image/gif" class="sr-only">
            <input type="file" id="contact_media_video" accept="video/mp4,video/quicktime,video/webm" class="sr-only">
            <input type="file" id="contact_media_audio" accept="audio/*" class="sr-only">
            <input type="file" name="media_file" id="contact_media_file_actual" class="sr-only">

            {{-- 1. Three Attachment Option Buttons (Visible when no active recording and no attached media) --}}
            <div id="media_buttons_grid" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                {{-- Voice Recording Button --}}
                <button type="button" id="btn_record_voice"
                    class="flex items-center gap-3 p-3.5 rounded-2xl border border-slate-200/90 hover:border-amber-300 bg-white hover:bg-amber-50/40 text-right transition group shadow-sm">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center text-lg flex-shrink-0 group-hover:scale-105 transition-transform">
                        <i class="fas fa-microphone"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-xs sm:text-sm font-bold text-slate-800 group-hover:text-amber-700 transition-colors">
                            تسجيل صوتي
                        </div>
                        <div class="text-[11px] text-slate-500 truncate">
                            اشرح فكرتك بصوتك
                        </div>
                    </div>
                </button>

                {{-- Image Attachment Button --}}
                <button type="button" id="btn_attach_image"
                    class="flex items-center gap-3 p-3.5 rounded-2xl border border-slate-200/90 hover:border-emerald-300 bg-white hover:bg-emerald-50/40 text-right transition group shadow-sm">
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-lg flex-shrink-0 group-hover:scale-105 transition-transform">
                        <i class="fas fa-image"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-xs sm:text-sm font-bold text-slate-800 group-hover:text-emerald-700 transition-colors">
                            صورة توضيحية
                        </div>
                        <div class="text-[11px] text-slate-500 truncate">
                            لقطة شاشة أو صورة
                        </div>
                    </div>
                </button>

                {{-- Video Attachment Button --}}
                <button type="button" id="btn_attach_video"
                    class="flex items-center gap-3 p-3.5 rounded-2xl border border-slate-200/90 hover:border-rose-300 bg-white hover:bg-rose-50/40 text-right transition group shadow-sm">
                    <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center text-lg flex-shrink-0 group-hover:scale-105 transition-transform">
                        <i class="fas fa-video"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-xs sm:text-sm font-bold text-slate-800 group-hover:text-rose-700 transition-colors">
                            مقطع فيديو
                        </div>
                        <div class="text-[11px] text-slate-500 truncate">
                            فيديو حتى 60 ثانية
                        </div>
                    </div>
                </button>
            </div>

            {{-- 2. Active Voice Recording Bar --}}
            <div id="recording_bar" class="hidden p-4 rounded-2xl bg-rose-50 border border-rose-300 items-center justify-between gap-3 shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="w-3.5 h-3.5 rounded-full bg-rose-500 animate-ping"></span>
                    <span class="text-xs sm:text-sm font-bold text-rose-800">
                        جارٍ التسجيل: <span id="recording_timer">00:00</span>
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" id="btn_cancel_recording"
                        class="px-3 py-1.5 rounded-xl border border-rose-300 bg-white hover:bg-rose-100 text-rose-700 text-xs font-bold transition">
                        إلغاء
                    </button>
                    <button type="button" id="btn_save_recording"
                        class="px-3.5 py-1.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-sm transition inline-flex items-center gap-1.5">
                        <i class="fas fa-check text-[11px]"></i>
                        <span>حفظ</span>
                    </button>
                </div>
            </div>

            {{-- 3. Attached Media Preview Card --}}
            <div id="attached_preview_card" class="hidden p-4 rounded-2xl bg-slate-50 border border-slate-200/90 items-center justify-between gap-3 shadow-sm">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    {{-- Media Thumbnail / Icon --}}
                    <div id="preview_icon_wrapper" class="w-12 h-12 rounded-xl flex items-center justify-center text-xl flex-shrink-0 bg-emerald-100 text-emerald-600 overflow-hidden border border-slate-200">
                        <img id="preview_thumb_img" src="" alt="" class="w-full h-full object-cover hidden">
                        <i id="preview_thumb_icon" class="fas fa-file"></i>
                    </div>

                    {{-- Info --}}
                    <div class="min-w-0 flex-1">
                        <div id="preview_media_title" class="font-bold text-slate-800 text-xs sm:text-sm truncate">
                            ملف مرفق
                        </div>
                        <div class="text-[11px] text-slate-500">
                            جاهز للإرسال مع الاقتراح
                        </div>
                        {{-- Audio Player --}}
                        <div id="preview_audio_player_wrap" class="hidden mt-2">
                            <audio id="preview_audio_element" controls class="h-8 max-w-full"></audio>
                        </div>
                    </div>
                </div>

                {{-- Remove Button --}}
                <button type="button" id="btn_remove_attached_media"
                    class="w-8 h-8 rounded-full bg-slate-200/80 hover:bg-rose-100 hover:text-rose-600 text-slate-500 flex items-center justify-center transition flex-shrink-0"
                    title="إزالة المرفق">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>
        </div>

        {{-- Status Alert (Feedback) --}}
        <div id="contactFormAlert" class="hidden mb-5 p-4 rounded-xl text-sm font-medium"></div>

        {{-- Dual Action CTAs (Matching Mobile Contact Screen) --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 pt-2">
            {{-- Primary Server Submit --}}
            <button type="submit" id="btnSubmitServer"
                class="w-full flex items-center justify-center gap-2.5 py-3.5 px-5 rounded-2xl bg-gradient-to-r from-emerald-700 to-emerald-600 hover:from-emerald-800 hover:to-emerald-700 active:scale-[0.99] text-white font-bold text-sm sm:text-base shadow-md shadow-emerald-700/20 transition">
                <i class="fas fa-paper-plane text-sm"></i>
                <span id="btnSubmitServerText">إرسال الاقتراح رسمياً لإدارة العائلة</span>
            </button>

            {{-- WhatsApp Action --}}
            <button type="button" id="btnSendWhatsapp"
                class="w-full flex items-center justify-center gap-2.5 py-3.5 px-5 rounded-2xl border-2 border-[#25D366] bg-[#25D366]/10 hover:bg-[#25D366]/20 text-[#15803D] active:scale-[0.99] font-bold text-sm sm:text-base transition">
                <i class="fab fa-whatsapp text-xl text-[#25D366]"></i>
                <span>أو إرسال المحتوى مباشرة عبر واتساب</span>
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('supportContactForm');
    if (!form) return;

    const messageInput = document.getElementById('contact_message');
    const charCounter = document.getElementById('contact_char_counter');
    const mediaTypeInput = document.getElementById('contact_media_type');
    const actualFileInput = document.getElementById('contact_media_file_actual');

    const imageInput = document.getElementById('contact_media_image');
    const videoInput = document.getElementById('contact_media_video');
    const audioInput = document.getElementById('contact_media_audio');

    const btnRecordVoice = document.getElementById('btn_record_voice');
    const btnAttachImage = document.getElementById('btn_attach_image');
    const btnAttachVideo = document.getElementById('btn_attach_video');

    const mediaButtonsGrid = document.getElementById('media_buttons_grid');
    const recordingBar = document.getElementById('recording_bar');
    const recordingTimer = document.getElementById('recording_timer');
    const btnCancelRecording = document.getElementById('btn_cancel_recording');
    const btnSaveRecording = document.getElementById('btn_save_recording');

    const previewCard = document.getElementById('attached_preview_card');
    const previewThumbImg = document.getElementById('preview_thumb_img');
    const previewThumbIcon = document.getElementById('preview_thumb_icon');
    const previewIconWrapper = document.getElementById('preview_icon_wrapper');
    const previewMediaTitle = document.getElementById('preview_media_title');
    const previewAudioWrap = document.getElementById('preview_audio_player_wrap');
    const previewAudioElement = document.getElementById('preview_audio_element');
    const btnRemoveAttachedMedia = document.getElementById('btn_remove_attached_media');

    const alertBox = document.getElementById('contactFormAlert');
    const btnSubmit = document.getElementById('btnSubmitServer');
    const btnSubmitText = document.getElementById('btnSubmitServerText');
    const btnWhatsapp = document.getElementById('btnSendWhatsapp');

    const FAMILY_WA_NUMBER = '{{ $defaultWhatsappNumber }}';
    const hasIdentity = {{ $hasVerifiedPerson ? 'true' : 'false' }};

    // Live Character Counter
    if (messageInput && charCounter) {
        messageInput.addEventListener('input', function () {
            charCounter.textContent = this.value.length + ' حرفاً';
        });
    }

    // Media State
    let attachedBlob = null;
    let attachedKind = 'none'; // 'none' | 'image' | 'video' | 'voice'
    let mediaRecorder = null;
    let audioChunks = [];
    let recordInterval = null;
    let recordSeconds = 0;

    function resetMediaState() {
        attachedBlob = null;
        attachedKind = 'none';
        mediaTypeInput.value = 'none';
        actualFileInput.value = '';
        imageInput.value = '';
        videoInput.value = '';
        audioInput.value = '';

        if (previewAudioElement) {
            previewAudioElement.pause();
            previewAudioElement.src = '';
        }

        previewThumbImg.src = '';
        previewThumbImg.classList.add('hidden');
        previewThumbIcon.classList.remove('hidden');
        previewAudioWrap.classList.add('hidden');

        previewCard.classList.add('hidden');
        previewCard.classList.remove('flex');
        mediaButtonsGrid.classList.remove('hidden');
    }

    function showAttachedPreview(kind, title, thumbUrl = null, audioBlobUrl = null) {
        attachedKind = kind;
        mediaTypeInput.value = kind;
        mediaButtonsGrid.classList.add('hidden');
        previewCard.classList.remove('hidden');
        previewCard.classList.add('flex');

        previewMediaTitle.textContent = title;

        if (kind === 'image') {
            previewIconWrapper.className = 'w-12 h-12 rounded-xl flex items-center justify-center text-xl flex-shrink-0 bg-emerald-100 text-emerald-600 overflow-hidden border border-slate-200';
            if (thumbUrl) {
                previewThumbImg.src = thumbUrl;
                previewThumbImg.classList.remove('hidden');
                previewThumbIcon.classList.add('hidden');
            } else {
                previewThumbIcon.className = 'fas fa-image';
                previewThumbIcon.classList.remove('hidden');
                previewThumbImg.classList.add('hidden');
            }
            previewAudioWrap.classList.add('hidden');
        } else if (kind === 'video') {
            previewIconWrapper.className = 'w-12 h-12 rounded-xl flex items-center justify-center text-xl flex-shrink-0 bg-rose-100 text-rose-600 overflow-hidden border border-slate-200';
            previewThumbIcon.className = 'fas fa-video';
            previewThumbIcon.classList.remove('hidden');
            previewThumbImg.classList.add('hidden');
            previewAudioWrap.classList.add('hidden');
        } else if (kind === 'voice') {
            previewIconWrapper.className = 'w-12 h-12 rounded-xl flex items-center justify-center text-xl flex-shrink-0 bg-amber-100 text-amber-600 overflow-hidden border border-slate-200';
            previewThumbIcon.className = 'fas fa-microphone';
            previewThumbIcon.classList.remove('hidden');
            previewThumbImg.classList.add('hidden');
            if (audioBlobUrl) {
                previewAudioElement.src = audioBlobUrl;
                previewAudioWrap.classList.remove('hidden');
            }
        }
    }

    // Remove media handler
    btnRemoveAttachedMedia.addEventListener('click', function () {
        resetMediaState();
    });

    // Image Picker
    btnAttachImage.addEventListener('click', function () {
        imageInput.click();
    });

    imageInput.addEventListener('change', function () {
        if (this.files && this.files[0]) {
            const file = this.files[0];
            const reader = new FileReader();
            reader.onload = function (e) {
                showAttachedPreview('image', file.name || 'صورة توضيحية مرفقة', e.target.result);
            };
            reader.readAsDataURL(file);

            // Assign to actual input via DataTransfer
            const dt = new DataTransfer();
            dt.items.add(file);
            actualFileInput.files = dt.files;
            attachedBlob = null;
        }
    });

    // Video Picker
    btnAttachVideo.addEventListener('click', function () {
        videoInput.click();
    });

    videoInput.addEventListener('change', function () {
        if (this.files && this.files[0]) {
            const file = this.files[0];
            showAttachedPreview('video', file.name || 'مقطع فيديو توضيحي');

            const dt = new DataTransfer();
            dt.items.add(file);
            actualFileInput.files = dt.files;
            attachedBlob = null;
        }
    });

    // Audio Voice Recording via Web MediaRecorder API
    function formatTime(secs) {
        const m = Math.floor(secs / 60);
        const s = secs % 60;
        return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
    }

    btnRecordVoice.addEventListener('click', async function () {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.MediaRecorder) {
            // Fallback for browsers that don't support MediaRecorder
            audioInput.click();
            return;
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            audioChunks = [];
            
            // Choose supported mimeType
            let mimeType = 'audio/webm';
            if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
                mimeType = 'audio/webm;codecs=opus';
            } else if (MediaRecorder.isTypeSupported('audio/ogg;codecs=opus')) {
                mimeType = 'audio/ogg;codecs=opus';
            } else if (MediaRecorder.isTypeSupported('audio/mp4')) {
                mimeType = 'audio/mp4';
            }

            mediaRecorder = new MediaRecorder(stream, { mimeType: mimeType });

            mediaRecorder.ondataavailable = function (e) {
                if (e.data.size > 0) {
                    audioChunks.push(e.data);
                }
            };

            mediaRecorder.onstop = function () {
                stream.getTracks().forEach(track => track.stop());
                if (audioChunks.length > 0) {
                    const finalBlob = new Blob(audioChunks, { type: mimeType });
                    attachedBlob = finalBlob;
                    const durationStr = formatTime(recordSeconds);
                    const blobUrl = URL.createObjectURL(finalBlob);
                    showAttachedPreview('voice', `تسجيل صوتي (${durationStr})`, null, blobUrl);
                }
            };

            mediaRecorder.start(250);
            recordSeconds = 0;
            recordingTimer.textContent = '00:00';
            mediaButtonsGrid.classList.add('hidden');
            recordingBar.classList.remove('hidden');
            recordingBar.classList.add('flex');

            recordInterval = setInterval(function () {
                recordSeconds++;
                recordingTimer.textContent = formatTime(recordSeconds);
            }, 1000);
        } catch (err) {
            // Fallback to file picker if mic access denied
            audioInput.click();
        }
    });

    // Fallback audio picker
    audioInput.addEventListener('change', function () {
        if (this.files && this.files[0]) {
            const file = this.files[0];
            const blobUrl = URL.createObjectURL(file);
            showAttachedPreview('voice', file.name || 'تسجيل صوتي مرفق', null, blobUrl);

            const dt = new DataTransfer();
            dt.items.add(file);
            actualFileInput.files = dt.files;
            attachedBlob = null;
        }
    });

    btnCancelRecording.addEventListener('click', function () {
        if (recordInterval) clearInterval(recordInterval);
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            audioChunks = [];
            mediaRecorder.stop();
        }
        recordingBar.classList.add('hidden');
        recordingBar.classList.remove('flex');
        mediaButtonsGrid.classList.remove('hidden');
    });

    btnSaveRecording.addEventListener('click', function () {
        if (recordInterval) clearInterval(recordInterval);
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
        }
        recordingBar.classList.add('hidden');
        recordingBar.classList.remove('flex');
    });

    // Form Values Extraction
    function getFormValues() {
        const nameInput = document.getElementById('contact_name');
        const phoneInput = document.getElementById('contact_phone');
        const msgInput = document.getElementById('contact_message');
        const typeInput = document.querySelector('input[name="type"]:checked');

        const typeLabels = {
            'suggestion': 'اقتراح وتطوير',
            'problem': 'مشكلة أو عطل تقني',
            'inquiry': 'استفسار عام',
            'complaint': 'شكوى أو ملاحظة'
        };

        return {
            name: nameInput ? nameInput.value.trim() : '',
            phone: phoneInput ? phoneInput.value.trim() : '',
            message: msgInput ? msgInput.value.trim() : '',
            typeKey: typeInput ? typeInput.value : 'suggestion',
            typeLabel: typeInput ? (typeLabels[typeInput.value] || 'اقتراح وتطوير') : 'اقتراح وتطوير'
        };
    }

    function showAlert(msg, kind) {
        alertBox.innerHTML = msg;
        alertBox.className = 'mb-5 p-4 rounded-xl text-sm font-medium';
        if (kind === 'success') {
            alertBox.classList.add('bg-emerald-50', 'text-emerald-800', 'border', 'border-emerald-200');
        } else if (kind === 'warning') {
            alertBox.classList.add('bg-amber-50', 'text-amber-800', 'border', 'border-amber-200');
        } else {
            alertBox.classList.add('bg-rose-50', 'text-rose-800', 'border', 'border-rose-200');
        }
        alertBox.classList.remove('hidden');
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // 1) Send via WhatsApp Action
    btnWhatsapp.addEventListener('click', function () {
        const val = getFormValues();

        if (!hasIdentity && !val.name) {
            showAlert('يرجى إدخال اسمك الكريم لمتابعة الطلب.', 'warning');
            return;
        }
        if (!hasIdentity && (!val.phone || val.phone.length < 8)) {
            showAlert('يرجى إدخال رقم جوال صحيح للتواصل معك.', 'warning');
            return;
        }

        const lines = [
            'السلام عليكم ورحمة الله وبركاته،',
            '*تواصل عبر منصة عائلة السريّع*',
            '─────────────────',
            `*الموضوع:* ${val.typeLabel}`,
            `*الاسم:* ${val.name || 'غير محدد'}`,
            `*الجوال:* ${val.phone || 'غير محدد'}`,
            hasIdentity ? '*حالة التوثيق:* عضو موثق بالبصمة ✅' : '*حالة التوثيق:* ضيف (رمز سريع) 👤'
        ];

        if (val.message) {
            lines.push('─────────────────');
            lines.push('*نص الرسالة / الاقتراح:*');
            lines.push(val.message);
        }

        if (attachedKind !== 'none') {
            const kindText = attachedKind === 'voice'
                ? 'تسجيل صوتي'
                : (attachedKind === 'video' ? 'مقطع فيديو' : 'صورة توضيحية');
            lines.push('─────────────────');
            lines.push(`*مرفق بالمنصة:* ${kindText}`);
            lines.push('(يرجى إرسال الملف المرفق مباشرة في هذه المحادثة)');
        }

        lines.push('─────────────────');
        lines.push('تم التوليد من منصة عائلة السريّع.');

        const encoded = encodeURIComponent(lines.join('\n'));
        const waUrl = `https://wa.me/${FAMILY_WA_NUMBER}?text=${encoded}`;
        window.open(waUrl, '_blank');
    });

    // 2) Send directly to Server API
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const val = getFormValues();

        if (!hasIdentity && !val.name) {
            showAlert('الاسم مطلوب: يرجى إدخال اسمك الكريم لمتابعة الطلب.', 'warning');
            return;
        }
        if (!hasIdentity && (!val.phone || val.phone.length < 8)) {
            showAlert('رقم الجوال مطلوب: يرجى إدخال رقم جوال صحيح للتواصل معك.', 'warning');
            return;
        }

        const hasActualFile = actualFileInput.files && actualFileInput.files.length > 0;
        if (!val.message && !attachedBlob && !hasActualFile) {
            showAlert('الرسالة فارغة: يرجى كتابة تفاصيل رسالتك أو إرفاق صورة أو تسجيل صوتي.', 'warning');
            return;
        }

        btnSubmit.disabled = true;
        btnSubmitText.textContent = 'جارٍ الإرسال والتسجيل...';
        alertBox.classList.add('hidden');

        const formData = new FormData(form);

        // If voice was recorded via MediaRecorder blob
        if (attachedBlob && attachedKind === 'voice') {
            const ext = attachedBlob.type.includes('ogg') ? 'ogg' : 'webm';
            formData.set('media_file', attachedBlob, `voice_note_${Date.now()}.${ext}`);
            formData.set('media_type', 'voice');
        }

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(async response => {
            const data = await response.json();
            if (!response.ok) {
                const err = (data.errors && Object.values(data.errors).flat().join('<br>')) || data.message || 'حدث خطأ أثناء إرسال الرسالة، يرجى المحاولة مرة أخرى.';
                throw new Error(err);
            }
            return data;
        })
        .then(res => {
            form.reset();
            resetMediaState();
            if (charCounter) charCounter.textContent = '0 حرفاً';
            showAlert('تم الاستلام بنجاح 🎉: ' + (res.message || 'تم تسجيل مشاركتك بنجاح لدى إدارة العائلة، وسيتم مراجعتها والاهتمام بها في أقرب فرصة بإذن الله.'), 'success');
        })
        .catch(err => {
            showAlert(err.message, 'danger');
        })
        .finally(() => {
            btnSubmit.disabled = false;
            btnSubmitText.textContent = 'إرسال الاقتراح رسمياً لإدارة العائلة';
        });
    });
});
</script>
@endpush
