@extends('web-site.invitations.layout')

@section('title', 'مساحة إبداع')

@section('head')
    <link rel="stylesheet" href="{{ asset('css/invitations/creative-field-editor.css') }}?v={{ filemtime(public_path('css/invitations/creative-field-editor.css')) }}">
@endsection

@section('content')
    <h1 class="mb-6 text-3xl font-extrabold">مساحة إبداع</h1>
    @if (session('creative_success'))
        <p role="status" class="mb-4 rounded-xl bg-emerald-50 p-3 text-emerald-800">{{ session('creative_success') }}</p>
    @endif
    @foreach ($errors->creative->all() as $error)
        <p role="alert" class="mb-3 rounded-xl bg-rose-50 p-3 text-rose-800">{{ $error }}</p>
    @endforeach

    <div class="space-y-8">
        <form action="{{ route('invitations.creatives.submit', absolute: false) }}" method="POST" enctype="multipart/form-data"
            class="glass-card space-y-5 rounded-3xl p-5 sm:p-6" id="creativeSubmission"
            data-preview-url="{{ route('invitations.creatives.fields.preview', absolute: false) }}">
            @csrf
            <div class="grid gap-5 lg:grid-cols-[minmax(12rem,1.2fr)_1fr_1fr] lg:items-end">
                <div>
                    <h2 class="text-xl font-bold mb-2">قدّم تصميمك</h2>
                    <label class="creative-editor-upload" for="submissionImage">
                        <i class="fas fa-cloud-upload-alt" aria-hidden="true"></i>
                        <span id="submissionFileName">اختر صورة تصميمك</span>
                        <small>JPG أو PNG أو WebP أو SVG · حتى ١٦ ميغابايت</small>
                    </label>
                    <input id="submissionImage" name="image" type="file" accept=".jpg,.jpeg,.png,.webp,.svg,image/svg+xml" required class="sr-only">
                </div>
                <label class="block text-sm font-bold">نوع الدعوة
                    <select id="submissionType" name="invitation_type" required class="mt-1 w-full rounded-xl border p-2">
                        <option value="">اختر نوع الدعوة</option>
                        @foreach ($templates as $tpl)
                            <option value="{{ $tpl['key'] }}" @selected(old('invitation_type') === $tpl['key'])>{{ $tpl['name'] }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-bold">اسم التصميم (اختياري)
                    <input id="submissionName" name="name" value="{{ old('name') }}" maxlength="255" class="mt-1 w-full rounded-xl border p-2">
                </label>
            </div>

            @include('web-site.invitations.partials.creative-field-editor')

            <div class="creative-editor-footer">
                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="publication_consent" value="1" required @checked(old('publication_consent'))>
                    <span>أوافق على عرض تصميمي للأعضاء إذا اعتمدته الإدارة.</span>
                </label>
                <p id="submissionStatus" class="creative-editor-status" role="status"></p>
                <button type="submit" id="submissionSend" class="creative-editor-button creative-editor-primary">إرسال للمراجعة</button>
            </div>
            <dialog id="creativeSubmissionReview" class="creative-editor-review" aria-labelledby="creativeSubmissionReviewTitle">
                <h3 id="creativeSubmissionReviewTitle">راجع التصميم قبل الإرسال</h3>
                <img id="creativeSubmissionPreview" alt="معاينة التصميم مع المتغيّرات">
                <div class="creative-editor-review-actions">
                    <button id="creativeSubmissionReturn" type="button" class="creative-editor-button">رجوع للتعديل</button>
                    <button id="creativeSubmissionConfirm" type="button" class="creative-editor-button creative-editor-primary">تأكيد الإرسال للمراجعة</button>
                </div>
            </dialog>
        </form>

        <section aria-labelledby="my-designs">
            <h2 id="my-designs" class="mb-3 text-xl font-bold">تصاميمي</h2>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @php($typeNames = array_column($templates, 'name', 'key'))
                @forelse ($myCreatives as $design)
                    <article class="glass-card rounded-2xl p-4">
                        <div class="flex items-center gap-3">
                            @if ($design->image_path)
                                <img src="{{ route('invitations.creatives.preview-image', $design->id) }}" alt="{{ $design->name }}" loading="lazy" data-zoomable tabindex="0"
                                    class="h-20 w-20 shrink-0 rounded-xl object-contain">
                            @endif
                            <div class="min-w-0">
                                <h3 class="break-words font-bold">{{ $design->name }}</h3>
                                <p class="text-xs text-slate-600">{{ $typeNames[$design->invitation_type] ?? 'تصميم عام' }}</p>
                                <p class="mt-1 text-sm font-bold">{{ $design->status_label }}</p>
                            </div>
                        </div>
                        @if ($design->rejection_reason)
                            <p class="mt-2 break-words text-sm text-rose-700">{{ $design->rejection_reason }}</p>
                        @endif
                        @if ($design->designMode() === \App\Models\CreativeTemplate::MODE_SVG)
                            @foreach ($design->svg_inspection['warnings'] ?? [] as $warning)
                                <p class="mt-2 break-words text-sm text-amber-800">{{ $warning }}</p>
                            @endforeach
                            @if ($design->status === 'pending' && $design->svgReadyForReview())
                                <p class="mt-2 text-sm text-emerald-800">الملف فيه نص قابل للتعديل وهو جاهز لمعاينة الإدارة.</p>
                            @endif
                        @endif
                        @if ($design->awaitsPublicationConsent())
                            <form method="POST" action="{{ route('invitations.creatives.consent', $design->id) }}" class="mt-3 space-y-2">
                                @csrf
                                <label class="flex items-start gap-2 text-sm">
                                    <input type="checkbox" name="publication_consent" value="1" required>
                                    <span>أوافق على عرض هذا التصميم في قوالب الصور بعد اعتماده.</span>
                                </label>
                                <button type="submit" class="font-bold text-emerald-700">حفظ الموافقة</button>
                            </form>
                        @endif
                    </article>
                @empty
                    <p class="text-sm text-slate-600">لم ترسل أي تصميم بعد.</p>
                @endforelse
            </div>
            @if ($myCreatives->hasPages())
                <div class="mt-4">{{ $myCreatives->links() }}</div>
            @endif
        </section>
    </div>
@endsection

@section('scripts')
    <script src="{{ asset('js/invitations/creative-field-editor.js') }}?v={{ filemtime(public_path('js/invitations/creative-field-editor.js')) }}" defer></script>
@endsection
