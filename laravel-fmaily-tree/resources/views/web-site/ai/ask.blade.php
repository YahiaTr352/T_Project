@extends('layouts.web-site.web')

@section('title', 'اسأل شجرة العائلة')

@push('styles')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@include('web-site.ai.partials.styles')
@endpush

@section('content')
<div class="ai-ask-shell" dir="rtl"
     x-data="aiAskApp(@js([
         'chatUrl' => route('ai.ask.chat'),
         'voiceChatUrl' => route('ai.ask.voice-chat'),
         'ttsUrl' => route('ai.ask.tts'),
         'searchUrl' => route('ai.search'),
         'suggestions' => $suggestions,
     ]))">

    @include('web-site.ai.partials.hero')

    <div class="max-w-4xl mx-auto px-4 -mt-6 relative z-10 pb-16">
        {{-- Mode tabs --}}
        <div class="flex flex-wrap items-center justify-center gap-2 mb-5">
            <button type="button" class="ai-tab-btn" :class="{ 'is-active': mode === 'chat' }" @click="mode = 'chat'">
                <i class="fas fa-comments ml-1"></i> محادثة ذكية
            </button>
            <button type="button" class="ai-tab-btn" :class="{ 'is-active': mode === 'search' }" @click="mode = 'search'">
                <i class="fas fa-magnifying-glass ml-1"></i> بحث في العائلة
            </button>
        </div>

        {{-- Chat mode --}}
        <div x-show="mode === 'chat'" x-cloak>
            @include('web-site.ai.partials.question-types')

            <div class="ai-chat-panel">
                <div class="px-4 py-3 border-b border-emerald-100 bg-emerald-50/60 flex items-center justify-between gap-2">
                    <div class="text-sm font-bold text-emerald-900">
                        <i class="fas fa-message ml-1 text-emerald-600"></i>
                        المحادثة
                    </div>
                    <button type="button" x-show="messages.length" @click="clearChat()"
                            class="text-[11px] font-bold text-gray-500 hover:text-rose-600">
                        <i class="fas fa-rotate-left ml-1"></i> محادثة جديدة
                    </button>
                </div>

                <div class="p-4 h-[26rem] md:h-[28rem] overflow-y-auto space-y-3" x-ref="scroll">
                    <template x-if="messages.length === 0">
                        <div class="text-center text-gray-500 py-10 px-4">
                            <div class="w-16 h-16 rounded-2xl bg-emerald-50 flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-hand-pointer text-2xl text-emerald-400"></i>
                            </div>
                            <p class="text-sm font-bold text-gray-700 mb-1">ابدأ بخطوة واحدة</p>
                            <p class="text-xs text-gray-500 max-w-sm mx-auto">
                                اضغط على نوع السؤال بالأعلى، أو اختر اقتراحاً جاهزاً، أو اكتب سؤالك في الأسفل.
                            </p>
                        </div>
                    </template>

                    <template x-for="(msg, idx) in messages" :key="idx">
                        <div :class="msg.role === 'user' ? 'flex justify-start' : 'flex justify-end'">
                            <div class="max-w-[88%] px-4 py-3 text-sm leading-relaxed shadow-sm"
                                 :class="msg.role === 'user' ? 'ai-bubble-user' : 'ai-bubble-bot'">
                                <div x-text="msg.content" class="whitespace-pre-wrap"></div>

                                {{-- On-demand audio player (Zero token waste) --}}
                                <div class="mt-2 pt-2 border-t border-emerald-100 flex items-center justify-between" x-show="msg.role === 'bot'">
                                    <button type="button" @click="playTts(msg)"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold transition-all"
                                            :class="msg.isPlaying ? 'bg-emerald-600 text-white shadow-sm' : 'bg-emerald-100/70 text-emerald-800 hover:bg-emerald-200'">
                                        <template x-if="msg.loadingAudio">
                                            <span class="inline-flex items-center gap-1"><i class="fas fa-spinner fa-spin"></i> جاري تجهيز الصوت...</span>
                                        </template>
                                        <template x-if="!msg.loadingAudio && !msg.isPlaying">
                                            <span class="inline-flex items-center gap-1"><i class="fas fa-volume-high"></i> استمع للإجابة</span>
                                        </template>
                                        <template x-if="!msg.loadingAudio && msg.isPlaying">
                                            <span class="inline-flex items-center gap-1"><i class="fas fa-pause"></i> إيقاف مؤقت</span>
                                        </template>
                                    </button>
                                    <span class="text-[10px] text-gray-400 font-medium" x-show="msg.audioDuration" x-text="msg.audioDuration"></span>
                                </div>

                                <template x-if="msg.citations && msg.citations.length">
                                    <div class="mt-3 pt-2 border-t border-emerald-100 space-y-1">
                                        <div class="text-[10px] font-bold text-emerald-700">المصادر</div>
                                        <template x-for="cite in msg.citations" :key="cite.key || cite.id">
                                            <a :href="cite.url || '#'" class="block text-[11px] text-emerald-700 underline hover:text-emerald-900"
                                               x-text="cite.title"></a>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <template x-if="loading">
                        <div class="flex justify-end">
                            <div class="ai-bubble-bot px-4 py-3 text-sm ai-typing">
                                <span>●</span> <span>●</span> <span>●</span>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="border-t border-emerald-100 p-4 bg-emerald-50/40">
                    <div class="flex flex-wrap gap-2 mb-3" x-show="messages.length === 0">
                        <template x-for="s in suggestions" :key="s">
                            <button type="button" @click="send(s)" class="ai-suggestion-chip">
                                <span x-text="s"></span>
                            </button>
                        </template>
                    </div>
                    <form @submit.prevent="send()" class="flex gap-2">

                        <button type="button" @click="toggleVoiceRecording()"
                                class="rounded-xl px-4 py-3 text-sm font-bold transition-all shrink-0 border"
                                :class="isRecording ? 'bg-rose-500 text-white animate-pulse border-rose-600 shadow-md' : 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100'"
                                :title="isRecording ? 'اضغط للإرسال' : 'تسجيل رسالة صوتية'">
                            <i class="fas" :class="isRecording ? 'fa-stop' : 'fa-microphone'"></i>
                            <span x-show="isRecording" class="mr-1 text-xs" x-text="recordingTimer"></span>
                        </button>

                        <input type="text" x-model="input" :disabled="loading" maxlength="2000"
                               :placeholder="inputPlaceholder"
                               class="flex-1 rounded-xl border border-emerald-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400 bg-white">
                        <button type="submit" :disabled="loading || !input.trim()"
                                class="rounded-xl px-5 py-3 text-sm font-bold text-white gradient-primary disabled:opacity-50 shrink-0">
                            <span x-show="!loading">إرسال</span>
                            <span x-show="loading"><i class="fas fa-spinner fa-spin"></i></span>
                        </button>
                    </form>
                    <p class="text-[11px] text-rose-600 mt-2 font-medium" x-show="error" x-text="error"></p>
                </div>
            </div>
        </div>

        {{-- Search mode --}}
        <div x-show="mode === 'search'" x-cloak>
            <div class="ai-chat-panel p-5 md:p-6">
                <div class="flex items-start gap-3 mb-4">
                    <div class="w-11 h-11 rounded-xl gradient-primary flex items-center justify-center text-white shrink-0">
                        <i class="fas fa-magnifying-glass"></i>
                    </div>
                    <div>
                        <div class="text-base font-bold text-gray-800">بحث دلالي في العائلة</div>
                        <p class="text-xs text-gray-500 mt-1">اكتب وصفاً بلغة طبيعية — مثل: «اللي اتولد في الخمسينات»</p>
                    </div>
                </div>

                <form @submit.prevent="runSearch()" class="flex flex-col sm:flex-row gap-2">
                    <input x-model="searchQuery" type="search"
                           class="flex-1 rounded-xl border border-emerald-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400"
                           placeholder="مثال: قصص عن الهجرة أو أفراد من فرع معيّن...">
                    <button type="submit"
                            class="rounded-xl px-5 py-3 text-sm font-bold text-white gradient-primary disabled:opacity-50 shrink-0"
                            :disabled="searchLoading || !searchQuery.trim()">
                        <span x-show="!searchLoading">بحث</span>
                        <span x-show="searchLoading"><i class="fas fa-spinner fa-spin"></i> جاري البحث...</span>
                    </button>
                </form>

                <p class="text-[11px] text-rose-600 mt-3 font-medium" x-show="searchError" x-text="searchError"></p>

                <div class="mt-5 space-y-2" x-show="searchResults.length">
                    <div class="text-xs font-bold text-emerald-800 mb-2">
                        النتائج (<span x-text="searchResults.length"></span>)
                    </div>
                    <template x-for="item in searchResults" :key="(item.type||'')+'-'+(item.id||'')">
                        <a :href="item.url || '#'" class="ai-search-result block p-3 no-underline">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-bold text-emerald-800" x-text="item.title"></span>
                                <span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-bold" x-text="item.type"></span>
                            </div>
                            <div class="text-xs text-gray-600 mt-1 line-clamp-2" x-text="item.snippet"></div>
                        </a>
                    </template>
                </div>

                <div x-show="!searchLoading && searchDone && !searchResults.length && !searchError"
                     class="text-center py-10 text-sm text-gray-500">
                    <i class="fas fa-inbox text-2xl text-emerald-300 mb-2"></i>
                    <p>لا نتائج — جرّب صياغة مختلفة أو استخدم المحادثة الذكية.</p>
                </div>
            </div>
        </div>

        <p class="text-center text-[11px] text-gray-400 mt-6">
            الإجابات مبنية على بيانات مسجّلة في الموقع. للدقة، راجع المصادر المرفقة.
        </p>
    </div>
</div>
@endsection

@push('scripts')
<script>
function aiAskApp(config) {
    const placeholders = {
        person: 'اكتب اسم الشخص أو «من هو ...؟»',
        story: 'اسأل عن قصة أو حدث عائلي...',
        kinship: 'اسأل عن صلة القرابة بين شخصين...',
        stats: 'اسأل عن عدد أو إحصائية...',
        default: 'اكتب سؤالك هنا...',
    };

    return {
        mode: 'chat',
        chatUrl: config.chatUrl,
        searchUrl: config.searchUrl,
        suggestions: config.suggestions || [],
        messages: [],
        input: '',
        loading: false,
        error: '',
        conversationId: null,
        activeType: null,
        searchQuery: '',
        searchResults: [],
        searchLoading: false,
        searchError: '',
        searchDone: false,


        voiceChatUrl: config.voiceChatUrl,
        ttsUrl: config.ttsUrl,
        isRecording: false,
        mediaRecorder: null,
        audioChunks: [],
        recordingTimer: '0:00',
        recordingInterval: null,
        recordingSecs: 0,
        activeAudio: null,

        async playTts(msg) {
            if (msg.isPlaying && this.activeAudio) {
                this.activeAudio.pause();
                msg.isPlaying = false;
                return;
            }

            if (msg.audioUrl && this.activeAudio && this.activeAudio.src === msg.audioUrl) {
                this.activeAudio.play();
                msg.isPlaying = true;
                return;
            }

            if (this.activeAudio) {
                this.activeAudio.pause();
                this.messages.forEach(m => m.isPlaying = false);
            }

            if (msg.audioUrl) {
                this.startAudioPlayback(msg, msg.audioUrl);
                return;
            }

            msg.loadingAudio = true;
            try {
                const res = await fetch(this.ttsUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        message_id: msg.id || null,
                        text: msg.content
                    })
                });
                const data = await res.json();
                if (data.ok && data.audio_url) {
                    msg.audioUrl = data.audio_url;
                    msg.audioDuration = (data.duration_approx || 10) + ' ث';
                    this.startAudioPlayback(msg, data.audio_url);
                } else {
                    alert(data.message || 'تعذر تشغيل الصوت');
                }
            } catch (err) {
                alert('حدث خطأ في الاتصال بالصوت.');
            } finally {
                msg.loadingAudio = false;
            }
        },

        startAudioPlayback(msg, url) {
            this.activeAudio = new Audio(url);
            this.activeAudio.play();
            msg.isPlaying = true;
            this.activeAudio.onended = () => {
                msg.isPlaying = false;
            };
            this.activeAudio.onerror = () => {
                msg.isPlaying = false;
            };
        },

        async toggleVoiceRecording() {
            if (this.isRecording) {
                this.stopVoiceRecording();
            } else {
                await this.startVoiceRecording();
            }
        },

        async startVoiceRecording() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                this.mediaRecorder = new MediaRecorder(stream);
                this.audioChunks = [];
                this.mediaRecorder.ondataavailable = e => {
                    if (e.data.size > 0) this.audioChunks.push(e.data);
                };
                this.mediaRecorder.onstop = async () => {
                    const audioBlob = new Blob(this.audioChunks, { type: 'audio/mp4' });
                    await this.uploadVoiceAudio(audioBlob);
                    stream.getTracks().forEach(t => t.stop());
                };
                this.mediaRecorder.start();
                this.isRecording = true;
                this.recordingSecs = 0;
                this.recordingTimer = '0:00';
                this.recordingInterval = setInterval(() => {
                    this.recordingSecs++;
                    const m = Math.floor(this.recordingSecs / 60);
                    const s = this.recordingSecs % 60;
                    this.recordingTimer = m + ':' + (s < 10 ? '0' : '') + s;
                }, 1000);
            } catch (err) {
                alert('يرجى السماح بصلاحية الميكروفون للتحدث.');
            }
        },

        stopVoiceRecording() {
            if (this.mediaRecorder && this.isRecording) {
                this.mediaRecorder.stop();
                this.isRecording = false;
                clearInterval(this.recordingInterval);
            }
        },

        async uploadVoiceAudio(blob) {
            this.loading = true;
            this.error = '';
            const formData = new FormData();
            formData.append('audio', blob, 'voice.mp4');
            if (this.conversationId) formData.append('conversation_id', this.conversationId);

            try {
                const res = await fetch(this.voiceChatUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: formData
                });
                const data = await res.json();
                if (data.ok) {
                    this.conversationId = data.conversation_id;
                    this.messages.push({ role: 'user', content: '🎤 ' + data.transcription });
                    this.messages.push({
                        id: data.message_id,
                        role: 'bot',
                        content: data.answer,
                        citations: data.citations || [],
                        audioUrl: null,
                        isPlaying: false,
                        loadingAudio: false
                    });
                    this.$nextTick(() => {
                        if (this.$refs.scroll) this.$refs.scroll.scrollTop = this.$refs.scroll.scrollHeight;
                    });
                } else {
                    this.error = data.message || 'تعذر معالجة الرسالة الصوتية.';
                }
            } catch (err) {
                this.error = 'تعذر الاتصال بالخادم.';
            } finally {
                this.loading = false;
            }
        },

        get inputPlaceholder() {
            return placeholders[this.activeType] || placeholders.default;
        },

        pickType(key, question) {
            this.activeType = key;
            this.mode = 'chat';
            this.input = question;
            this.$nextTick(() => {
                const el = document.querySelector('.ai-chat-panel input[type="text"]');
                if (el) el.focus();
            });
        },

        clearChat() {
            this.messages = [];
            this.conversationId = null;
            this.error = '';
            this.input = '';
            this.activeType = null;
        },

        async send(preset) {
            const text = (preset || this.input || '').trim();
            if (!text || this.loading) return;
            this.error = '';
            this.messages.push({ role: 'user', content: text, citations: [] });
            this.input = '';
            this.loading = true;
            this.$nextTick(() => this.scrollBottom());
            try {
                const res = await fetch(this.chatUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        message: text,
                        conversation_id: this.conversationId,
                    }),
                });
                const data = await res.json();
                if (!data.ok) {
                    this.error = data.message || 'تعذر الحصول على إجابة.';
                    this.messages.push({ role: 'assistant', content: this.error, citations: [] });
                } else {
                    this.conversationId = data.conversation_id;
                    this.messages.push({
                        role: 'assistant',
                        content: data.answer,
                        citations: data.citations || [],
                    });
                }
            } catch (e) {
                this.error = 'حدث خطأ في الاتصال. تحقق من الإنترنت وحاول مرة أخرى.';
                this.messages.push({ role: 'assistant', content: this.error, citations: [] });
            } finally {
                this.loading = false;
                this.$nextTick(() => this.scrollBottom());
            }
        },

        async runSearch() {
            const q = this.searchQuery.trim();
            if (!q || this.searchLoading) return;
            this.searchLoading = true;
            this.searchError = '';
            this.searchResults = [];
            this.searchDone = false;
            try {
                const res = await fetch(this.searchUrl + '?q=' + encodeURIComponent(q), {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await res.json();
                if (!data.ok) {
                    this.searchError = data.message || 'فشل البحث.';
                } else {
                    this.searchResults = data.results || [];
                }
            } catch (e) {
                this.searchError = 'تعذر الاتصال بالبحث.';
            } finally {
                this.searchLoading = false;
                this.searchDone = true;
            }
        },

        scrollBottom() {
            const el = this.$refs.scroll;
            if (el) el.scrollTop = el.scrollHeight;
        },
    };
}
</script>
<style>[x-cloak]{display:none!important}</style>
@endpush
