@extends('layouts.app')

@section('title', 'مساحة الإبداع')

@push('styles')
    @include('dashboard.notifications.partials.styles')
    <style>
        .creative-library { --creative-line: #e2e8f0; --creative-tag-text: #075985; --creative-tag-bg: #e0f2fe; }
        body.dark .creative-library { --creative-line: #334155; --creative-tag-text: #bae6fd; --creative-tag-bg: #0c4a6e; }
        .creative-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(min(100%, 16rem), 1fr));
            grid-auto-rows: 1fr;
            align-items: stretch;
            gap: 1rem;
        }
        .creative-library .visual-lib-card { height: auto; }
        .creative-library .visual-lib-media { flex: none; }
        .creative-library .visual-lib-media img { object-fit: contain; }
        .creative-library .visual-lib-body { flex: 1; padding: 12px; }
        .creative-library .visual-lib-reason { max-height: 4.5rem; overflow: auto; overflow-wrap: anywhere; }
        .creative-library .visual-lib-actions { margin-top: auto; padding-top: 8px; border-top: 1px solid var(--creative-line); }
        .creative-library .btn, .creative-library .visual-lib-btn, .creative-library .creative-pagination .page-link { border-radius: 10px; }
        .creative-toolbar, .creative-filter, .creative-classification { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
        .creative-toolbar { justify-content: space-between; gap: 12px; }
        .creative-filter .dashboard-input { width: auto; min-width: 10rem; max-width: 100%; }
        .creative-filter-submit { align-self: stretch; padding-inline: 12px; }
        .creative-pagination .pagination { gap: 4px; justify-content: center; flex-wrap: wrap; margin-bottom: 0; }
        .creative-pagination .page-link { margin: 0; }
        .creative-type-pill, .creative-type-edit { display: inline-flex; align-items: center; gap: 5px; font-size: .75rem; }
        .creative-type-pill { border-radius: 999px; padding: 4px 9px; font-weight: 700; color: var(--creative-tag-text); background: var(--creative-tag-bg); }
        .creative-type-edit { cursor: pointer; font-weight: 700; color: var(--creative-tag-text); }
        .creative-type-edit::-webkit-details-marker { display: none; }
        .creative-category-editor[open] { flex-basis: 100%; }
        .creative-category-editor form { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 6px; margin-top: 6px; }
        .creative-category-feedback:empty { display: none; }
        .creative-category-feedback { font-size: .75rem; }
    </style>
@endpush

@section('content')
<div class="container-fluid py-4 creative-library" style="font-family: 'Tajawal', sans-serif;">
    <div
        data-visual-scope
        data-preview-modal="#visualPreviewModal"
        data-reject-modal="#visualRejectModal"
        data-confirm-modal="#visualConfirmModal"
    >
        {{-- Hero — مكون جاهز يدعم الوضع الليلي والنهاري --}}
        <x-dashboard.glass-hero
            title="مساحة الإبداع"
            subtitle="ارفع قوالب الصور وراجع تصاميم الأعضاء قبل إتاحتها في الدعوات."
        >
            <x-slot name="actions">
                @can('notifications.send')
                    <button type="button" class="btn btn-success btn-sm px-3 py-2" data-toggle="modal" data-target="#visualUploadModal">
                        <i class="fa-solid fa-arrow-up-from-bracket" aria-hidden="true"></i>
                        رفع قالب صورة
                    </button>
                @endcan
                <a
                    href="{{ route('dashboard.notifications.index') }}"
                    class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold rounded-xl transition-all border-0"
                    style="background:rgba(255,255,255,0.15);color:#fff;border:1px solid rgba(255,255,255,0.25) !important;"
                >
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    عودة
                </a>
            </x-slot>
        </x-dashboard.glass-hero>

        @include('components.alerts')

        {{-- إحصائيات سريعة --}}
        <div class="row mb-4">
            <div class="col-xl-4 col-md-6 mb-3 mb-xl-0">
                <a
                    href="{{ route('dashboard.creatives.index', ['status' => 'pending'] + $filters) }}"
                    class="dashboard-notif-card h-100 p-4 d-flex align-items-center text-decoration-none"
                    style="gap:12px;"
                >
                    <span class="action-icon-circle bg-soft-warning shrink-0">
                        <i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>
                    </span>
                    <span>
                        <span class="d-block notif-muted" style="font-size:11px;font-weight:700;">بانتظار المراجعة</span>
                        <span class="d-block notif-title" style="font-size:1.6rem;font-weight:900;line-height:1.2;">{{ number_format($counts['pending'] ?? 0) }}</span>
                    </span>
                </a>
            </div>
            <div class="col-xl-4 col-md-6 mb-3 mb-xl-0">
                <a
                    href="{{ route('dashboard.creatives.index', ['status' => 'approved'] + $filters) }}"
                    class="dashboard-notif-card h-100 p-4 d-flex align-items-center text-decoration-none"
                    style="gap:12px;"
                >
                    <span class="action-icon-circle bg-soft-success shrink-0">
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    </span>
                    <span>
                        <span class="d-block notif-muted" style="font-size:11px;font-weight:700;">تصاميم معتمدة</span>
                        <span class="d-block notif-title" style="font-size:1.6rem;font-weight:900;line-height:1.2;">{{ number_format($counts['approved'] ?? 0) }}</span>
                    </span>
                </a>
            </div>
            <div class="col-xl-4 col-md-6">
                <a
                    href="{{ route('dashboard.creatives.index', ['status' => 'rejected'] + $filters) }}"
                    class="dashboard-notif-card h-100 p-4 d-flex align-items-center text-decoration-none"
                    style="gap:12px;"
                >
                    <span class="action-icon-circle bg-soft-danger shrink-0">
                        <i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>
                    </span>
                    <span>
                        <span class="d-block notif-muted" style="font-size:11px;font-weight:700;">تصاميم مرفوضة</span>
                        <span class="d-block notif-title" style="font-size:1.6rem;font-weight:900;line-height:1.2;">{{ number_format($counts['rejected'] ?? 0) }}</span>
                    </span>
                </a>
            </div>
        </div>

        {{-- شريط التصفية --}}
        <div class="dashboard-notif-card p-3 mb-3">
            <div class="creative-toolbar">
                <x-dashboard.visual.status-tabs
                    baseRoute="dashboard.creatives.index"
                    :status="$status"
                    :counts="$counts"
                    :filters="$filters"
                />
                <form method="GET" action="{{ route('dashboard.creatives.index') }}" class="creative-filter">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <label class="notif-title mb-0" for="creativeInvitationType"><i class="fa-solid fa-sliders" aria-hidden="true"></i> نوع الدعوة</label>
                    <select id="creativeInvitationType" name="invitation_type" class="dashboard-input">
                        <option value="">كل الأنواع</option>
                        @foreach ($invitationTypes as $key => $label)
                            <option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>
                        @endforeach
                        <option value="unclassified" @selected($type === 'unclassified')>تصاميم عامة</option>
                    </select>
                    <label class="notif-title mb-0" for="creativeSource">مصدر التصميم</label>
                    <select id="creativeSource" name="source" class="dashboard-input">
                        <option value="">كل المصادر</option>
                        <option value="admin" @selected($source === 'admin')>تصاميم الإدارة</option>
                        <option value="members" @selected($source === 'members')>تصاميم الأعضاء</option>
                    </select>
                    <button type="submit" class="btn btn-success btn-sm creative-filter-submit">تصفية</button>
                </form>
            </div>
        </div>

        {{-- الشبكة / الحالة الفارغة --}}
        @if ($creatives->isEmpty())
            <x-dashboard.visual.empty-state
                icon="fa-solid fa-images"
                title="لا توجد تصاميم مطابقة"
                :subtitle="$filters ? 'جرّب تغيير عوامل التصفية.' : 'التصاميم تظهر هنا حسب حالة المراجعة المختارة.'"
            />
        @else
            <div class="creative-grid">
                @foreach ($creatives as $ct)
                    <x-dashboard.visual.library-card
                        :id="$ct->id"
                        :imageUrl="$previewUrls[$ct->id]"
                        :previewFields="$ct->editableTextFields()"
                        :name="$ct->name"
                        :description="$ct->description"
                        :uploaderName="$ct->uploader?->full_name ?? ($ct->publication_consented_at ? 'عضو سابق' : null)"
                        :createdAt="$ct->created_at?->format('Y-m-d')"
                        :status="$ct->status"
                        :statusLabel="$ct->status_label"
                        :rejectionReason="$ct->rejection_reason"
                        :approveUrl="$ct->awaitsPublicationConsent() || !$ct->invitation_type || in_array($ct->designMode(), [\App\Models\CreativeTemplate::MODE_ARTWORK, \App\Models\CreativeTemplate::MODE_SVG], true) ? null : route('dashboard.creatives.approve', $ct)"
                        :rejectUrl="route('dashboard.creatives.reject', $ct)"
                        :deleteUrl="route('dashboard.creatives.destroy', $ct)"
                        :canManage="auth()->user()?->can('notifications.send') ?? false"
                    >
                        <x-slot name="details">
                            @if ($ct->designMode() === \App\Models\CreativeTemplate::MODE_SVG)
                                @foreach ($ct->svg_inspection['warnings'] ?? [] as $warning)
                                    <p class="text-warning small mb-2">{{ $warning }}</p>
                                @endforeach
                                @can('notifications.send')
                                    <a href="{{ route('dashboard.creatives.svg.edit', $ct) }}" class="btn btn-outline-primary btn-sm mb-2">
                                        {{ $ct->svgReadyForReview() ? 'معاينة حقول SVG' : 'فحص ملف SVG' }}
                                    </a>
                                @endcan
                            @endif
                            @can('notifications.send')
                                @if ($ct->canPrepareArtwork() && $ct->status !== 'rejected')
                                    <a href="{{ route('dashboard.creatives.artwork.edit', $ct) }}" class="btn btn-outline-primary btn-sm mb-2">
                                        {{ $ct->editable_text ? 'تعديل حقول الكتابة' : 'تجهيز حقول الكتابة' }}
                                    </a>
                                @endif
                            @endcan
                            <div class="creative-classification">
                                <span class="creative-type-pill">
                                    <i class="fa-solid fa-tag" aria-hidden="true"></i>
                                    <span data-category-label>{{ $invitationTypes[$ct->invitation_type] ?? 'تصميم عام' }}</span>
                                </span>
                                @can('notifications.send')
                                    <details class="creative-category-editor">
                                        <summary class="creative-type-edit"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> تعديل النوع</summary>
                                        <form method="POST" action="{{ route('dashboard.creatives.update', $ct) }}" data-category-form>
                                            @csrf
                                            @method('PATCH')
                                            <label class="sr-only" for="creativeType{{ $ct->id }}">نوع الدعوة</label>
                                            <select name="invitation_type" id="creativeType{{ $ct->id }}" class="dashboard-input">
                                                <option value="">تصميم عام</option>
                                                @foreach ($invitationTypes as $key => $label)
                                                    <option value="{{ $key }}" @selected($ct->invitation_type === $key)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn btn-success btn-sm" aria-label="حفظ التصنيف">حفظ</button>
                                        </form>
                                    </details>
                                    <div class="creative-category-feedback w-100 notif-muted" data-category-feedback role="status"></div>
                                @endcan
                            </div>
                            @if ($ct->awaitsPublicationConsent())
                                <p class="notif-muted small mb-0"><i class="fa-solid fa-clock" aria-hidden="true"></i> بانتظار موافقة العضو على عرض التصميم.</p>
                            @endif
                        </x-slot>
                    </x-dashboard.visual.library-card>
                @endforeach
            </div>

            @if ($creatives->hasPages())
                <div class="creative-pagination d-flex justify-content-center mt-3">
                    {{ $creatives->links() }}
                </div>
            @endif
        @endif

        {{-- المودالات المشتركة --}}
        <x-dashboard.visual.preview-modal id="visualPreviewModal" title="معاينة التصميم" :show-fields="true" />
        <x-dashboard.visual.reject-modal
            id="visualRejectModal"
            title="رفض التصميم"
            inputId="visualRejectReason"
        />
        <x-dashboard.visual.confirm-modal
            id="visualConfirmModal"
            title="تأكيد الإجراء"
            message="هل أنت متأكد؟"
            confirmLabel="تأكيد"
        />

        @can('notifications.send')
            <x-dashboard.visual.upload-modal
                title="رفع تصميم"
                submit-label="رفع التصميم"
                name-label="اسم التصميم (اختياري)"
                accept=".svg,image/svg+xml"
                image-hint="SVG قابل للتحرير أو صورة ثابتة بحسب الخيار، حتى 16 ميغابايت."
                :invitationTypes="config('invitation_templates.templates', [])"
                :creativeModes="[
                    'svg' => 'قالب SVG قابل للتحرير',
                    'ready' => 'صورة ثابتة',
                ]"
                default-creative-mode="svg"
                :storeUrl="route('dashboard.creatives.store')"
            />
        @endcan

        <x-dashboard.visual.toasts />
    </div>
</div>
@endsection

@push('scripts')
    <script>
        const uploadMode = document.querySelector('[data-creative-design-mode]');
        uploadMode?.addEventListener('change', () => {
            const form = uploadMode.closest('form');
            const file = form.querySelector('[data-visual-file-input]');
            file.accept = uploadMode.value === 'svg' ? '.svg,image/svg+xml' : 'image/jpeg,image/png,image/webp,image/gif';
            file.value = '';
            const dropzone = file.closest('[data-visual-dropzone]');
            dropzone.querySelector('[data-visual-file-preview]').classList.add('d-none');
            dropzone.querySelector('[data-visual-drop-empty]').classList.remove('d-none');
        });
        const creativeTypeFilter = @json($type);
        document.querySelectorAll('[data-category-form]').forEach(function (form) {
            form.addEventListener('submit', async function (event) {
                event.preventDefault();
                const button = form.querySelector('button');
                const select = form.querySelector('select');
                const card = form.closest('[data-visual-card]');
                const status = card.querySelector('[data-category-feedback]');
                const submitted = new FormData(form);
                const selectedType = select.value || 'unclassified';
                const selectedLabel = select.selectedOptions[0].textContent.trim();
                status.textContent = '';
                button.disabled = true;
                select.disabled = true;
                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: { Accept: 'application/json' },
                        body: submitted,
                    });
                    const result = await response.json();
                    if (!response.ok) {
                        status.textContent = result.message || 'تعذر الحفظ';
                        return;
                    }
                    if (creativeTypeFilter && creativeTypeFilter !== selectedType) {
                        window.location.assign(@json(route('dashboard.creatives.index', ['status' => $status] + $filters)));
                        return;
                    }
                    if (card.querySelector('[data-category-label]').textContent.trim() !== selectedLabel) {
                        window.location.reload();
                        return;
                    }
                    card.querySelector('[data-category-label]').textContent = selectedLabel;
                    form.closest('details').open = false;
                    status.textContent = 'تم حفظ التصنيف';
                } catch (error) {
                    status.textContent = 'تعذر الاتصال، حاول مجددًا';
                } finally {
                    button.disabled = false;
                    select.disabled = false;
                }
            });
        });
    </script>
    <script src="{{ asset('js/dashboard/visual-library.js') }}?v={{ file_exists(public_path('js/dashboard/visual-library.js')) ? filemtime(public_path('js/dashboard/visual-library.js')) : time() }}"></script>
@endpush
