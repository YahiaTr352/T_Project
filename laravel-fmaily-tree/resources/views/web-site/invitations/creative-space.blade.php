@extends('web-site.invitations.layout')

@section('title', 'مساحة إبداع')

@section('head')
    <link rel="stylesheet" href="{{ asset('css/invitations/creative-field-editor.css') }}?v={{ filemtime(public_path('css/invitations/creative-field-editor.css')) }}">
@endsection

@section('content')
    <div class="inv-creative-page space-y-6">
        {{-- البانر التعريفي --}}
        <section class="inv-hero">
            <img src="{{ asset('images/invitations/greeting.jpg') }}" alt="مساحة إبداع" class="inv-hero-img">
            <div class="inv-hero-overlay">
                <h2 class="text-xl sm:text-2xl font-extrabold text-white">مساحة إبداع</h2>
                <p class="text-xs sm:text-sm text-emerald-50/90 mt-1 font-medium">قدّم تصميمك ليُعتمد في قوالب العائلة</p>
            </div>
        </section>

        @if (session('creative_success'))
            <p role="status" class="rounded-2xl inv-badge-success p-4 text-sm font-bold shadow-sm">
                <i class="fas fa-check-circle ml-1.5" aria-hidden="true"></i>{{ session('creative_success') }}
            </p>
        @endif
        @foreach ($errors->creative->all() as $error)
            <p role="alert" class="rounded-2xl inv-badge-danger p-4 text-sm font-bold shadow-sm">
                <i class="fas fa-exclamation-circle ml-1.5" aria-hidden="true"></i>{{ $error }}
            </p>
        @endforeach

        <form action="{{ route('invitations.creatives.submit', absolute: false) }}" method="POST" enctype="multipart/form-data"
            class="inv-card space-y-5 rounded-3xl p-5 sm:p-6" id="creativeSubmission"
            data-preview-url="{{ route('invitations.creatives.fields.preview', absolute: false) }}">
            @csrf

            <div class="inv-section-heading mb-1">
                <span class="inv-section-title"><span class="inv-dot"></span>قدّم تصميمك</span>
                <span class="text-xs inv-muted">ارفع تصميمك كاملًا وأرسله للإدارة للمراجعة</span>
            </div>

            {{-- اختيار نوع الدعوة كشرائح متزامنة مع الحقل --}}
            <div>
                <label for="submissionType" class="block text-xs font-bold inv-muted mb-2">نوع الدعوة</label>
                <select id="submissionType" name="invitation_type" required class="sr-only">
                    <option value="">اختر نوع الدعوة</option>
                    @foreach ($templates as $tpl)
                        <option value="{{ $tpl['key'] }}" @selected(old('invitation_type') === $tpl['key'])>{{ $tpl['name'] }}</option>
                    @endforeach
                </select>
                <div class="flex flex-wrap gap-2" id="submissionTypePills">
                    @foreach ($templates as $tpl)
                        @php $isSelected = old('invitation_type') === $tpl['key']; @endphp
                        <button type="button" data-type-pill="{{ $tpl['key'] }}"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-xs sm:text-sm font-bold border transition cursor-pointer {{ $isSelected ? 'inv-pill' : 'inv-inset inv-text-sec' }}"
                            style="{{ $isSelected ? 'border-color: var(--inv-primary);' : '' }}">
                            <i class="fas fa-check text-[11px] {{ $isSelected ? '' : 'hidden' }}" data-type-check aria-hidden="true"></i>
                            <span>{{ $tpl['name'] }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="submissionName" class="block text-xs font-bold inv-muted mb-1.5">اسم التصميم (اختياري)</label>
                    <input id="submissionName" name="name" value="{{ old('name') }}" maxlength="255"
                        placeholder="مثال: بطاقة زفاف الياسمين"
                        class="inv-input w-full px-4 py-3 text-sm font-medium">
                </div>
                <div>
                    <span class="block text-xs font-bold inv-muted mb-1.5">ملف التصميم</span>
                    <label class="creative-editor-upload" for="submissionImage">
                        <span class="w-10 h-10 rounded-full inv-btn-primary inline-flex items-center justify-center shrink-0">
                            <i class="fas fa-cloud-upload-alt text-white" aria-hidden="true"></i>
                        </span>
                        <div class="min-w-0">
                            <span id="submissionFileName" class="block text-sm font-extrabold truncate">اختيار صورة التصميم</span>
                            <small class="block text-[11px] mt-0.5">تحدد الإدارة النص القابل للتعديل قبل اعتماده · JPG, PNG, WebP, SVG</small>
                        </div>
                    </label>
                    <input id="submissionImage" name="image" type="file" accept=".jpg,.jpeg,.png,.webp,.svg,image/svg+xml" required class="sr-only">
                </div>
            </div>

            @include('web-site.invitations.partials.creative-field-editor')

            <div class="creative-editor-footer pt-2 border-t" style="border-color: var(--inv-border);">
                <label class="flex items-center gap-2.5 text-sm inv-text-sec cursor-pointer">
                    <input type="checkbox" name="publication_consent" value="1" required @checked(old('publication_consent'))
                        class="w-4 h-4 rounded accent-emerald-600">
                    <span>أوافق على عرض تصميمي للأعضاء في قوالب الصور إذا اعتمدته الإدارة.</span>
                </label>
                <p id="submissionStatus" class="creative-editor-status" role="status"></p>
                <button type="submit" id="submissionSend" class="inv-btn-primary px-6 py-3 text-sm inline-flex items-center justify-center gap-2 cursor-pointer">
                    <i class="fas fa-paper-plane" aria-hidden="true"></i>
                    <span>إرسال للمراجعة</span>
                </button>
            </div>
            @include('web-site.invitations.partials.creative-submission-review')
        </form>

        <section aria-labelledby="my-designs" class="space-y-3">
            <div class="inv-section-heading">
                <h2 id="my-designs" class="inv-section-title"><span class="inv-dot"></span>تصاميمي</h2>
                @if ($myCreatives->total() > 0)
                    <span class="inv-pill">{{ $myCreatives->total() }} {{ $myCreatives->total() >= 3 && $myCreatives->total() <= 10 ? 'تصاميم' : 'تصميم' }}</span>
                @endif
            </div>

            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @php $typeNames = array_column($templates, 'name', 'key'); @endphp
                @forelse ($myCreatives as $design)
                    @php
                        $isRejected = $design->status === 'rejected' || !empty($design->rejection_reason);
                        $badgeClass = match($design->status) {
                            'approved' => 'inv-badge-success',
                            'rejected' => 'inv-badge-danger',
                            default => 'inv-badge-warning',
                        };
                    @endphp
                    <article class="inv-card rounded-2xl p-4 flex flex-col gap-3"
                        style="{{ $isRejected ? 'background: var(--inv-danger-bg); border-color: var(--inv-danger-border);' : '' }}">
                        <div class="flex items-center gap-3">
                            @if ($design->image_path)
                                <div class="w-16 h-16 rounded-xl inv-inset overflow-hidden shrink-0 flex items-center justify-center">
                                    <img src="{{ route('invitations.creatives.preview-image', $design->id) }}" alt="{{ $design->name }}" loading="lazy" data-zoomable tabindex="0"
                                        class="w-full h-full object-cover cursor-zoom-in">
                                </div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="break-words font-extrabold text-sm" style="color: var(--inv-text)">{{ $design->name ?: 'تصميمك' }}</h3>
                                    <span class="shrink-0 px-2.5 py-0.5 rounded-full text-[11px] font-bold {{ $badgeClass }}">
                                        {{ $design->status_label }}
                                    </span>
                                </div>
                                <p class="text-xs inv-muted mt-0.5">{{ $typeNames[$design->invitation_type] ?? 'تصميم عام' }}</p>
                            </div>
                        </div>

                        @if ($design->rejection_reason)
                            <p class="break-words text-xs font-bold leading-relaxed" style="color: var(--inv-danger-text)">
                                السبب: {{ $design->rejection_reason }}
                            </p>
                        @endif

                        @if ($design->designMode() === \App\Models\CreativeTemplate::MODE_SVG)
                            @foreach ($design->svg_inspection['warnings'] ?? [] as $warning)
                                <p class="break-words text-xs font-medium px-3 py-2 rounded-xl inv-badge-warning">{{ $warning }}</p>
                            @endforeach
                            @if ($design->status === 'pending' && $design->svgReadyForReview())
                                <p class="text-xs font-bold px-3 py-2 rounded-xl inv-badge-success">الملف فيه نص قابل للتعديل وهو جاهز لمعاينة الإدارة.</p>
                            @endif
                        @endif

                        @if ($design->awaitsPublicationConsent())
                            <form method="POST" action="{{ route('invitations.creatives.consent', $design->id) }}" class="pt-2 border-t space-y-2" style="border-color: var(--inv-border);">
                                @csrf
                                <label class="flex items-start gap-2 text-xs inv-text-sec cursor-pointer">
                                    <input type="checkbox" name="publication_consent" value="1" required class="mt-0.5 accent-emerald-600">
                                    <span>أوافق على عرض هذا التصميم في قوالب الصور بعد اعتماده.</span>
                                </label>
                                <button type="submit" class="inv-pill cursor-pointer">حفظ الموافقة</button>
                            </form>
                        @endif
                    </article>
                @empty
                    <div class="md:col-span-2 xl:col-span-3 inv-card rounded-2xl p-8 text-center">
                        <p class="text-sm inv-muted">لم ترسل أي تصميم بعد.</p>
                    </div>
                @endforelse
            </div>

            @if ($myCreatives->hasPages())
                <div class="mt-4">{{ $myCreatives->links() }}</div>
            @endif
        </section>
    </div>
@endsection

@section('scripts')
    <script>
        (() => {
            const select = document.getElementById('submissionType');
            const pills = Array.from(document.querySelectorAll('[data-type-pill]'));
            function syncTypePills() {
                const current = select.value;
                pills.forEach((btn) => {
                    const active = btn.dataset.typePill === current;
                    btn.classList.toggle('inv-pill', active);
                    btn.classList.toggle('inv-inset', !active);
                    btn.classList.toggle('inv-text-sec', !active);
                    btn.style.borderColor = active ? 'var(--inv-primary)' : '';
                    btn.querySelector('[data-type-check]')?.classList.toggle('hidden', !active);
                });
            }
            pills.forEach((btn) => {
                btn.addEventListener('click', () => {
                    select.value = btn.dataset.typePill;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    syncTypePills();
                });
            });
            select.addEventListener('change', syncTypePills);
            syncTypePills();
        })();
    </script>
    <script src="{{ asset('js/invitations/creative-field-editor.js') }}?v={{ filemtime(public_path('js/invitations/creative-field-editor.js')) }}" defer></script>
@endsection
